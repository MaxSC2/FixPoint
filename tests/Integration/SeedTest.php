<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Database\Seeder;
use FixPoint\Security\PasswordHasher;
use FixPoint\Tests\IntegrationTestCase;

final class SeedTest extends IntegrationTestCase
{
    public function testSeedCreatesExpectedLocalData(): void
    {
        $pdo = app()->database()->connection();

        $this->assertSame(6, $this->count('users'));
        $this->assertSame(5, $this->count('service_categories'));
        $this->assertSame(10, $this->count('services'));
        $this->assertSame(4, $this->count('repair_requests'));
        $this->assertSame(15, $this->count('request_status_history'));
        $this->assertSame(5, $this->count('request_comments'));

        $admin = $pdo->query("SELECT * FROM users WHERE email = 'admin@fixpoint.test'")->fetch();
        $this->assertNotSame(false, $admin);
        $this->assertSame('admin', $admin['role']);
        $this->assertSame('1', (string) $admin['is_active']);

        $password = \FixPoint\Core\Env::get('SEED_PASSWORD', 'FixPoint!Dev1') ?? 'FixPoint!Dev1';
        $this->assertTrue((new PasswordHasher())->verify($password, (string) $admin['password_hash']));
    }

    public function testSeedIsIdempotent(): void
    {
        $before = [
            'users' => $this->count('users'),
            'categories' => $this->count('service_categories'),
            'services' => $this->count('services'),
            'requests' => $this->count('repair_requests'),
            'history' => $this->count('request_status_history'),
            'comments' => $this->count('request_comments'),
        ];

        $seeder = new Seeder(app()->database()->connection(), new PasswordHasher(), app()->config());
        $summary = $seeder->run();

        $after = [
            'users' => $this->count('users'),
            'categories' => $this->count('service_categories'),
            'services' => $this->count('services'),
            'requests' => $this->count('repair_requests'),
            'history' => $this->count('request_status_history'),
            'comments' => $this->count('request_comments'),
        ];

        $this->assertSame($before, $after);
        $this->assertSame(0, $summary['history']);
        $this->assertSame(0, $summary['comments']);
    }

    public function testSeedDoesNotOverwriteExistingRequestWorkflowChanges(): void
    {
        $pdo = app()->database()->connection();
        $request = $pdo->query(
            'SELECT id, technician_id, status, priority, problem_description, diagnostic_result,
                    estimated_cost, final_cost, estimated_ready_at, updated_at, completed_at
             FROM repair_requests ORDER BY id ASC LIMIT 1'
        )->fetch();

        $this->assertNotSame(false, $request);

        $technicianId = (int) $pdo->query(
            "SELECT id FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY id LIMIT 1"
        )->fetchColumn();

        $edited = [
            'technician_id' => $technicianId,
            'status' => 'diagnostics',
            'priority' => 'urgent',
            'problem_description' => 'Locally edited problem description',
            'diagnostic_result' => 'Locally edited diagnosis',
            'estimated_cost' => '12345.67',
            'final_cost' => '23456.78',
            'estimated_ready_at' => '2026-12-01 10:00:00',
            'updated_at' => '2026-11-13 01:00:00',
            'completed_at' => '2026-12-02 15:00:00',
        ];

        $update = $pdo->prepare(
            'UPDATE repair_requests
             SET technician_id = :technician_id, status = :status, priority = :priority,
                 problem_description = :problem_description, diagnostic_result = :diagnostic_result,
                 estimated_cost = :estimated_cost, final_cost = :final_cost,
                 estimated_ready_at = :estimated_ready_at, updated_at = :updated_at,
                 completed_at = :completed_at
             WHERE id = :id'
        );
        $update->execute($edited + ['id' => (int) $request['id']]);

        try {
            $seeder = new Seeder($pdo, new PasswordHasher(), app()->config());
            $seeder->run();

            $read = $pdo->prepare(
                'SELECT technician_id, status, priority, problem_description, diagnostic_result,
                        estimated_cost, final_cost, estimated_ready_at, updated_at, completed_at
                 FROM repair_requests WHERE id = :id'
            );
            $read->execute(['id' => (int) $request['id']]);
            $after = $read->fetch();

            foreach ($edited as $field => $expected) {
                $this->assertSame((string) $expected, (string) $after[$field], $field . ' must be preserved');
            }
        } finally {
            $restore = $pdo->prepare(
                'UPDATE repair_requests
                 SET technician_id = :technician_id, status = :status, priority = :priority,
                     problem_description = :problem_description, diagnostic_result = :diagnostic_result,
                     estimated_cost = :estimated_cost, final_cost = :final_cost,
                     estimated_ready_at = :estimated_ready_at, updated_at = :updated_at,
                     completed_at = :completed_at
                 WHERE id = :id'
            );
            $restore->execute([
                'technician_id' => $request['technician_id'],
                'status' => $request['status'],
                'priority' => $request['priority'],
                'problem_description' => $request['problem_description'],
                'diagnostic_result' => $request['diagnostic_result'],
                'estimated_cost' => $request['estimated_cost'],
                'final_cost' => $request['final_cost'],
                'estimated_ready_at' => $request['estimated_ready_at'],
                'updated_at' => $request['updated_at'],
                'completed_at' => $request['completed_at'],
                'id' => (int) $request['id'],
            ]);
        }
    }

    public function testSeedRefusesProductionWithoutForce(): void
    {
        $values = app()->config()->all();
        $values['app']['env'] = 'production';
        $config = new \FixPoint\Core\Config($values);
        $seeder = new Seeder(app()->database()->connection(), new PasswordHasher(), $config);

        try {
            $seeder->run(false);
        } catch (\RuntimeException $exception) {
            $this->assertContains('production', $exception->getMessage());

            return;
        }

        $this->fail('Expected production seed guard.');
    }

    private function count(string $table): int
    {
        $allowed = [
            'users', 'service_categories', 'services',
            'repair_requests', 'request_status_history', 'request_comments',
        ];
        if (!in_array($table, $allowed, true)) {
            $this->fail('Unsafe test table name.');
        }

        return (int) app()->database()->connection()->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }
}
