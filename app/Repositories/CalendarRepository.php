<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;
use PDO;
use Throwable;

/**
 * Persistence for a technician's weekly schedule, one-off blocks and the
 * technician row used as the serialization lock for booking operations.
 */
final class CalendarRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function connection(): PDO
    {
        return $this->database->connection();
    }

    /** @return array<string, mixed>|null */
    public function settings(int $technicianId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT * FROM technician_calendar_settings WHERE technician_id = :technician_id LIMIT 1'
        );
        $statement->execute(['technician_id' => $technicianId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function availability(int $technicianId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT id, technician_id, weekday, start_time, end_time
             FROM technician_calendar_availability
             WHERE technician_id = :technician_id
             ORDER BY weekday ASC, start_time ASC, id ASC'
        );
        $statement->execute(['technician_id' => $technicianId]);

        return $statement->fetchAll();
    }

    /**
     * Replace the whole weekly schedule atomically. Existing appointments and
     * blocks are deliberately untouched: changing a schedule never rewrites
     * already agreed meetings.
     *
     * @param array<string, mixed> $settings
     * @param list<array{weekday: int, start_time: string, end_time: string}> $intervals
     */
    public function saveSettings(int $technicianId, array $settings, array $intervals): void
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            if (!$this->lockTechnician($technicianId)) {
                throw new \RuntimeException('Technician not found.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO technician_calendar_settings
                    (technician_id, timezone, slot_minutes, min_notice_minutes,
                     pending_hold_minutes, cancellation_cutoff_minutes, is_configured)
                 VALUES
                    (:technician_id, :timezone, :slot_minutes, :min_notice_minutes,
                     :pending_hold_minutes, :cancellation_cutoff_minutes, 1)
                 ON DUPLICATE KEY UPDATE
                    timezone = VALUES(timezone),
                    slot_minutes = VALUES(slot_minutes),
                    min_notice_minutes = VALUES(min_notice_minutes),
                    pending_hold_minutes = VALUES(pending_hold_minutes),
                    cancellation_cutoff_minutes = VALUES(cancellation_cutoff_minutes),
                    is_configured = 1'
            );
            $statement->execute([
                'technician_id' => $technicianId,
                'timezone' => $settings['timezone'],
                'slot_minutes' => $settings['slot_minutes'],
                'min_notice_minutes' => $settings['min_notice_minutes'],
                'pending_hold_minutes' => $settings['pending_hold_minutes'],
                'cancellation_cutoff_minutes' => $settings['cancellation_cutoff_minutes'],
            ]);

            $delete = $pdo->prepare(
                'DELETE FROM technician_calendar_availability WHERE technician_id = :technician_id'
            );
            $delete->execute(['technician_id' => $technicianId]);

            $insert = $pdo->prepare(
                'INSERT INTO technician_calendar_availability
                    (technician_id, weekday, start_time, end_time)
                 VALUES (:technician_id, :weekday, :start_time, :end_time)'
            );
            foreach ($intervals as $interval) {
                $insert->execute([
                    'technician_id' => $technicianId,
                    'weekday' => $interval['weekday'],
                    'start_time' => $interval['start_time'] . ':00',
                    'end_time' => $interval['end_time'] . ':00',
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Blocks are internal data. Customers only ever see the resulting missing
     * slots; they never receive a block title, note or reason.
     *
     * @return list<array<string, mixed>>
     */
    public function blocks(
        int $technicianId,
        ?string $fromUtc = null,
        ?string $toUtc = null,
        bool $forUpdate = false
    ): array {
        $params = ['technician_id' => $technicianId];
        $range = '';
        if ($fromUtc !== null && $toUtc !== null) {
            $range = ' AND starts_at_utc < :to_utc AND ends_at_utc > :from_utc';
            $params['from_utc'] = $fromUtc;
            $params['to_utc'] = $toUtc;
        }

        $statement = $this->connection()->prepare(
            'SELECT id, technician_id, starts_at_utc, ends_at_utc, kind, title, notes,
                    is_all_day, created_at, updated_at
             FROM technician_calendar_blocks
             WHERE technician_id = :technician_id' . $range . '
             ORDER BY starts_at_utc ASC, id ASC' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findBlock(int $blockId, int $technicianId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT * FROM technician_calendar_blocks
             WHERE id = :id AND technician_id = :technician_id LIMIT 1'
        );
        $statement->execute(['id' => $blockId, 'technician_id' => $technicianId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function createBlock(int $technicianId, array $data): int
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO technician_calendar_blocks
                (technician_id, starts_at_utc, ends_at_utc, kind, title, notes, is_all_day)
             VALUES
                (:technician_id, :starts_at_utc, :ends_at_utc, :kind, :title, :notes, :is_all_day)'
        );
        $statement->execute([
            'technician_id' => $technicianId,
            'starts_at_utc' => $data['starts_at_utc'],
            'ends_at_utc' => $data['ends_at_utc'],
            'kind' => $data['kind'],
            'title' => $data['title'],
            'notes' => $data['notes'],
            'is_all_day' => $data['is_all_day'],
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    public function deleteBlock(int $blockId, int $technicianId): bool
    {
        $statement = $this->connection()->prepare(
            'DELETE FROM technician_calendar_blocks WHERE id = :id AND technician_id = :technician_id'
        );
        $statement->execute(['id' => $blockId, 'technician_id' => $technicianId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Serialize every booking mutation for one technician. InnoDB row locks on
     * this row make concurrent requests for the same schedule wait instead of
     * both passing the conflict check.
     */
    public function lockTechnician(int $technicianId): bool
    {
        $statement = $this->connection()->prepare(
            'SELECT id FROM users
             WHERE id = :id AND role = "technician" AND is_active = 1
             FOR UPDATE'
        );
        $statement->execute(['id' => $technicianId]);

        return $statement->fetchColumn() !== false;
    }

    /** @return array<string, mixed>|null */
    public function findTechnician(int $technicianId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT id, full_name, email, role, is_active
             FROM users WHERE id = :id AND role = "technician" LIMIT 1'
        );
        $statement->execute(['id' => $technicianId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
