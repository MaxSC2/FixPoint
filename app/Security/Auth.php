<?php

declare(strict_types=1);

namespace FixPoint\Security;

use FixPoint\Core\Session;
use FixPoint\Repositories\UserRepository;

/**
 * Session-backed current user. Only the public contract shape is returned to
 * controllers/views; password hashes never leave the repository.
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user_id';

    /** @var array{id: int, full_name: string, email: string, role: string}|null */
    private ?array $user = null;

    private bool $resolved = false;

    public function __construct(
        private readonly Session $session,
        private readonly UserRepository $users,
        private readonly Csrf $csrf
    ) {
    }

    /** @return array{id: int, full_name: string, email: string, role: string}|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $id = $this->session->get(self::SESSION_KEY);
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }

        $row = $this->users->findById((int) $id);
        if ($row === null || (int) $row['is_active'] !== 1) {
            $this->session->remove(self::SESSION_KEY);
            $this->csrf->rotate();

            return null;
        }

        $this->user = [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'email' => (string) $row['email'],
            'role' => (string) $row['role'],
        ];

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function role(): ?string
    {
        return $this->user()['role'] ?? null;
    }

    public function hasRole(string ...$roles): bool
    {
        $role = $this->role();

        return $role !== null && in_array($role, $roles, true);
    }

    public function login(int $userId): void
    {
        if ($userId < 1) {
            throw new \InvalidArgumentException('Invalid user id.');
        }

        // Session fixation protection: issue a fresh id on privilege change.
        $this->session->regenerate(true);
        $this->session->set(self::SESSION_KEY, $userId);
        $this->csrf->rotate();
        $this->user = null;
        $this->resolved = false;
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->regenerate(true);
        $this->csrf->rotate();
        $this->user = null;
        $this->resolved = true;
    }
}
