<?php

declare(strict_types=1);

namespace FixPoint\Security;

use FixPoint\Core\Session;

/**
 * Per-session CSRF token. Tokens are compared in constant time and rotate on
 * login/logout (see Auth).
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $expected = $this->session->get(self::SESSION_KEY);
        if (!is_string($expected) || strlen($expected) !== 64) {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public function rotate(): string
    {
        $this->session->remove(self::SESSION_KEY);

        return $this->token();
    }
}
