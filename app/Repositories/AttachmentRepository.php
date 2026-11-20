<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;
use PDO;

final class AttachmentRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @param array<string, mixed> $data */
    public function insert(PDO $pdo, array $data): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO request_attachments
                (request_id, message_id, uploader_id, storage_key, original_name,
                 mime_type, byte_size, width, height)
             VALUES
                (:request_id, :message_id, :uploader_id, :storage_key, :original_name,
                 :mime_type, :byte_size, :width, :height)'
        );
        $statement->execute([
            'request_id' => $data['request_id'],
            'message_id' => $data['message_id'],
            'uploader_id' => $data['uploader_id'],
            'storage_key' => $data['storage_key'],
            'original_name' => $data['original_name'],
            'mime_type' => $data['mime_type'],
            'byte_size' => $data['byte_size'],
            'width' => $data['width'],
            'height' => $data['height'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function countForRequest(int $requestId): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM request_attachments WHERE request_id = :request_id'
        );
        $statement->execute(['request_id' => $requestId]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function requestImages(int $requestId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, request_id, message_id, original_name, mime_type,
                    byte_size, width, height, created_at
             FROM request_attachments
             WHERE request_id = :request_id AND message_id IS NULL
             ORDER BY id ASC'
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    /**
     * @param list<int> $messageIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forMessages(int $requestId, array $messageIds): array
    {
        $messageIds = array_values(array_unique(array_filter($messageIds, static fn (int $id): bool => $id > 0)));
        if ($messageIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $statement = $this->database->connection()->prepare(
            'SELECT id, request_id, message_id, original_name, mime_type,
                    byte_size, width, height, created_at
             FROM request_attachments
             WHERE request_id = ? AND message_id IN (' . $placeholders . ')
             ORDER BY id ASC'
        );
        $statement->execute(array_merge([$requestId], $messageIds));

        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $messageId = (int) $row['message_id'];
            $grouped[$messageId][] = $row;
        }

        return $grouped;
    }

    /** @return array<string, mixed>|null */
    /** @return array<string, mixed>|null */
    public function findById(int $attachmentId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, request_id, message_id, uploader_id, storage_key,
                    original_name, mime_type, byte_size, width, height
             FROM request_attachments
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $attachmentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function findForRequest(int $requestId, int $attachmentId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, request_id, message_id, uploader_id, storage_key,
                    original_name, mime_type, byte_size, width, height
             FROM request_attachments
             WHERE request_id = :request_id AND id = :id
             LIMIT 1'
        );
        $statement->execute(['request_id' => $requestId, 'id' => $attachmentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
