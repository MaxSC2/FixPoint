<?php

declare(strict_types=1);

namespace FixPoint\Core;

use DateTimeZone;
use InvalidArgumentException;

/**
 * Immutable application configuration built from environment variables.
 */
final class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values)
    {
    }

    public static function fromEnvironment(string $basePath): self
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $environment = strtolower(Env::get('APP_ENV', 'local') ?? 'local');
        if (!in_array($environment, ['local', 'testing', 'staging', 'production'], true)) {
            throw new InvalidArgumentException('APP_ENV must be one of: local, testing, staging, production.');
        }

        $timezone = Env::get('APP_TIMEZONE', 'UTC') ?? 'UTC';
        try {
            new DateTimeZone($timezone);
        } catch (\Throwable) {
            throw new InvalidArgumentException('APP_TIMEZONE is not a valid timezone identifier.');
        }

        $database = Env::get('DB_DATABASE', 'fixpoint') ?? 'fixpoint';
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new InvalidArgumentException('DB_DATABASE may contain only letters, digits and underscores.');
        }

        $appUrl = rtrim(Env::get('APP_URL', 'http://localhost:8080') ?? 'http://localhost:8080', '/');
        if (filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('APP_URL must be a valid absolute URL.');
        }

        $secureSession = Env::bool('SESSION_SECURE', str_starts_with($appUrl, 'https://'));
        $sameSite = Env::get('SESSION_SAMESITE', 'Lax') ?? 'Lax';
        $sameSite = ucfirst(strtolower($sameSite));
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new InvalidArgumentException('SESSION_SAMESITE must be Lax, Strict or None.');
        }
        if ($sameSite === 'None' && !$secureSession) {
            throw new InvalidArgumentException('SESSION_SAMESITE=None requires SESSION_SECURE=true.');
        }

        $debugDefault = in_array($environment, ['local', 'testing'], true);
        $perPage = Env::int('CATALOG_PER_PAGE', 10);
        if ($perPage < 1 || $perPage > 50) {
            throw new InvalidArgumentException('CATALOG_PER_PAGE must be between 1 and 50.');
        }

        return new self([
            'app' => [
                'name' => Env::get('APP_NAME', 'FixPoint') ?: 'FixPoint',
                'env' => $environment,
                'debug' => Env::bool('APP_DEBUG', $debugDefault),
                'url' => $appUrl,
                'timezone' => $timezone,
            ],
            'database' => [
                'host' => Env::get('DB_HOST', '127.0.0.1') ?: '127.0.0.1',
                'port' => Env::int('DB_PORT', 3306),
                'name' => $database,
                'username' => Env::get('DB_USERNAME', 'fixpoint') ?? 'fixpoint',
                'password' => Env::get('DB_PASSWORD', '') ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'session' => [
                'name' => Env::get('SESSION_NAME', 'fixpoint_session') ?: 'fixpoint_session',
                'lifetime' => max(1, Env::int('SESSION_LIFETIME', 120)),
                'secure' => $secureSession,
                'samesite' => $sameSite,
                'save_path' => $basePath . '/storage/sessions',
            ],
            'logging' => [
                'path' => $basePath . '/storage/logs/app.log',
            ],
            'catalog' => [
                'per_page' => $perPage,
            ],
            'paths' => [
                'base' => $basePath,
                'views' => $basePath . '/app/Views',
                'fallback_views' => $basePath . '/app/Support/views',
                'migrations' => $basePath . '/database/migrations',
                'seed_data' => $basePath . '/database/seeds/local_data.php',
            ],
        ]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->values;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    public function isProduction(): bool
    {
        return $this->get('app.env') === 'production';
    }

    public function isLocal(): bool
    {
        return in_array($this->get('app.env'), ['local', 'testing'], true);
    }
}
