<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Repositories\UserRepository;
use FixPoint\Security\Auth;
use FixPoint\Security\PasswordHasher;

/**
 * Credential verification primitive for TASK-002. No HTTP routes are exposed
 * in the foundation; the service is covered by integration tests.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly Auth $auth
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || (int) $user['is_active'] !== 1) {
            return false;
        }

        if (!$this->hasher->verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if ($this->hasher->needsRehash((string) $user['password_hash'])) {
            $this->users->updatePasswordHash((int) $user['id'], $this->hasher->hash($password));
        }

        $this->auth->login((int) $user['id']);

        return true;
    }

    public function logout(): void
    {
        $this->auth->logout();
    }
}
