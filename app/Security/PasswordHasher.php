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

    /**
     * Shared password policy: at least 8 characters, at most 72 bytes, and at
     * least one letter and one digit.
     *
     * @return string|null the error message, or null when the password is fine
     */
    public static function policyError(string $password): ?string
    {
        if (mb_strlen($password) < 8) {
            return 'Пароль должен быть не короче 8 символов.';
        }
        if (strlen($password) > 72) {
            return 'Пароль слишком длинный.';
        }
        if (preg_match('/\p{L}/u', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            return 'Пароль должен содержать хотя бы одну букву и одну цифру.';
        }

        return null;
    }
}
