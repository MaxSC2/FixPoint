<?php

declare(strict_types=1);

namespace FixPoint\Core;

use RuntimeException;

/**
 * Domain validation failure carrying field errors. Controllers translate it
 * into a redirect-back with flashed errors; it must never leak to HTTP as 500.
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validation failed.');
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
