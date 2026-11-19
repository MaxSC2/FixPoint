<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\Config;
use FixPoint\Core\HttpException;
use FixPoint\Core\UploadedFile;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\AttachmentRepository;
use FixPoint\Repositories\RequestRepository;
use PDO;
use Throwable;

final class AttachmentService
{
    private const MAX_FILES_PER_SUBMISSION = 5;
    private const MAX_FILES_PER_REQUEST = 10;

    public function __construct(
        private readonly Config $config,
        private readonly AttachmentRepository $attachments,
        private readonly RequestRepository $requests,
        private readonly ImageValidator $imageValidator
    ) {
    }

    /**
     * Validate and store a batch inside the caller's database transaction.
     * Returns storage keys so the caller can remove files if its transaction
     * later rolls back.
     *
     * @param list<UploadedFile> $uploads
     * @return list<string>
     * @throws ValidationException
     */
    public function storeUploads(
        PDO $pdo,
        int $requestId,
        ?int $messageId,
        int $uploaderId,
        array $uploads
    ): array {
        if ($uploads === []) {
            return [];
        }
        if (count($uploads) > self::MAX_FILES_PER_SUBMISSION) {
            throw new ValidationException([
                'attachments' => 'За один раз можно прикрепить не более 5 изображений.',
            ]);
        }
        if ($requestId < 1 || $uploaderId < 1) {
            throw new HttpException(404, 'Request not found');
        }

        // Serialize uploads for a request so concurrent submissions cannot
        // both pass the per-request attachment cap against the same count.
        $lock = $pdo->prepare(
            'SELECT id FROM repair_requests WHERE id = :request_id FOR UPDATE'
        );
        $lock->execute(['request_id' => $requestId]);
        if ($lock->fetchColumn() === false) {
            throw new HttpException(404, 'Request not found');
        }

        $existing = $this->attachments->countForRequest($requestId);
        if ($existing + count($uploads) > self::MAX_FILES_PER_REQUEST) {
            throw new ValidationException([
                'attachments' => 'К одной заявке можно прикрепить не более 10 изображений.',
            ]);
        }

        $savedKeys = [];
        try {
            foreach ($uploads as $upload) {
                if (!$upload instanceof UploadedFile) {
                    throw new ValidationException(['attachments' => 'Не удалось прочитать загруженное изображение.']);
                }

                $validatedImage = $this->imageValidator->validate($upload);
                $detectedMime = $validatedImage['mime_type'];
                $extension = $validatedImage['extension'];
                $width = $validatedImage['width'];
                $height = $validatedImage['height'];
                $storageKey = 'requests/' . $requestId . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
                $storageRoot = rtrim((string) $this->config->get('paths.base'), DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private';
                if (!is_dir($storageRoot) && !mkdir($storageRoot, 0750, true) && !is_dir($storageRoot)) {
                    throw new \RuntimeException('Unable to create private storage root.');
                }
                @chmod($storageRoot, 0750);
                $destination = $this->absolutePathForNewFile($storageKey);
                $directory = dirname($destination);

                if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                    throw new \RuntimeException('Unable to create private attachment directory.');
                }
                @chmod($directory, 0750);

                $upload->moveTo($destination);
                $savedKeys[] = $storageKey;

                // Docker includes GD and re-encodes accepted images, stripping
                // EXIF/XMP metadata. Local PHP without GD keeps the validated
                // original so uploads remain usable in the demo environment.
                $this->reencodeWhenAvailable($destination, $detectedMime);

                $storedSize = filesize($destination);
                if ($storedSize === false || $storedSize < 1 || $storedSize > ImageValidator::MAX_FILE_BYTES) {
                    throw new ValidationException([
                        'attachments' => 'После обработки изображение превысило допустимый размер.',
                    ]);
                }

                $originalName = $upload->originalName();
                if ($originalName === '') {
                    $originalName = 'Изображение.' . $extension;
                }
                $originalName = mb_substr($originalName, 0, 255);

                $this->attachments->insert($pdo, [
                    'request_id' => $requestId,
                    'message_id' => $messageId,
                    'uploader_id' => $uploaderId,
                    'storage_key' => $storageKey,
                    'original_name' => $originalName,
                    'mime_type' => $detectedMime,
                    'byte_size' => (int) $storedSize,
                    'width' => $width,
                    'height' => $height,
                ]);
            }
        } catch (Throwable $exception) {
            $this->cleanup($savedKeys);
            throw $exception;
        }

        return $savedKeys;
    }

