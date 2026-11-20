<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;

final class RepairGuideRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listPublished(string $query, string $category, int $limit, int $offset): array
    {
        [$where, $params] = $this->publicFilter($query, $category);
        $limit = max(1, min(30, $limit));
        $offset = max(0, $offset);
        $statement = $this->database->connection()->prepare(
            'SELECT id, title, slug, category_slug, device_type, summary, symptoms,
                    possible_causes, status, view_count, published_at, created_at, updated_at
             FROM repair_guides
             WHERE ' . $where . '
             ORDER BY published_at DESC, id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function countPublished(string $query, string $category): int
    {
        [$where, $params] = $this->publicFilter($query, $category);
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM repair_guides WHERE ' . $where
        );
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlugForRequest(string $slug): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, title, slug, summary, device_type
             FROM repair_guides WHERE slug = :slug AND status = "published" LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE repair_guides SET view_count = view_count + 1
             WHERE slug = :slug AND status = "published"'
        );
        $statement->execute(['slug' => $slug]);
        if ($statement->rowCount() < 1) {
            return null;
        }

        $select = $this->database->connection()->prepare(
            'SELECT * FROM repair_guides WHERE slug = :slug AND status = "published" LIMIT 1'
        );
        $select->execute(['slug' => $slug]);
        $row = $select->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function listForAdmin(): array
    {
        return $this->database->connection()->query(
            'SELECT id, title, slug, category_slug, device_type, status, view_count,
                    published_at, updated_at
             FROM repair_guides
             ORDER BY FIELD(status, "draft", "published", "archived"), updated_at DESC, id DESC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM repair_guides WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM repair_guides WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);
        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $userId): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO repair_guides
                (title, slug, category_slug, device_type, summary, symptoms, possible_causes,
                 steps_json, do_not, when_to_contact, status, created_by, updated_by, published_at)
             VALUES
                (:title, :slug, :category_slug, :device_type, :summary, :symptoms, :possible_causes,
                 :steps_json, :do_not, :when_to_contact, :status, :created_by, :updated_by,
                 IF(:is_published = 1, CURRENT_TIMESTAMP, NULL))'
        );
        $statement->execute($this->writeParams($data, $userId));
        return (int) $this->database->connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, int $userId): void
    {
        $params = $this->writeParams($data, $userId);
        unset($params['created_by'], $params['is_published']);
        $params['id'] = $id;
        $params['status_timestamp'] = $data['status'];
        $statement = $this->database->connection()->prepare(
            'UPDATE repair_guides
             SET title = :title, slug = :slug, category_slug = :category_slug,
                 device_type = :device_type, summary = :summary, symptoms = :symptoms,
                 possible_causes = :possible_causes, steps_json = :steps_json,
                 do_not = :do_not, when_to_contact = :when_to_contact,
                 status = :status,
                 published_at = CASE
                    WHEN :status_timestamp = "published" THEN COALESCE(published_at, CURRENT_TIMESTAMP)
                    ELSE published_at
                 END,
                 updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute($params);
    }

    public function archive(int $id, int $userId): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE repair_guides SET status = "archived", updated_by = :updated_by WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'updated_by' => $userId]);
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function publicFilter(string $query, string $category): array
    {
        $conditions = ['status = "published"'];
        $params = [];
        if ($query !== '') {
            $conditions[] = '(title LIKE :term_title OR summary LIKE :term_summary OR symptoms LIKE :term_symptoms
                OR possible_causes LIKE :term_causes OR device_type LIKE :term_device)';
            $term = '%' . $query . '%';
            $params = [
                'term_title' => $term,
                'term_summary' => $term,
                'term_symptoms' => $term,
                'term_causes' => $term,
                'term_device' => $term,
            ];
        }
        if ($category !== '') {
            $conditions[] = 'category_slug = :category';
            $params['category'] = $category;
        }
        return [implode(' AND ', $conditions), $params];
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed> */
    private function writeParams(array $data, int $userId): array
    {
        return [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'category_slug' => $data['category_slug'],
            'device_type' => $data['device_type'],
            'summary' => $data['summary'],
            'symptoms' => $data['symptoms'],
            'possible_causes' => $data['possible_causes'],
            'steps_json' => json_encode($data['steps'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'do_not' => $data['do_not'],
            'when_to_contact' => $data['when_to_contact'],
            'status' => $data['status'],
            'created_by' => $userId,
            'updated_by' => $userId,
            'is_published' => $data['status'] === 'published' ? 1 : 0,
        ];
    }
}
