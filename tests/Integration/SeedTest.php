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
