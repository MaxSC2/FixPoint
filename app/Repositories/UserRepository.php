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

    /** @return list<array<string, mixed>> */
    public function listAllForAdmin(): array
    {
        return $this->database->connection()
            ->query(
                'SELECT id, full_name, email, phone, role, is_active, created_at
                 FROM users
                 ORDER BY created_at DESC, id DESC'
            )
            ->fetchAll();
    }

    public function createWithRole(
        string $fullName,
        string $email,
        ?string $phone,
        string $passwordHash,
        string $role,
        bool $isActive
    ): int {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, is_active)
             VALUES (:full_name, :email, :phone, :password_hash, :role, :is_active)'
        );
        $statement->execute([
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $passwordHash,
            'role' => $role,
            'is_active' => $isActive ? 1 : 0,
        ]);

        return (int) $this->database->connection()->lastInsertId();
    }

    public function updateProfile(
        int $id,
        string $fullName,
        string $email,
        ?string $phone,
        string $role,
        bool $isActive
    ): void {
        $statement = $this->database->connection()->prepare(
            'UPDATE users
             SET full_name = :full_name,
                 email = :email,
                 phone = :phone,
                 role = :role,
                 is_active = :is_active
             WHERE id = :id'
        );
        $statement->execute([
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'is_active' => $isActive ? 1 : 0,
            'id' => $id,
        ]);
    }

    /**
     * Active administrators other than the given user. Used before demotion or
     * deactivation so the system can never lose its last admin account.
     */
    public function countActiveAdminsExcluding(int $id): int
    {
        $statement = $this->database->connection()->prepare(
            "SELECT COUNT(*) FROM users
             WHERE role = 'admin' AND is_active = 1 AND id <> :id"
        );
        $statement->execute(['id' => $id]);

        return (int) $statement->fetchColumn();
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
