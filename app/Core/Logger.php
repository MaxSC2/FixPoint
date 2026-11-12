<?php

declare(strict_types=1);

namespace FixPoint\Core;

/**
 * Small append-only logger. Application errors go to a file outside the
 * public document root; exception details never reach HTTP responses.
 */
final class Logger
{
    public function __construct(private readonly string $path)
    {
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $line = sprintf(
            "[%s] %s %s%s\n",
            date('c'),
            $level,
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
        );

        if (@file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(trim($line));
        }
    }
}
