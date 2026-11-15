<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use DateTimeImmutable;
use FixPoint\Core\Database;
use PDO;

/** Read-only aggregate queries used by the administrator's statistics screen. */
final class AdminStatisticsRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @param array{from: string, to: string} $filters
     * @return array{
     *   total_requests: int,
     *   requests_completed: int,
     *   requests_in_progress: int,
     *   requests_waiting_approval: int,
     *   requests_rejected: int
     * }
     */
    public function summary(array $filters): array
    {
        [$where, $params] = $this->dateWhere($filters);
        $statement = $this->database->connection()->prepare(
            'SELECT
                COUNT(*) AS total_requests,
                COALESCE(SUM(CASE WHEN r.status = \'completed\' THEN 1 ELSE 0 END), 0) AS requests_completed,
                COALESCE(SUM(CASE WHEN r.status IN (\'accepted\', \'diagnostics\', \'awaiting_parts\', \'repairing\', \'ready_for_pickup\') THEN 1 ELSE 0 END), 0) AS requests_in_progress,
                COALESCE(SUM(CASE WHEN r.status = \'awaiting_customer_approval\' THEN 1 ELSE 0 END), 0) AS requests_waiting_approval,
                COALESCE(SUM(CASE WHEN r.status = \'rejected\' THEN 1 ELSE 0 END), 0) AS requests_rejected
             FROM repair_requests r
             WHERE ' . $where
        );
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_requests' => (int) ($row['total_requests'] ?? 0),
            'requests_completed' => (int) ($row['requests_completed'] ?? 0),
            'requests_in_progress' => (int) ($row['requests_in_progress'] ?? 0),
            'requests_waiting_approval' => (int) ($row['requests_waiting_approval'] ?? 0),
            'requests_rejected' => (int) ($row['requests_rejected'] ?? 0),
        ];
    }

    /**
     * @param array{from: string, to: string} $filters
     * @return list<array{status: string, request_count: int}>
     */
    public function statusBreakdown(array $filters): array
    {
        [$where, $params] = $this->dateWhere($filters);
        $statement = $this->database->connection()->prepare(
            'SELECT r.status, COUNT(*) AS request_count
             FROM repair_requests r
             WHERE ' . $where . '
             GROUP BY r.status
             ORDER BY request_count DESC, r.status ASC'
        );
        $statement->execute($params);

        return array_map(
            static fn (array $row): array => [
                'status' => (string) $row['status'],
                'request_count' => (int) $row['request_count'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @param array{from: string, to: string} $filters
     * @return list<array{month_key: string, month_label: string, request_count: int}>
     */
    public function monthlyRequests(array $filters): array
    {
        [$where, $params] = $this->dateWhere($filters);
        $statement = $this->database->connection()->prepare(
            'SELECT month_key, month_label, request_count
             FROM (
                SELECT
                    DATE_FORMAT(r.created_at, \'%Y-%m\') AS month_key,
                    DATE_FORMAT(r.created_at, \'%m.%Y\') AS month_label,
                    COUNT(*) AS request_count
                FROM repair_requests r
                WHERE ' . $where . '
                GROUP BY month_key, month_label
                ORDER BY month_key DESC
                LIMIT 12
             ) AS recent_months
             ORDER BY month_key ASC'
        );
        $statement->execute($params);

        return array_map(
            static fn (array $row): array => [
                'month_key' => (string) $row['month_key'],
                'month_label' => (string) $row['month_label'],
                'request_count' => (int) $row['request_count'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @param array{from: string, to: string} $filters
     * @return list<array{name: string, request_count: int}>
     */
    public function topServices(array $filters): array
    {
        [$where, $params] = $this->dateWhere($filters);
        $statement = $this->database->connection()->prepare(
            'SELECT s.name, COUNT(*) AS request_count
             FROM repair_requests r
             INNER JOIN services s ON s.id = r.service_id
             WHERE ' . $where . '
             GROUP BY s.id, s.name
             ORDER BY request_count DESC, s.name ASC
             LIMIT 5'
        );
        $statement->execute($params);

        return array_map(
            static fn (array $row): array => [
                'name' => (string) $row['name'],
                'request_count' => (int) $row['request_count'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Use a half-open interval so the selected end date includes all times
     * during that day, without applying DATE() to the indexed column.
     *
     * @param array{from: string, to: string} $filters
     * @return array{0: string, 1: array{date_from: string, date_to_exclusive: string}}
     */
    private function dateWhere(array $filters): array
    {
        $exclusiveEnd = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');

        return [
            'r.created_at >= :date_from AND r.created_at < :date_to_exclusive',
            [
                'date_from' => $filters['from'],
                'date_to_exclusive' => $exclusiveEnd,
            ],
        ];
    }
}
