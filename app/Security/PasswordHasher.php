<?php

declare(strict_types=1);

namespace FixPoint\Security;

use InvalidArgumentException;

final class PasswordHasher
{
    public function hash(string $password): string
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password must not be empty.');
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verify(string $password, string $hash): bool
    {
        if ($password === '' || $hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
