<?php

declare(strict_types=1);

namespace FixPoint\Core;

/**
 * Immutable HTTP request value object. Controllers never read superglobals
 * directly, which keeps dispatch testable without a web server.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $cookies
     * @param array<string, mixed> $server
     * @param array<string, string> $headers
     * @param array<string, list<UploadedFile>> $files
     */
    private function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $query,
        private readonly array $post,
        private readonly array $cookies,
        private readonly array $server,
        private readonly array $headers,
        private readonly array $files
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = (string) $value;
            }
        }
        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $key => $name) {
            if (isset($_SERVER[$key])) {
                $headers[$name] = (string) $_SERVER[$key];
            }
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            $_GET,
            $_POST,
            $_COOKIE,
            $_SERVER,
            $headers,
            self::normaliseFiles($_FILES)
        );
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $query
     * @param array<string, mixed> $server
     * @param array<string, string> $headers
     * @param array<string, mixed> $files
     */
    public static function create(
        string $method,
        string $uri,
        array $post = [],
        array $query = [],
        array $server = [],
        array $headers = [],
        array $files = []
    ): self {
        if ($query === []) {
            $queryString = parse_url($uri, PHP_URL_QUERY);
            if (is_string($queryString) && $queryString !== '') {
                parse_str($queryString, $query);
            }
        }

        $server = array_merge([
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
            'HTTP_HOST' => 'localhost',
            'REMOTE_ADDR' => '127.0.0.1',
        ], $server);

        return new self(
            strtoupper($method),
            $uri,
            $query,
            $post,
            [],
            $server,
            $headers,
            self::normaliseFiles($files)
        );
    }

    /**
     * Uploaded files for one input name. Invalid PHP upload errors are retained
     * so the validation layer can return a useful field error.
     *
     * @return list<UploadedFile>
     */
    public function files(string $key): array
    {
        return $this->files[$key] ?? [];
    }

    /** @return array<string, list<UploadedFile>> */
    public function allFiles(): array
    {
        return $this->files;
    }

    /**
     * Convert PHP's scalar and multiple-file $_FILES formats into value objects.
     *
     * @param array<string, mixed> $files
     * @return array<string, list<UploadedFile>>
     */
    private static function normaliseFiles(array $files): array
    {
        $normalised = [];

        foreach ($files as $field => $payload) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            $items = [];
            if ($payload instanceof UploadedFile) {
                $items[] = $payload;
            } elseif (is_array($payload) && isset($payload['tmp_name'])) {
                if (is_array($payload['tmp_name'])) {
                    foreach (array_keys($payload['tmp_name']) as $index) {
                        $single = [];
                        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
                            $value = $payload[$key] ?? null;
                            $single[$key] = is_array($value) ? ($value[$index] ?? null) : $value;
                        }
                        $items[] = UploadedFile::fromPhpArray($single);
                    }
                } else {
                    $items[] = UploadedFile::fromPhpArray($payload);
                }
            } elseif (is_array($payload)) {
                foreach ($payload as $item) {
                    if ($item instanceof UploadedFile) {
                        $items[] = $item;
                    } elseif (is_array($item)) {
                        $items[] = UploadedFile::fromPhpArray($item);
                    }
                }
            }

            $items = array_values(array_filter(
                $items,
                static fn (UploadedFile $file): bool => $file->errorCode() !== UPLOAD_ERR_NO_FILE
            ));
            if ($items !== []) {
                $normalised[$field] = $items;
            }
        }

        return $normalised;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        $path = rawurldecode($path);
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function queryString(): string
    {
        $query = parse_url($this->uri, PHP_URL_QUERY);

        return is_string($query) ? $query : '';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function allPost(): array
    {
        return $this->post;
    }

    /** @return array<string, mixed> */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $needle = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $needle) {
                return $value;
            }
        }

        return $default;
    }

    public function expectsJson(): bool
    {
        $accept = strtolower($this->header('Accept', '') ?? '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        return strtolower($this->header('X-Requested-With', '') ?? '') === 'xmlhttprequest';
    }

    public function isSecure(): bool
    {
        $https = strtolower((string) $this->server('HTTPS', ''));

        return ($https !== '' && $https !== 'off') || (int) $this->server('SERVER_PORT', 0) === 443;
    }

    public function ip(): string
    {
        return (string) $this->server('REMOTE_ADDR', '0.0.0.0');
    }

    public function referer(): ?string
    {
        $value = $this->header('Referer');

        return $value === '' ? null : $value;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }
}
