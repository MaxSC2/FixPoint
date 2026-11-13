<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;

final class ServiceCategoryRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function allActive(): array
    {
        $statement = $this->database->connection()->query(
            'SELECT id, name, slug, description, sort_order
             FROM service_categories
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC'
        );

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listAllForAdmin(): array
    {
        return $this->database->connection()->query(
            'SELECT id, name, slug, description, sort_order, is_active
             FROM service_categories
             ORDER BY sort_order ASC, name ASC, id ASC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, name, slug, description, sort_order, is_active
             FROM service_categories
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM service_categories WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(
        string $name,
        string $slug,
        ?string $description,
        int $sortOrder,
        bool $isActive
    ): int {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO service_categories (name, slug, description, sort_order, is_active)
             VALUES (:name, :slug, :description, :sort_order, :is_active)'
        );
        $statement->execute([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'sort_order' => $sortOrder,
            'is_active' => $isActive ? 1 : 0,
        ]);

        return (int) $this->database->connection()->lastInsertId();
    }

    public function update(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        int $sortOrder,
        bool $isActive
    ): void {
        $statement = $this->database->connection()->prepare(
            'UPDATE service_categories
             SET name = :name,
                 slug = :slug,
                 description = :description,
                 sort_order = :sort_order,
                 is_active = :is_active
             WHERE id = :id'
        );
        $statement->execute([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'sort_order' => $sortOrder,
            'is_active' => $isActive ? 1 : 0,
            'id' => $id,
        ]);
    }

    public function countActive(): int
    {
        return (int) $this->database->connection()
            ->query('SELECT COUNT(*) FROM service_categories WHERE is_active = 1')
            ->fetchColumn();
    }
}
