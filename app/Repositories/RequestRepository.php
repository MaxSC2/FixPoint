<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;

/**
 * Read model for repair requests. Every query is scoped explicitly: customer
 * queries filter by customer_id and technician queries by technician_id, so a
 * controller cannot accidentally widen access through a list method.
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
