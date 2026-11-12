<?php

declare(strict_types=1);

namespace FixPoint\Core;

use RuntimeException;

/**
 * Expected HTTP failure. The message is for developers; HTTP responses render
 * a neutral page and never expose it.
 */
final class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $statusCode,
        string $message = '',
        public readonly array $headers = []
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self(403, $message);
    }

    public static function csrf(): self
    {
        return new self(419, 'CSRF token is missing or invalid');
    }

    /** @param list<string> $allowedMethods */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        $allowed = array_values(array_unique($allowedMethods));
        if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        return new self(405, 'Method not allowed', ['Allow' => implode(', ', $allowed)]);
    }
}
