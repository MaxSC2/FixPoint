<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\UploadedFile;
use FixPoint\Core\ValidationException;

final class ImageValidator
{
    public const MAX_FILE_BYTES = 8 * 1024 * 1024;
    public const MAX_PIXELS = 40_000_000;

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Validate the upload from server-side file metadata, never from the
     * browser-supplied MIME type or filename extension.
     *
     * @return array{mime_type: string, extension: string, byte_size: int, width: int, height: int}
     * @throws ValidationException
     */
    public function validate(UploadedFile $upload): array
    {
        $errorCode = $upload->errorCode();
        if ($errorCode !== UPLOAD_ERR_OK || !$upload->isAvailable()) {
            throw new ValidationException([
                'attachments' => $this->uploadErrorMessage($errorCode),
            ]);
        }

        $size = $upload->actualSize();
        if ($size < 1 || $size > self::MAX_FILE_BYTES) {
            throw new ValidationException([
                'attachments' => 'Размер каждого изображения не должен превышать 8 МБ.',
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($upload->temporaryPath());
        if (!is_string($detectedMime) || !isset(self::MIME_EXTENSIONS[$detectedMime])) {
            throw new ValidationException([
                'attachments' => 'Поддерживаются только изображения JPEG, PNG и WebP.',
            ]);
        }

        $imageInfo = @getimagesize($upload->temporaryPath());
        if (!is_array($imageInfo)
            || !isset($imageInfo[0], $imageInfo[1], $imageInfo['mime'])
            || (string) $imageInfo['mime'] !== $detectedMime
        ) {
            throw new ValidationException([
                'attachments' => 'Файл не прошёл проверку формата изображения.',
            ]);
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        if ($width < 1 || $height < 1 || ($width * $height) > self::MAX_PIXELS) {
            throw new ValidationException([
                'attachments' => 'Изображение слишком большое: максимум 40 мегапикселей.',
            ]);
        }

        return [
            'mime_type' => $detectedMime,
            'extension' => self::MIME_EXTENSIONS[$detectedMime],
            'byte_size' => $size,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Размер изображения превышает лимит загрузки сервера.',
            UPLOAD_ERR_PARTIAL => 'Изображение загрузилось не полностью. Повторите попытку.',
            UPLOAD_ERR_NO_TMP_DIR => 'На сервере отсутствует временная папка для загрузки.',
            UPLOAD_ERR_CANT_WRITE => 'Сервер не смог сохранить загруженное изображение.',
            UPLOAD_ERR_EXTENSION => 'Загрузка изображения остановлена расширением PHP.',
            default => 'Не удалось загрузить изображение. Повторите попытку.',
        };
    }
}
