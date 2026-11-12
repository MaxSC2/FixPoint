<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;

/**
 * All SQL for users lives here; controllers and views never touch PDO.
 */
final class UserRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, full_name, email, phone, password_hash, role, is_active, created_at, updated_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, full_name, email, phone, password_hash, role, is_active, created_at, updated_at
             FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE users SET password_hash = :hash WHERE id = :id'
        );
        $statement->execute(['hash' => $hash, 'id' => $id]);
    }
}
