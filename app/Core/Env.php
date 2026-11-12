<?php

declare(strict_types=1);

namespace FixPoint\Core;

/**
 * Minimal .env loader.
 *
 * Values already present in the process environment always win, so Docker,
 * CI and production configuration is never overwritten by a stale .env file.
 * The parser supports comments, blank lines, "export KEY=VALUE", single and
 * double quoted values. It does not evaluate PHP or interpolate variables.
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $position = strpos($line, '=');
            if ($position === false) {
                continue;
            }

            $key = trim(substr($line, 0, $position));
            if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }

            $value = self::normaliseValue(trim(substr($line, $position + 1)));

            if (getenv($key) !== false) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER) && is_scalar($_SERVER[$key])) {
            return (string) $_SERVER[$key];
        }

        return $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_INT) === false ? $default : (int) $value;
    }

    private static function normaliseValue(string $value): string
    {
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            if (strlen($value) >= 2 && str_ends_with($value, $quote)) {
                $inner = substr($value, 1, -1);
                if ($quote === '"') {
                    return str_replace(['\\n', '\\r', '\\t', '\\"'], ["\n", "\r", "\t", '"'], $inner);
                }

                return $inner;
            }
        }

        $comment = strpos($value, ' #');
        if ($comment !== false) {
            $value = substr($value, 0, $comment);
        }

        return trim($value);
    }
}
