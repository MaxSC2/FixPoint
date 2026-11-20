<?php

declare(strict_types=1);

/**
 * Test helper for CalendarAppointmentTest::testConcurrentBookingsSerialize...
 *
 * Holds the same per-technician row lock the booking service uses, signals the
 * test, then commits a conflicting confirmed appointment. The service must wait
 * for this lock and reject its own stale booking after the row is visible.
 *
 * Usage:
 *   php hold_calendar_lock.php host port database username password
 *       technician_id request_id customer_id start_utc end_utc ready_file
 */

if ($argc !== 12) {
    fwrite(STDERR, "Unexpected argument count.\n");
    exit(2);
}

[, $host, $port, $database, $username, $password, $technicianId, $requestId, $customerId, $startUtc, $endUtc, $readyFile] = $argv;

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $pdo->beginTransaction();
    $lock = $pdo->prepare(
        'SELECT id FROM users WHERE id = :id AND role = "technician" AND is_active = 1 FOR UPDATE'
    );
    $lock->execute(['id' => (int) $technicianId]);
    if ($lock->fetchColumn() === false) {
        throw new RuntimeException('Fixture technician not found.');
    }

    if (file_put_contents($readyFile, 'locked') === false) {
        throw new RuntimeException('Cannot write the lock-ready marker.');
    }

    // Give the parent test time to reach its own technician lock.
    usleep(900000);

    $insert = $pdo->prepare(
        'INSERT INTO technician_appointments
            (request_id, customer_id, technician_id, meeting_type, status,
             starts_at_utc, ends_at_utc, timezone, purpose, created_by)
         VALUES
            (:request_id, :customer_id, :technician_id, "consultation", "confirmed",
             :starts_at_utc, :ends_at_utc, "UTC", "Консультация", :created_by)'
    );
    $insert->execute([
        'request_id' => (int) $requestId,
        'customer_id' => (int) $customerId,
        'technician_id' => (int) $technicianId,
        'starts_at_utc' => $startUtc,
        'ends_at_utc' => $endUtc,
        'created_by' => (int) $customerId,
    ]);

    $pdo->commit();
    exit(0);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
