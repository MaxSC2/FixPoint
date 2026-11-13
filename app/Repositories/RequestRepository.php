<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;
use Throwable;

/**
 * Read/write model for repair requests. Every scoped query filters explicitly:
 * customer queries by customer_id and technician queries by technician_id, so
 * a controller cannot accidentally widen access through a list method.
 */
final class RequestRepository
{
    private const SELECT = 'SELECT
            r.id, r.public_number, r.status, r.priority,
            r.device_type, r.device_brand, r.device_model,
            r.problem_description, r.diagnostic_result,
            r.estimated_cost, r.final_cost, r.estimated_ready_at,
            r.customer_contact, r.created_at, r.updated_at, r.completed_at,
            c.id AS customer_id, c.full_name AS customer_name,
            t.id AS technician_id, t.full_name AS technician_name,
            s.id AS service_id, s.name AS service_name, s.slug AS service_slug,
            cat.name AS category_name
        FROM repair_requests r
        INNER JOIN users c ON c.id = r.customer_id
        LEFT JOIN users t ON t.id = r.technician_id
        LEFT JOIN services s ON s.id = r.service_id
        LEFT JOIN service_categories cat ON cat.id = s.category_id';

    public function __construct(private readonly Database $database)
    {
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->database->connection()->prepare(
            self::SELECT . ' WHERE r.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function listForCustomer(int $customerId, int $limit = 20, int $offset = 0): array
    {
        $statement = $this->database->connection()->prepare(
            self::SELECT . ' WHERE r.customer_id = :customer_id
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute(['customer_id' => $customerId]);

        return $statement->fetchAll();
    }

    public function countForCustomer(int $customerId): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM repair_requests WHERE customer_id = :customer_id'
        );
        $statement->execute(['customer_id' => $customerId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, int> */
    public function statusCountsForCustomer(int $customerId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT status, COUNT(*) AS total
             FROM repair_requests WHERE customer_id = :customer_id
             GROUP BY status'
        );
        $statement->execute(['customer_id' => $customerId]);

        return $this->statusMap($statement->fetchAll());
    }

    /**
     * Scoped customer list with allowlisted filters. The caller must validate
     * status/sort values before passing them here.
     *
     * @param array{q: string, status: string, sort: string} $filters
     * @return list<array<string, mixed>>
     */
    public function listForCustomerFiltered(int $customerId, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->customerFilter($customerId, $filters);
        $order = $this->customerSort((string) ($filters['sort'] ?? 'newest'));

        $statement = $this->database->connection()->prepare(
            self::SELECT . ' WHERE ' . $where . ' ORDER BY ' . $order . '
             LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @param array{q: string, status: string, sort: string} $filters */
    public function countForCustomerFiltered(int $customerId, array $filters): int
    {
        [$where, $params] = $this->customerFilter($customerId, $filters);
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*)
             FROM repair_requests r
             LEFT JOIN services s ON s.id = r.service_id
             WHERE ' . $where
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * Insert a request and its first history entry atomically. The temporary
     * public number is replaced with an id-based number before commit, so the
     * unique index never exposes a race between concurrent inserts.
     *
     * @param array{device_type: string, device_brand: ?string, device_model: ?string, service_id: ?int, problem_description: string, customer_contact: string} $data
     */
    public function createForCustomer(int $customerId, array $data): int
    {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();

        try {
            $temporary = 'TMP-' . bin2hex(random_bytes(8));
            $statement = $pdo->prepare(
                'INSERT INTO repair_requests
                    (public_number, customer_id, service_id, status, priority,
                     device_type, device_brand, device_model, problem_description, customer_contact)
                 VALUES
                    (:public_number, :customer_id, :service_id, \'new\', \'normal\',
                     :device_type, :device_brand, :device_model, :problem_description, :customer_contact)'
            );
            $statement->execute([
                'public_number' => $temporary,
                'customer_id' => $customerId,
                'service_id' => $data['service_id'],
                'device_type' => $data['device_type'],
                'device_brand' => $data['device_brand'],
                'device_model' => $data['device_model'],
                'problem_description' => $data['problem_description'],
                'customer_contact' => $data['customer_contact'],
            ]);

            $id = (int) $pdo->lastInsertId();
            $publicNumber = sprintf('FP-%s-%06d', date('Y'), $id);

            $pdo->prepare('UPDATE repair_requests SET public_number = :public_number WHERE id = :id')
                ->execute(['public_number' => $publicNumber, 'id' => $id]);

            $pdo->prepare(
                'INSERT INTO request_status_history
                    (request_id, from_status, to_status, reason, changed_by)
                 VALUES (:request_id, NULL, \'new\', :reason, :changed_by)'
            )->execute([
                'request_id' => $id,
                'reason' => 'Заявка создана клиентом.',
                'changed_by' => $customerId,
            ]);

            $pdo->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function commentsForRequest(int $requestId, bool $includeInternal = false): array
    {
        $sql = 'SELECT rc.id, rc.body, rc.is_internal, rc.created_at,
                       u.full_name AS author_name, u.role AS author_role
                FROM request_comments rc
                INNER JOIN users u ON u.id = rc.author_id
                WHERE rc.request_id = :request_id';
        if (!$includeInternal) {
            $sql .= ' AND rc.is_internal = 0';
        }
        $sql .= ' ORDER BY rc.created_at ASC, rc.id ASC';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function historyForRequest(int $requestId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT h.id, h.from_status, h.to_status, h.reason, h.created_at AS changed_at,
                    u.full_name AS changed_by_name
             FROM request_status_history h
             INNER JOIN users u ON u.id = h.changed_by
             WHERE h.request_id = :request_id
             ORDER BY h.created_at ASC, h.id ASC'
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    public function addComment(int $requestId, int $authorId, string $body, bool $isInternal = false): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO request_comments (request_id, author_id, body, is_internal)
             VALUES (:request_id, :author_id, :body, :is_internal)'
        );
        $statement->execute([
            'request_id' => $requestId,
            'author_id' => $authorId,
            'body' => $body,
            'is_internal' => $isInternal ? 1 : 0,
        ]);

        return (int) $this->database->connection()->lastInsertId();
    }

    /**
     * Apply a status change and append immutable history in one transaction.
     * The conditional UPDATE protects against two actors racing from the same
     * source status; false means the request changed before this call.
     */
    public function transitionStatus(
        int $requestId,
        string $fromStatus,
        string $toStatus,
        int $changedBy,
        ?string $reason = null
    ): bool {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();

        try {
            $sql = 'UPDATE repair_requests SET status = :to_status';
            if ($toStatus === 'completed') {
                $sql .= ', completed_at = UTC_TIMESTAMP()';
            }
            $sql .= ' WHERE id = :id AND status = :from_status';

            $statement = $pdo->prepare($sql);
            $statement->execute([
                'to_status' => $toStatus,
                'id' => $requestId,
                'from_status' => $fromStatus,
            ]);

            if ($statement->rowCount() !== 1) {
                $pdo->rollBack();

                return false;
            }

            $pdo->prepare(
                'INSERT INTO request_status_history
                    (request_id, from_status, to_status, reason, changed_by)
                 VALUES (:request_id, :from_status, :to_status, :reason, :changed_by)'
            )->execute([
                'request_id' => $requestId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'changed_by' => $changedBy,
            ]);

            $pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForTechnician(int $technicianId, int $limit = 20, int $offset = 0): array
    {
        $statement = $this->database->connection()->prepare(
            self::SELECT . ' WHERE r.technician_id = :technician_id
             ORDER BY FIELD(r.status, \'new\', \'accepted\', \'diagnostics\', \'awaiting_customer_approval\',
                \'awaiting_parts\', \'repairing\', \'ready_for_pickup\', \'completed\', \'cancelled\', \'rejected\'),
                r.created_at DESC, r.id DESC
             LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute(['technician_id' => $technicianId]);

        return $statement->fetchAll();
    }

    public function countForTechnician(int $technicianId): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM repair_requests WHERE technician_id = :technician_id'
        );
        $statement->execute(['technician_id' => $technicianId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, int> */
    public function statusCountsForTechnician(int $technicianId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT status, COUNT(*) AS total
             FROM repair_requests WHERE technician_id = :technician_id
             GROUP BY status'
        );
        $statement->execute(['technician_id' => $technicianId]);

        return $this->statusMap($statement->fetchAll());
    }

    /** @return list<array<string, mixed>> */
    public function listAll(int $limit = 20, int $offset = 0): array
    {
        $statement = $this->database->connection()->prepare(
            self::SELECT . ' ORDER BY r.created_at DESC, r.id DESC
             LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countAll(): int
    {
        return (int) $this->database->connection()
            ->query('SELECT COUNT(*) FROM repair_requests')
            ->fetchColumn();
    }

    /** @return array<string, int> */
    public function statusCountsAll(): array
    {
        $rows = $this->database->connection()
            ->query('SELECT status, COUNT(*) AS total FROM repair_requests GROUP BY status')
            ->fetchAll();

        return $this->statusMap($rows);
    }

    public function countUnassigned(): int
    {
        return (int) $this->database->connection()
            ->query('SELECT COUNT(*) FROM repair_requests WHERE technician_id IS NULL')
            ->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $limit = 8): array
    {
        return $this->listAll($limit, 0);
    }

    /**
     * @param array{q: string, status: string, sort: string} $filters
     * @return array{0: string, 1: array<string, string>}
     */
    private function customerFilter(int $customerId, array $filters): array
    {
        $where = ['r.customer_id = :customer_id'];
        $params = ['customer_id' => $customerId];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $like = '%' . $query . '%';
            $where[] = '(r.public_number LIKE :q_number
                OR r.device_type LIKE :q_type
                OR r.device_brand LIKE :q_brand
                OR r.device_model LIKE :q_model
                OR s.name LIKE :q_service)';
            $params['q_number'] = $like;
            $params['q_type'] = $like;
            $params['q_brand'] = $like;
            $params['q_model'] = $like;
            $params['q_service'] = $like;
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }

        return [implode(' AND ', $where), $params];
    }

    private function customerSort(string $sort): string
    {
        return match ($sort) {
            'oldest' => 'r.created_at ASC, r.id ASC',
            'updated' => 'r.updated_at DESC, r.id DESC',
            default => 'r.created_at DESC, r.id DESC',
        };
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function statusMap(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $status = isset($row['status']) ? (string) $row['status'] : '';
            if ($status !== '') {
                $counts[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $counts;
    }
}
