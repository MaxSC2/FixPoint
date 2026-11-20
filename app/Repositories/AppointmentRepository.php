<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;
use PDO;

/**
 * Appointment persistence. Reads always join the public request number and the
 * two participants so controllers and views never invent a name. Every write
 * used by the lifecycle runs inside a service transaction after the technician
 * row has been locked.
 */
final class AppointmentRepository
{
    private const SELECT = 'SELECT
            a.*,
            r.public_number, r.status AS request_status, r.device_type,
            r.device_brand, r.device_model, r.problem_description,
            c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
            t.full_name AS technician_name,
            p.full_name AS proposed_by_name
        FROM technician_appointments a
        INNER JOIN repair_requests r ON r.id = a.request_id
        INNER JOIN users c ON c.id = a.customer_id
        INNER JOIN users t ON t.id = a.technician_id
        LEFT JOIN users p ON p.id = a.proposed_by';

    private const UPDATABLE_COLUMNS = [
        'status',
        'starts_at_utc',
        'ends_at_utc',
        'technician_note',
        'proposed_starts_at_utc',
        'proposed_ends_at_utc',
        'proposed_by',
        'reschedule_from_status',
        'pending_expires_at_utc',
        'confirmed_at_utc',
        'declined_at_utc',
        'cancelled_at_utc',
        'completed_at_utc',
        'customer_comment',
        'purpose',
    ];

    public function __construct(private readonly Database $database)
    {
    }

