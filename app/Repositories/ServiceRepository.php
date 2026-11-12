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
            ->query('SELECT COUNT(*) FROM services s INNER JOIN service_categories c ON c.id = s.category_id WHERE s.is_active = 1 AND c.is_active = 1')
            ->fetchColumn();
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
