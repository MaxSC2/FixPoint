<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\Config;
use FixPoint\Core\UploadedFile;
use FixPoint\Core\ValidationException;
use Throwable;

final class TechnicianAvatarService
{
    public function __construct(
        private readonly Config $config,
        private readonly ImageValidator $validator
    ) {
    }

    public function store(int $technicianId, UploadedFile $upload): string
    {
        if ($technicianId < 1) {
            throw new ValidationException(['avatar' => 'Не удалось определить профиль мастера.']);
        }

        try {
            $image = $this->validator->validate($upload);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            throw new ValidationException([
                'avatar' => (string) ($errors['attachments'] ?? 'Изображение не прошло проверку.'),
            ]);
        }

        $storageKey = 'avatars/' . $technicianId . '/' . bin2hex(random_bytes(16))
            . '.' . $image['extension'];
        $root = $this->privateRoot();
        $directory = $root . DIRECTORY_SEPARATOR . 'avatars'
            . DIRECTORY_SEPARATOR . $technicianId;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create private avatar directory.');
        }
        @chmod($directory, 0750);

        $destination = $directory . DIRECTORY_SEPARATOR . basename($storageKey);
        try {
            $upload->moveTo($destination);
            $this->reencodeWhenAvailable($destination, $image['mime_type']);

            $storedSize = filesize($destination);
            if ($storedSize === false || $storedSize < 1 || $storedSize > ImageValidator::MAX_FILE_BYTES) {
                throw new ValidationException([
                    'avatar' => 'После обработки изображение превысило допустимый размер.',
                ]);
            }
            @chmod($destination, 0640);
        } catch (Throwable $exception) {
            if (is_file($destination)) {
                @unlink($destination);
            }
            throw $exception;
        }

        return $storageKey;
    }

    public function delete(?string $storageKey): void
    {
        $path = $this->absolutePath($storageKey);
        if ($path === null || !is_file($path)) {
            return;
        }

        @unlink($path);
        $directory = dirname($path);
        if (is_dir($directory)) {
            @rmdir($directory);
        }
    }

    /** @return array{path: string, mime_type: string}|null */
    public function resolvePublic(?string $storageKey): ?array
    {
        $path = $this->absolutePath($storageKey);
        if ($path === null || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
        if ($mimeType === null) {
            return null;
        }

        return ['path' => $path, 'mime_type' => $mimeType];
    }

    private function absolutePath(?string $storageKey): ?string
    {
        if ($storageKey === null
            || preg_match('/\Aavatars\/([1-9][0-9]*)\/([a-f0-9]{32})\.(jpg|png|webp)\z/', $storageKey) !== 1
        ) {
            return null;
        }

        $root = realpath($this->privateRoot());
        if ($root === false) {
            return null;
        }

        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
    }

    private function privateRoot(): string
    {
        $root = rtrim((string) $this->config->get('paths.base'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private';
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new \RuntimeException('Unable to create private storage root.');
        }
        @chmod($root, 0750);

        return $root;
    }

    private function reencodeWhenAvailable(string $path, string $mimeType): void
    {
        if (!function_exists('imagecreatefromstring')) {
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read stored avatar for processing.');
        }
        $image = @imagecreatefromstring($contents);
        unset($contents);
        if ($image === false) {
            throw new ValidationException(['avatar' => 'Не удалось декодировать изображение.']);
        }

        $temporary = $path . '.clean';
        $written = false;
        try {
            if ($mimeType === 'image/jpeg') {
                $written = imagejpeg($image, $temporary, 88);
            } elseif ($mimeType === 'image/png') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $written = imagepng($image, $temporary, 6);
            } elseif ($mimeType === 'image/webp' && function_exists('imagewebp')) {
                $written = imagewebp($image, $temporary, 88);
            } else {
                throw new ValidationException(['avatar' => 'Сервер не может безопасно обработать этот формат изображения.']);
            }

            if (!$written || !is_file($temporary) || filesize($temporary) < 1) {
                throw new \RuntimeException('Unable to re-encode uploaded avatar.');
            }
            if (!rename($temporary, $path)) {
                throw new \RuntimeException('Unable to replace avatar with sanitized version.');
            }
        } finally {
            imagedestroy($image);
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
