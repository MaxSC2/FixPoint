<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;

/**
 * Read model for the public service catalogue. Every value is bound through
 * PDO; only validated integers are interpolated for LIMIT/OFFSET.
 */
final class ServiceRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function countActive(string $query = '', string $categorySlug = ''): int
    {
        [$where, $params] = $this->buildFilter($query, $categorySlug);
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*)
             FROM services s
             INNER JOIN service_categories c ON c.id = s.category_id
             WHERE ' . $where
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function paginateActive(string $query, string $categorySlug, int $limit, int $offset): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->buildFilter($query, $categorySlug);

        $statement = $this->database->connection()->prepare(
            'SELECT s.id, s.name, s.slug, s.short_description, s.price_from, s.estimated_minutes,
                    c.id AS category_id, c.name AS category_name, c.slug AS category_slug
             FROM services s
             INNER JOIN service_categories c ON c.id = s.category_id
             WHERE ' . $where . '
             ORDER BY c.sort_order ASC, s.name ASC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findActiveBySlug(string $slug): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT s.id, s.name, s.slug, s.short_description, s.description,
                    s.price_from, s.estimated_minutes, s.category_id,
                    c.name AS category_name, c.slug AS category_slug
             FROM services s
             INNER JOIN service_categories c ON c.id = s.category_id
             WHERE s.slug = :slug AND s.is_active = 1 AND c.is_active = 1
             LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function popularActive(int $limit): array
    {
        $limit = max(1, min(12, $limit));
        $statement = $this->database->connection()->query(
            'SELECT s.id, s.name, s.slug, s.short_description, s.price_from, s.estimated_minutes,
                    c.name AS category_name, c.slug AS category_slug
             FROM services s
             INNER JOIN service_categories c ON c.id = s.category_id
             WHERE s.is_active = 1 AND c.is_active = 1
             ORDER BY c.sort_order ASC, s.name ASC
             LIMIT ' . $limit
        );

        return $statement->fetchAll();
    }

    public function countAllActive(): int
    {
        return (int) $this->database->connection()
            ->query(
                'SELECT COUNT(*)
                 FROM services s
                 INNER JOIN service_categories c ON c.id = s.category_id
                 WHERE s.is_active = 1 AND c.is_active = 1'
            )
            ->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listActiveForSelect(): array
    {
        return $this->database->connection()->query(
            'SELECT s.id, s.name
             FROM services s
             INNER JOIN service_categories c ON c.id = s.category_id
             WHERE s.is_active = 1 AND c.is_active = 1
             ORDER BY c.sort_order ASC, s.name ASC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findActiveById(int $id): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT s.id, s.name, s.slug
             FROM services s
             INNER JOIN service_categories c ON c.id = s.category_id
             WHERE s.id = :id AND s.is_active = 1 AND c.is_active = 1
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function listAllForAdmin(): array
    {
        return $this->database->connection()->query(
            'SELECT s.id, s.category_id, s.name, s.slug, s.short_description, s.description,
                    s.price_from, s.estimated_minutes, s.is_active,
                    c.name AS category_name, c.slug AS category_slug
             FROM services s
             INNER JOIN service_categories c ON c.id = s.category_id
             ORDER BY c.sort_order ASC, c.name ASC, s.name ASC, s.id ASC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findByIdForAdmin(int $id): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT s.id, s.category_id, s.name, s.slug, s.short_description, s.description,
                    s.price_from, s.estimated_minutes, s.is_active,
                    c.name AS category_name, c.slug AS category_slug
             FROM services s
             INNER JOIN service_categories c ON c.id = s.category_id
             WHERE s.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM services WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function createForAdmin(
        int $categoryId,
        string $name,
        string $slug,
        ?string $shortDescription,
        ?string $description,
        ?string $priceFrom,
        ?int $estimatedMinutes,
        bool $isActive
    ): int {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO services
                (category_id, name, slug, short_description, description,
                 price_from, estimated_minutes, is_active)
             VALUES
                (:category_id, :name, :slug, :short_description, :description,
                 :price_from, :estimated_minutes, :is_active)'
        );
        $statement->execute([
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'short_description' => $shortDescription,
            'description' => $description,
            'price_from' => $priceFrom,
            'estimated_minutes' => $estimatedMinutes,
            'is_active' => $isActive ? 1 : 0,
        ]);

        return (int) $this->database->connection()->lastInsertId();
    }

    public function updateForAdmin(
        int $id,
        int $categoryId,
        string $name,
        string $slug,
        ?string $shortDescription,
        ?string $description,
        ?string $priceFrom,
        ?int $estimatedMinutes,
        bool $isActive
    ): void {
        $statement = $this->database->connection()->prepare(
            'UPDATE services
             SET category_id = :category_id,
                 name = :name,
                 slug = :slug,
                 short_description = :short_description,
                 description = :description,
                 price_from = :price_from,
                 estimated_minutes = :estimated_minutes,
                 is_active = :is_active
             WHERE id = :id'
        );
        $statement->execute([
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'short_description' => $shortDescription,
            'description' => $description,
            'price_from' => $priceFrom,
            'estimated_minutes' => $estimatedMinutes,
            'is_active' => $isActive ? 1 : 0,
            'id' => $id,
        ]);
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildFilter(string $query, string $categorySlug): array
    {
        $where = ['s.is_active = 1', 'c.is_active = 1'];
        $params = [];

        $query = trim($query);
        if ($query !== '') {
            $where[] = '(s.name LIKE :q_name OR s.short_description LIKE :q_short OR c.name LIKE :q_category)';
            $like = '%' . $query . '%';
            $params['q_name'] = $like;
            $params['q_short'] = $like;
            $params['q_category'] = $like;
        }

        if ($categorySlug !== '') {
            $where[] = 'c.slug = :category_slug';
            $params['category_slug'] = $categorySlug;
        }

        return [implode(' AND ', $where), $params];
    }
}
