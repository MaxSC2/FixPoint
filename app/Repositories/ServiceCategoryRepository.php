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

    public function countActive(): int
    {
        return (int) $this->database->connection()
            ->query('SELECT COUNT(*) FROM service_categories WHERE is_active = 1')
            ->fetchColumn();
    }
}