    public function connection(): PDO
    {
        return $this->database->connection();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $appointmentId): ?array
    {
        $statement = $this->connection()->prepare(
            self::SELECT . ' WHERE a.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $appointmentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Read-only request projection used by the customer booking page. The
     * booking transaction later re-reads the same row with FOR UPDATE.
     *
     * @return array<string, mixed>|null
     */
    public function findRequest(int $requestId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT r.id, r.public_number, r.customer_id, r.technician_id, r.status,
                    r.device_type, r.device_brand, r.device_model, r.problem_description,
                    c.full_name AS customer_name,
                    t.full_name AS technician_name
             FROM repair_requests r
             INNER JOIN users c ON c.id = r.customer_id
             LEFT JOIN users t ON t.id = r.technician_id
             WHERE r.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $requestId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findRaw(int $appointmentId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT * FROM technician_appointments WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $appointmentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findRawForUpdate(int $appointmentId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT * FROM technician_appointments WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $appointmentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Lock the request row while booking so an administrator cannot reassign
     * the technician between the ownership check and the insert.
     *
     * @return array<string, mixed>|null
     */
    public function lockRequest(int $requestId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT id, public_number, customer_id, technician_id, status,
                    device_type, device_brand, device_model
             FROM repair_requests
             WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $requestId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function listForCustomer(int $customerId): array
    {
        $statement = $this->connection()->prepare(
            self::SELECT . ' WHERE a.customer_id = :customer_id
             ORDER BY a.starts_at_utc DESC, a.id DESC LIMIT 500'
        );
        $statement->execute(['customer_id' => $customerId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listForTechnician(int $technicianId): array
    {
        $statement = $this->connection()->prepare(
            self::SELECT . ' WHERE a.technician_id = :technician_id
             ORDER BY a.starts_at_utc ASC, a.id ASC LIMIT 500'
        );
        $statement->execute(['technician_id' => $technicianId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listForRequest(int $requestId): array
    {
        $statement = $this->connection()->prepare(
            self::SELECT . ' WHERE a.request_id = :request_id
             ORDER BY a.starts_at_utc ASC, a.id ASC'
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function forTechnicianRange(int $technicianId, string $fromUtc, string $toUtc): array
    {
        $statement = $this->connection()->prepare(
            self::SELECT . ' WHERE a.technician_id = :technician_id
             AND (
                (a.starts_at_utc < :to_1 AND a.ends_at_utc > :from_1)
                OR (a.proposed_starts_at_utc IS NOT NULL
                    AND a.proposed_starts_at_utc < :to_2
                    AND a.proposed_ends_at_utc > :from_2)
             )
             ORDER BY a.starts_at_utc ASC, a.id ASC'
        );
        $statement->execute([
            'technician_id' => $technicianId,
            'from_1' => $fromUtc,
            'to_1' => $toUtc,
            'from_2' => $fromUtc,
            'to_2' => $toUtc,
        ]);

        return $statement->fetchAll();
    }

    /**
     * Reservations that still occupy time for the technician. Pending rows are
     * ignored once their hold expires, so an abandoned request cannot block a
     * slot forever.
     *
     * @return list<array<string, mixed>>
     */
    public function activeForTechnicianRange(
        int $technicianId,
        string $fromUtc,
        string $toUtc,
        string $nowUtc,
        ?int $excludeAppointmentId = null,
        bool $forUpdate = false
    ): array {
        $params = [
            'technician_id' => $technicianId,
            'confirmed_from' => $fromUtc,
            'confirmed_to' => $toUtc,
            'pending_now' => $nowUtc,
            'pending_from' => $fromUtc,
            'pending_to' => $toUtc,
            'reschedule_now' => $nowUtc,
            'original_from' => $fromUtc,
            'original_to' => $toUtc,
            'proposed_from' => $fromUtc,
            'proposed_to' => $toUtc,
        ];
        $exclude = '';
        if ($excludeAppointmentId !== null && $excludeAppointmentId > 0) {
            $exclude = ' AND a.id <> :exclude_id';
            $params['exclude_id'] = $excludeAppointmentId;
        }

        $statement = $this->connection()->prepare(
            'SELECT a.id, a.starts_at_utc, a.ends_at_utc, a.status,
                    a.pending_expires_at_utc, a.reschedule_from_status,
                    a.proposed_starts_at_utc, a.proposed_ends_at_utc
             FROM technician_appointments a
             WHERE a.technician_id = :technician_id' . $exclude . '
               AND (
                    (a.status = "confirmed"
                        AND a.starts_at_utc < :confirmed_to
                        AND a.ends_at_utc > :confirmed_from)
                    OR (a.status = "pending"
                        AND a.pending_expires_at_utc > :pending_now
                        AND a.starts_at_utc < :pending_to
                        AND a.ends_at_utc > :pending_from)
                    OR (a.status = "reschedule_proposed"
                        AND (COALESCE(a.reschedule_from_status, "confirmed") <> "pending"
                             OR a.pending_expires_at_utc > :reschedule_now)
                        AND (
                            (a.starts_at_utc < :original_to AND a.ends_at_utc > :original_from)
                            OR (a.proposed_starts_at_utc < :proposed_to
                                AND a.proposed_ends_at_utc > :proposed_from)
                        ))
               )' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function activeCountForRequestType(
        int $requestId,
        string $meetingType,
        string $nowUtc,
        ?int $excludeAppointmentId = null
    ): int {
        $params = [
            'request_id' => $requestId,
            'meeting_type' => $meetingType,
            'now_pending' => $nowUtc,
            'now_reschedule' => $nowUtc,
        ];
        $exclude = '';
        if ($excludeAppointmentId !== null && $excludeAppointmentId > 0) {
            $exclude = ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeAppointmentId;
        }
        $statement = $this->connection()->prepare(
            'SELECT COUNT(*) FROM technician_appointments
             WHERE request_id = :request_id
               AND meeting_type = :meeting_type
               AND (
                    status = "confirmed"
                    OR (status = "pending" AND pending_expires_at_utc > :now_pending)
                    OR (status = "reschedule_proposed"
                        AND (COALESCE(reschedule_from_status, "confirmed") <> "pending"
                             OR pending_expires_at_utc > :now_reschedule))
               )' . $exclude
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO technician_appointments
                (request_id, customer_id, technician_id, meeting_type, status,
                 starts_at_utc, ends_at_utc, timezone, purpose, customer_comment,
                 pending_expires_at_utc, created_by)
             VALUES
                (:request_id, :customer_id, :technician_id, :meeting_type, :status,
                 :starts_at_utc, :ends_at_utc, :timezone, :purpose, :customer_comment,
                 :pending_expires_at_utc, :created_by)'
        );
        $statement->execute([
            'request_id' => $data['request_id'],
            'customer_id' => $data['customer_id'],
            'technician_id' => $data['technician_id'],
            'meeting_type' => $data['meeting_type'],
            'status' => $data['status'] ?? 'pending',
            'starts_at_utc' => $data['starts_at_utc'],
            'ends_at_utc' => $data['ends_at_utc'],
            'timezone' => $data['timezone'],
            'purpose' => $data['purpose'],
            'customer_comment' => $data['customer_comment'],
            'pending_expires_at_utc' => $data['pending_expires_at_utc'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    /**
     * Column names are fixed identifiers from the allowlist, never caller data.
     *
     * @param array<string, mixed> $fields
     */
    public function update(int $appointmentId, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        $assignments = [];
        $params = ['id' => $appointmentId];
        foreach ($fields as $column => $value) {
            if (!in_array($column, self::UPDATABLE_COLUMNS, true)) {
                continue;
            }
            $assignments[] = '`' . $column . '` = :' . $column;
            $params[$column] = $value;
        }
        if ($assignments === []) {
            return;
        }

        $statement = $this->connection()->prepare(
            'UPDATE technician_appointments SET ' . implode(', ', $assignments) . ' WHERE id = :id'
        );
        $statement->execute($params);
    }

    /** @param array<string, mixed> $event */
    public function addEvent(int $appointmentId, array $event): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO technician_appointment_events
                (appointment_id, actor_id, action, from_status, to_status,
                 previous_starts_at_utc, previous_ends_at_utc,
                 new_starts_at_utc, new_ends_at_utc, note)
             VALUES
                (:appointment_id, :actor_id, :action, :from_status, :to_status,
                 :previous_starts_at_utc, :previous_ends_at_utc,
                 :new_starts_at_utc, :new_ends_at_utc, :note)'
        );
        $statement->execute([
            'appointment_id' => $appointmentId,
            'actor_id' => $event['actor_id'] ?? null,
            'action' => $event['action'],
            'from_status' => $event['from_status'] ?? null,
            'to_status' => $event['to_status'],
            'previous_starts_at_utc' => $event['previous_starts_at_utc'] ?? null,
            'previous_ends_at_utc' => $event['previous_ends_at_utc'] ?? null,
            'new_starts_at_utc' => $event['new_starts_at_utc'] ?? null,
            'new_ends_at_utc' => $event['new_ends_at_utc'] ?? null,
            'note' => $event['note'] ?? null,
        ]);
    }

    /**
     * Pending (or pending-origin proposal) rows whose short reservation has
     * lapsed. Locking reads make a second expiry sweep wait for the first one
     * and then observe the new status.
     *
     * @return list<array<string, mixed>>
     */
    public function expiredHolds(string $nowUtc, int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $statement = $this->connection()->prepare(
            'SELECT id, status, reschedule_from_status, starts_at_utc, ends_at_utc
             FROM technician_appointments
             WHERE pending_expires_at_utc IS NOT NULL
               AND pending_expires_at_utc <= :now_utc
               AND (
                    status = "pending"
                    OR (status = "reschedule_proposed" AND reschedule_from_status = "pending")
               )
             ORDER BY pending_expires_at_utc ASC, id ASC
             LIMIT ' . $limit . '
             FOR UPDATE'
        );
        $statement->execute(['now_utc' => $nowUtc]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function events(int $appointmentId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT e.*, u.full_name AS actor_name
             FROM technician_appointment_events e
             LEFT JOIN users u ON u.id = e.actor_id
             WHERE e.appointment_id = :appointment_id
             ORDER BY e.created_at ASC, e.id ASC'
        );
        $statement->execute(['appointment_id' => $appointmentId]);

        return $statement->fetchAll();
    }
}