    /** @param list<string> $storageKeys */
    public function cleanup(array $storageKeys): void
    {
        foreach ($storageKeys as $storageKey) {
            $path = $this->absolutePathForKey($storageKey);
            if ($path !== null && is_file($path)) {
                @unlink($path);
                $directory = dirname($path);
                if (is_dir($directory)) {
                    @rmdir($directory);
                }
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function requestImages(int $requestId): array
    {
        return $this->attachments->requestImages($requestId);
    }

    /**
     * @param list<int> $messageIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forMessages(int $requestId, array $messageIds): array
    {
        return $this->attachments->forMessages($requestId, $messageIds);
    }

    /**
     * Resolve a file only after verifying the current user's access to the
     * parent request. Storage keys and paths are never taken from the URL.
     *
     * @param array<string, mixed> $user
     * @return array{path: string, mime_type: string, original_name: string, byte_size: int}|null
     */
    public function findAccessible(int $attachmentId, array $user, ?int $expectedRequestId = null): ?array
    {
        $row = $this->attachments->findById($attachmentId);
        if ($row === null) {
            return null;
        }
        if ($expectedRequestId !== null && (int) ($row['request_id'] ?? 0) !== $expectedRequestId) {
            return null;
        }

        $request = $this->requests->findById((int) ($row['request_id'] ?? 0));
        if ($request === null || !$this->canAccess($request, $user)) {
            return null;
        }

        $path = $this->absolutePathForKey((string) ($row['storage_key'] ?? ''));
        if ($path === null || !is_file($path) || !is_readable($path)) {
            return null;
        }

        return [
            'path' => $path,
            'mime_type' => (string) $row['mime_type'],
            'original_name' => (string) $row['original_name'],
            'byte_size' => (int) $row['byte_size'],
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $user
     */
    private function canAccess(array $request, array $user): bool
    {
        $userId = (int) ($user['id'] ?? 0);

        return match ((string) ($user['role'] ?? '')) {
            'admin' => $userId > 0,
            'customer' => $userId > 0 && (int) ($request['customer_id'] ?? 0) === $userId,
            'technician' => $userId > 0 && (int) ($request['technician_id'] ?? 0) === $userId,
            default => false,
        };
    }

    private function absolutePathForNewFile(string $storageKey): string
    {
        $path = $this->absolutePathForKey($storageKey);
        if ($path === null) {
            throw new \RuntimeException('Invalid generated attachment storage key.');
        }

        return $path;
    }

    private function absolutePathForKey(string $storageKey): ?string
    {
        if (preg_match('/\Arequests\/([1-9][0-9]*)\/([a-f0-9]{32})\.(jpg|png|webp)\z/', $storageKey) !== 1) {
            return null;
        }

        $root = rtrim((string) $this->config->get('paths.base'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private';
        $root = realpath($root);
        if ($root === false) {
            return null;
        }

        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
    }

    private function reencodeWhenAvailable(string $path, string $mimeType): void
    {
        if (!function_exists('imagecreatefromstring')) {
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read stored image for processing.');
        }

        $image = @imagecreatefromstring($contents);
        unset($contents);
        if ($image === false) {
            throw new ValidationException(['attachments' => 'Не удалось декодировать изображение.']);
        }

        $temporary = $path . '.clean';
        $written = false;

        try {
            if ($mimeType === 'image/jpeg') {
                // JPEG has no alpha channel, so a second full-size canvas is
                // unnecessary and would double peak memory for large photos.
                $written = imagejpeg($image, $temporary, 88);
            } elseif ($mimeType === 'image/png') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $written = imagepng($image, $temporary, 6);
            } elseif ($mimeType === 'image/webp' && function_exists('imagewebp')) {
                $written = imagewebp($image, $temporary, 88);
            } else {
                throw new ValidationException([
                    'attachments' => 'Сервер не может безопасно обработать этот формат изображения.',
                ]);
            }

            if (!$written || !is_file($temporary) || filesize($temporary) < 1) {
                throw new \RuntimeException('Unable to re-encode uploaded image.');
            }
            if (!rename($temporary, $path)) {
                throw new \RuntimeException('Unable to replace image with sanitized version.');
            }
            @chmod($path, 0640);
        } finally {
            imagedestroy($image);
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

}
