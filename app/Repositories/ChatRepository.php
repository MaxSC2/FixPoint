<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;
use PDO;

final class ChatRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Return the latest message from each conversation visible to this user.
     *
     * @return list<array<string, mixed>>
     */
    public function listThreads(int $userId, string $role, bool $onlyUnread = false): array
    {
        $scope = match ($role) {
            'customer' => 'r.customer_id = :scope_user_id',
            'technician' => 'r.technician_id = :scope_user_id',
            'admin' => '1 = 1',
            default => '1 = 0',
        };

        $params = [
            'unread_author_id' => $userId,
            'read_user_id' => $userId,
        ];
        if (in_array($role, ['customer', 'technician'], true)) {
            $params['scope_user_id'] = $userId;
        }

        $unreadFilter = '';
        if ($onlyUnread) {
            $unreadFilter = '
                AND EXISTS (
                    SELECT 1
                    FROM request_messages AS unread_filter
                    WHERE unread_filter.request_id = r.id
                      AND unread_filter.author_id <> :filter_author_id
                      AND unread_filter.id > COALESCE(cr.last_read_message_id, 0)
                )';
            $params['filter_author_id'] = $userId;
        }

        $statement = $this->database->connection()->prepare(
            'SELECT
                r.id AS request_id,
                r.public_number,
                r.status,
                r.device_type,
                r.device_brand,
                r.device_model,
                c.full_name AS customer_name,
                t.full_name AS technician_name,
                m.id AS last_message_id,
                m.author_id AS last_author_id,
                m.body AS last_message_body,
                m.created_at AS last_message_at,
                author.full_name AS last_author_name,
                (SELECT COUNT(*)
                 FROM request_messages AS unread
                 WHERE unread.request_id = r.id
                   AND unread.author_id <> :unread_author_id
                   AND unread.id > COALESCE(cr.last_read_message_id, 0)
                ) AS unread_count
             FROM repair_requests AS r
             INNER JOIN request_messages AS m
                ON m.id = (
                    SELECT MAX(latest.id)
                    FROM request_messages AS latest
                    WHERE latest.request_id = r.id
                )
             INNER JOIN users AS c ON c.id = r.customer_id
             LEFT JOIN users AS t ON t.id = r.technician_id
             INNER JOIN users AS author ON author.id = m.author_id
             LEFT JOIN chat_reads AS cr
                ON cr.request_id = r.id AND cr.user_id = :read_user_id
             WHERE ' . $scope . $unreadFilter . '
             ORDER BY m.id DESC
             LIMIT 100'
        );
        $statement->execute($params);

        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['request_id'] = (int) $row['request_id'];
            $row['last_message_id'] = (int) $row['last_message_id'];
            $row['last_author_id'] = (int) $row['last_author_id'];
            $row['unread_count'] = (int) $row['unread_count'];
        }
        unset($row);

        return $rows;
    }

    public function unreadTotal(int $userId, string $role): int
    {
        $scope = match ($role) {
            'customer' => 'r.customer_id = :scope_user_id',
            'technician' => 'r.technician_id = :scope_user_id',
            'admin' => '1 = 1',
            default => '1 = 0',
        };

        $params = [
            'author_id' => $userId,
            'read_user_id' => $userId,
        ];
        if (in_array($role, ['customer', 'technician'], true)) {
            $params['scope_user_id'] = $userId;
        }

        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*)
             FROM request_messages AS m
             INNER JOIN repair_requests AS r ON r.id = m.request_id
             LEFT JOIN chat_reads AS cr
                ON cr.request_id = r.id AND cr.user_id = :read_user_id
             WHERE m.author_id <> :author_id
               AND m.id > COALESCE(cr.last_read_message_id, 0)
               AND ' . $scope
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function messagesForRequest(int $requestId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT m.id, m.author_id, m.body, m.created_at,
                    u.full_name AS author_name, u.role AS author_role
             FROM request_messages AS m
             INNER JOIN users AS u ON u.id = m.author_id
             WHERE m.request_id = :request_id
             ORDER BY m.id ASC'
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll()    /**
     * @param callable(PDO, int): void|null $afterInsert
     */
    public function addMessage(int $requestId, int $authorId, ?string $body, ?callable $afterInsert = null): int
    {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'INSERT INTO request_messages (request_id, author_id, body)
                 VALUES (:request_id, :author_id, :body)'
            );
            $statement->execute([
                'request_id' => $requestId,
                'author_id' => $authorId,
                'body' => $body,
            ]);

            $messageId = (int) $pdo->lastInsertId();
            if ($afterInsert !== null) {
                $afterInsert($pdo, $messageId);
            }

            $pdo->commit();

            return $messageId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
);
    }

    public function markRead(int $requestId, int $userId): void
    {
        $latest = $this->database->connection()->prepare(
            'SELECT COALESCE(MAX(id), 0)
             FROM request_messages
             WHERE request_id = :request_id'
        );
        $latest->execute(['request_id' => $requestId]);
        $lastMessageId = (int) $latest->fetchColumn();
        if ($lastMessageId < 1) {
            return;
        }

        $statement = $this->database->connection()->prepare(
            'INSERT INTO chat_reads (request_id, user_id, last_read_message_id)
             VALUES (:request_id, :user_id, :last_read_message_id)
             ON DUPLICATE KEY UPDATE
                last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id)),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'request_id' => $requestId,
            'user_id' => $userId,
            'last_read_message_id' => $lastMessageId,
        ]);
    }
}
