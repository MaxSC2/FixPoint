<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;

/** MySQL-backed editable public content blocks. */
final class SiteContentRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByKey(string $key): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT block_key, title, lead, section_heading, section_body, updated_by, updated_at
             FROM site_content_blocks
             WHERE block_key = :block_key
             LIMIT 1'
        );
        $statement->execute(['block_key' => $key]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array{title: string, lead: string, section_heading: string, section_body: string} $content
     */
    public function updateByKey(string $key, array $content, int $updatedBy): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE site_content_blocks
             SET title = :title,
                 lead = :lead,
                 section_heading = :section_heading,
                 section_body = :section_body,
                 updated_by = :updated_by
             WHERE block_key = :block_key'
        );
        $statement->execute([
            'title' => $content['title'],
            'lead' => $content['lead'],
            'section_heading' => $content['section_heading'],
            'section_body' => $content['section_body'],
            'updated_by' => $updatedBy,
            'block_key' => $key,
        ]);
    }
}
