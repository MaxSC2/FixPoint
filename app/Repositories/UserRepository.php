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

    public function create(string $fullName, string $email, ?string $phone, string $passwordHash): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, is_active)
             VALUES (:full_name, :email, :phone, :password_hash, :role, 1)'
        );
        $statement->execute([
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $passwordHash,
            'role' => 'customer',
        ]);

        return (int) $this->database->connection()->lastInsertId();
    }

    public function countAll(): int
    {
        return (int) $this->database->connection()
            ->query('SELECT COUNT(*) FROM users')
            ->fetchColumn();
    }

    public function countByRole(string $role): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM users WHERE role = :role AND is_active = 1'
        );
        $statement->execute(['role' => $role]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listByRole(string $role, bool $activeOnly = true): array
    {
        $sql = 'SELECT id, full_name, email, phone, role, is_active, created_at
                FROM users WHERE role = :role';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY full_name ASC';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute(['role' => $role]);

        return $statement->fetchAll();
    }
}
