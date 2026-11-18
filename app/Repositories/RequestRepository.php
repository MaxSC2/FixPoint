<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;
use DateTimeImmutable;
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

    /**
     * Persist the technician's diagnosis and estimate. Status changes stay in
     * transitionStatus so every workflow step is validated and logged.
     */
    public function updateDiagnosis(
        int $requestId,
        string $diagnosticResult,
        ?string $estimatedCost,
        ?string $estimatedReadyAt
    ): bool {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();

        try {
            // Lock and re-check the stage inside the same transaction. This
            // prevents a stale technician form from overwriting a quote after
            // the customer-approval transition has committed.
            $lock = $pdo->prepare(
                'SELECT status FROM repair_requests WHERE id = :id FOR UPDATE'
            );
            $lock->execute(['id' => $requestId]);
            $current = $lock->fetch();

            if (!is_array($current) || (string) ($current['status'] ?? '') !== 'diagnostics') {
                $pdo->rollBack();

                return false;
            }

            $statement = $pdo->prepare(
                'UPDATE repair_requests
                 SET diagnostic_result = :diagnostic_result,
                     estimated_cost = :estimated_cost,
                     estimated_ready_at = :estimated_ready_at
                 WHERE id = :id AND status = \'diagnostics\''
            );
            $statement->execute([
                'diagnostic_result' => $diagnosticResult,
                'estimated_cost' => $estimatedCost,
                'estimated_ready_at' => $estimatedReadyAt,
                'id' => $requestId,
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
            // Keep workflow invariants atomic with the row update. Service-level
            // checks improve the error message, but stale concurrent requests
            // must not bypass estimate submission or the customer's decision.
            if ($toStatus === 'awaiting_customer_approval' || $fromStatus === 'awaiting_customer_approval') {
                $sql .= ' AND estimated_cost IS NOT NULL';
            }
            if (in_array($fromStatus, ['diagnostics', 'awaiting_parts'], true)
                && in_array($toStatus, ['awaiting_parts', 'repairing'], true)
            ) {
                $sql .= ' AND estimated_cost IS NULL';
            }

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

    /**
     * Scoped work queue. A null technician id means the caller is an admin and
     * wants every request; filter values are validated by the service.
     *
     * @param array{q: string, status: string, priority: string, sort: string} $filters
     * @return list<array<string, mixed>>
     */
    public function listForQueueFiltered(?int $technicianId, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->queueFilter($technicianId, $filters);
        $order = $this->queueSort((string) ($filters['sort'] ?? 'newest'));

        $statement = $this->database->connection()->prepare(
            self::SELECT . ' WHERE ' . $where . ' ORDER BY ' . $order . '
             LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @param array{q: string, status: string, priority: string, sort: string} $filters
     */
    public function countForQueueFiltered(?int $technicianId, array $filters): int
    {
        [$where, $params] = $this->queueFilter($technicianId, $filters);
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*)
             FROM repair_requests r
             INNER JOIN users c ON c.id = r.customer_id
             LEFT JOIN services s ON s.id = r.service_id
             WHERE ' . $where
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * Global admin registry. Filter values are allowlisted by RequestService;
     * the query reuses the staff filter so every join stays in one place.
     *
     * @param array{q: string, status: string, priority: string, sort: string} $filters
     * @return list<array<string, mixed>>
     */
    public function listForAdminFiltered(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->queueFilter(null, $filters);
        $order = $this->queueSort((string) ($filters['sort'] ?? 'newest'));

        $statement = $this->database->connection()->prepare(
            self::SELECT . ' WHERE ' . $where . ' ORDER BY ' . $order . '
             LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @param array{q: string, status: string, priority: string, sort: string} $filters */
    public function countForAdminFiltered(array $filters): int
    {
        [$where, $params] = $this->queueFilter(null, $filters);
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*)
             FROM repair_requests r
             INNER JOIN users c ON c.id = r.customer_id
             LEFT JOIN services s ON s.id = r.service_id
             WHERE ' . $where
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * Assign a technician without touching the status workflow. The status
     * guard closes the race where a terminal request is assigned after the
     * service already checked it.
     */
    public function assignTechnician(int $requestId, int $technicianId): bool
    {
        $statement = $this->database->connection()->prepare(
            "UPDATE repair_requests
             SET technician_id = :technician_id
             WHERE id = :id
               AND status NOT IN ('completed', 'cancelled', 'rejected')"
        );
        $statement->execute([
            'technician_id' => $technicianId,
            'id' => $requestId,
        ]);

        return $statement->rowCount() === 1;
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
     * Aggregates for the administrator statistics page. The optional dates are
     * already validated by AdminStatisticsService and are applied to
     * created_at as an inclusive day range in UTC.
     *
     * @return array{
     *   total_requests: int,
     *   requests_completed: int,
     *   requests_in_progress: int,
     *   requests_waiting_approval: int,
     *   requests_rejected: int
     * }
     */
    public function statisticsSummary(?string $fromDate, ?string $toDate): array
    {
        [$where, $params] = $this->statisticsPeriod($fromDate, $toDate);
        $statement = $this->database->connection()->prepare(
            "SELECT
                COUNT(*) AS total_requests,
                COALESCE(SUM(status = 'completed'), 0) AS requests_completed,
                COALESCE(SUM(status IN
                    ('accepted', 'diagnostics', 'awaiting_parts', 'repairing', 'ready_for_pickup')
                ), 0) AS requests_in_progress,
                COALESCE(SUM(status = 'awaiting_customer_approval'), 0) AS requests_waiting_approval,
                COALESCE(SUM(status = 'rejected'), 0) AS requests_rejected
             FROM repair_requests r
             WHERE " . $where
        );
        $statement->execute($params);
        $row = $statement->fetch();

        return [
            'total_requests' => (int) ($row['total_requests'] ?? 0),
            'requests_completed' => (int) ($row['requests_completed'] ?? 0),
            'requests_in_progress' => (int) ($row['requests_in_progress'] ?? 0),
            'requests_waiting_approval' => (int) ($row['requests_waiting_approval'] ?? 0),
            'requests_rejected' => (int) ($row['requests_rejected'] ?? 0),
        ];
    }

    /**
     * @return list<array{status: string, request_count: int}>
     */
    public function statusBreakdownForPeriod(?string $fromDate, ?string $toDate): array
    {
        [$where, $params] = $this->statisticsPeriod($fromDate, $toDate);
        $statement = $this->database->connection()->prepare(
            "SELECT status, COUNT(*) AS request_count
             FROM repair_requests r
             WHERE " . $where . "
             GROUP BY status
             ORDER BY FIELD(status,
                'new', 'accepted', 'diagnostics', 'awaiting_customer_approval',
                'awaiting_parts', 'repairing', 'ready_for_pickup', 'completed',
                'cancelled', 'rejected'
             ), status ASC"
        );
        $statement->execute($params);

        return array_map(
            static fn (array $row): array => [
                'status' => (string) ($row['status'] ?? ''),
                'request_count' => (int) ($row['request_count'] ?? 0),
            ],
            $statement->fetchAll()
        );
    }

    /**
     * @return list<array{month_key: string, request_count: int}>
     */
    public function monthlyCountsForPeriod(?string $fromDate, ?string $toDate): array
    {
        [$where, $params] = $this->statisticsPeriod($fromDate, $toDate);
        $statement = $this->database->connection()->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS request_count
             FROM repair_requests r
             WHERE " . $where . "
             GROUP BY month_key
             ORDER BY month_key ASC"
        );
        $statement->execute($params);

        return array_map(
            static fn (array $row): array => [
                'month_key' => (string) ($row['month_key'] ?? ''),
                'request_count' => (int) ($row['request_count'] ?? 0),
            ],
            $statement->fetchAll()
        );
    }

    /**
     * @return list<array{name: string, request_count: int}>
     */
    public function topServicesForPeriod(?string $fromDate, ?string $toDate, int $limit = 5): array
    {
        [$where, $params] = $this->statisticsPeriod($fromDate, $toDate);
        $limit = max(1, min(10, $limit));
        $statement = $this->database->connection()->prepare(
            'SELECT s.name, COUNT(*) AS request_count
             FROM repair_requests r
             INNER JOIN services s ON s.id = r.service_id
             WHERE ' . $where . '
             GROUP BY s.id, s.name
             ORDER BY request_count DESC, s.name ASC
             LIMIT ' . $limit
        );
        $statement->execute($params);

        return array_map(
            static fn (array $row): array => [
                'name' => (string) ($row['name'] ?? ''),
                'request_count' => (int) ($row['request_count'] ?? 0),
            ],
            $statement->fetchAll()
        );
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function statisticsPeriod(?string $fromDate, ?string $toDate): array
    {
        $where = [];
        $params = [];

        if ($fromDate !== null) {
            $where[] = 'r.created_at >= :statistics_from';
            $params['statistics_from'] = $fromDate . ' 00:00:00';
        }

        if ($toDate !== null) {
            $nextDay = (new DateTimeImmutable($toDate . ' 00:00:00'))
                ->modify('+1 day')
                ->format('Y-m-d H:i:s');
            $where[] = 'r.created_at < :statistics_to';
            $params['statistics_to'] = $nextDay;
        }

        return [$where === [] ? '1 = 1' : implode(' AND ', $where), $params];
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
     * @param array{q: string, status: string, priority: string} $filters
     * @return array{0: string, 1: array<string, string>}
     */
    private function queueFilter(?int $technicianId, array $filters): array
    {
        $where = [];
        $params = [];

        if ($technicianId !== null) {
            $where[] = 'r.technician_id = :technician_id';
            $params['technician_id'] = $technicianId;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $like = '%' . $query . '%';
            $where[] = '(r.public_number LIKE :q_number
                OR c.full_name LIKE :q_customer
                OR r.device_type LIKE :q_type
                OR r.device_brand LIKE :q_brand
                OR r.device_model LIKE :q_model
                OR s.name LIKE :q_service)';
            $params['q_number'] = $like;
            $params['q_customer'] = $like;
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

        $priority = (string) ($filters['priority'] ?? '');
        if ($priority !== '') {
            $where[] = 'r.priority = :priority';
            $params['priority'] = $priority;
        }

        return [$where === [] ? '1 = 1' : implode(' AND ', $where), $params];
    }

    private function queueSort(string $sort): string
    {
        return match ($sort) {
            'oldest' => 'r.created_at ASC, r.id ASC',
            'updated' => 'r.updated_at DESC, r.id DESC',
            'priority' => "FIELD(r.priority, 'urgent', 'high', 'normal', 'low'), r.created_at DESC, r.id DESC",
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
