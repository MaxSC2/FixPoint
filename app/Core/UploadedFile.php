<?php

declare(strict_types=1);

namespace FixPoint\Core;

use RuntimeException;

/**
 * A normalized PHP upload. The only production constructor is the one that
 * verifies the temporary path is a genuine HTTP upload.
 */
final class UploadedFile
{
    private function __construct(
        private readonly string $temporaryPath,
        private readonly string $originalName,
        private readonly int $errorCode,
        private readonly bool $httpUpload,
        private readonly bool $testFixture = false
    ) {
    }

    /** @param array<string, mixed> $file */
    public static function fromPhpArray(array $file): self
    {
        $temporaryPath = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
        $originalName = is_string($file['name'] ?? null) ? $file['name'] : '';
        $errorCode = filter_var($file['error'] ?? UPLOAD_ERR_NO_FILE, FILTER_VALIDATE_INT);

        if ($errorCode === false) {
            $errorCode = UPLOAD_ERR_NO_FILE;
        }

        return new self(
            $temporaryPath,
            $originalName,
            (int) $errorCode,
            $errorCode === UPLOAD_ERR_OK && $temporaryPath !== '' && is_uploaded_file($temporaryPath)
        );
    }

    /**
     * Test-only factory. It is unavailable outside APP_ENV=testing and never
     * changes the production upload validation path.
     */
    public static function forTesting(string $path, string $originalName): self
    {
        if (Env::get('APP_ENV', '') !== 'testing' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Test upload fixtures are available only in the testing environment.');
        }

        return new self($path, $originalName, UPLOAD_ERR_OK, false, true);
    }

    public function errorCode(): int
    {
        return $this->errorCode;
    }

    public function originalName(): string
    {
        $name = str_replace('\\', '/', $this->originalName);
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return trim($name);
    }

    public function temporaryPath(): string
    {
        return $this->temporaryPath;
    }

    public function actualSize(): int
    {
        $size = is_file($this->temporaryPath) ? filesize($this->temporaryPath) : false;

        return $size === false ? 0 : (int) $size;
    }

    public function isAvailable(): bool
    {
        return $this->errorCode === UPLOAD_ERR_OK
            && $this->temporaryPath !== ''
            && is_file($this->temporaryPath)
            && is_readable($this->temporaryPath)
            && ($this->httpUpload || $this->testFixture);
    }

    public function moveTo(string $destination): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('Uploaded file is not available.');
        }

        if (file_exists($destination)) {
            throw new RuntimeException('Upload destination already exists.');
        }

        $moved = $this->testFixture
            ? @copy($this->temporaryPath, $destination)
            : @move_uploaded_file($this->temporaryPath, $destination);

        if (!$moved) {
            throw new RuntimeException('Unable to move uploaded file into private storage.');
        }

        @chmod($destination, 0640);
    }
}
