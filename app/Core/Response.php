<?php

declare(strict_types=1);

namespace FixPoint\Core;

final class Response
{
    /** @param array<string, string> $headers */
    private function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly string $body
    ) {
    }

    /** @param array<string, string> $headers */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, array_merge([
            'Content-Type' => 'text/html; charset=UTF-8',
        ], $headers), $body);
    }

    /** @param array<string, mixed> $data
     *  @param array<string, string> $headers */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $body = '{"error":"encoding_error"}';
            $status = 500;
        }

        return new self($status, array_merge([
            'Content-Type' => 'application/json; charset=UTF-8',
        ], $headers), $body);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/plain; charset=UTF-8'], $body);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        if ($status < 300 || $status > 399) {
            throw new \InvalidArgumentException('Redirect status must be 3xx.');
        }

        return new self($status, ['Location' => $location], '');
    }

    public static function noContent(): self
    {
        return new self(204, [], '');
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->status, $headers, $this->body);
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, array_merge($this->headers, $headers), $this->body);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(bool $includeBody = true): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header_remove('X-Powered-By');
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        if ($includeBody) {
            echo $this->body;
        }
    }
}
