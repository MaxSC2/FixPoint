<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

/**
 * End-to-end HTTP tests for the administrator request workspace: global
 * registry filters/pagination, assignment to active technicians, terminal
 * request protection and role/CSRF boundaries. Fixtures use the TASK005-
 * prefixes and are removed in tearDown so seed data stays untouched.
 */
final class AdminHttpTest extends IntegrationTestCase
{
    private const CONTACT_PREFIX = 'TASK005-';
    private const USER_PREFIX = 'task005-';
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';
    private const OTHER_CUSTOMER = 'customer.ivanova@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';
    private const OTHER_TECHNICIAN = 'master.smirnova@fixpoint.test';
    private const ADMIN = 'admin@fixpoint.test';

    protected function tearDown(): void
    {
        $pdo = $this->pdo();

        $statement = $pdo->prepare('SELECT id FROM repair_requests WHERE customer_contact LIKE :prefix');
        $statement->execute(['prefix' => self::CONTACT_PREFIX . '%']);
        $ids = $statement->fetchAll(\PDO::FETCH_COLUMN);

        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare('DELETE FROM request_comments WHERE request_id IN (' . $placeholders . ')')
                ->execute($ids);
            $pdo->prepare('DELETE FROM request_status_history WHERE request_id IN (' . $placeholders . ')')
                ->execute($ids);
            $pdo->prepare('DELETE FROM repair_requests WHERE id IN (' . $placeholders . ')')
                ->execute($ids);
        }

        $pdo->prepare('DELETE FROM users WHERE email LIKE :prefix')
            ->execute(['prefix' => self::USER_PREFIX . '%']);

        parent::tearDown();
    }

    public function testGuestIsRedirectedFromAdminRequestRoutes(): void
    {
        $requestId = $this->createRequest();

        $registry = app()->handle(Request::create('GET', '/admin/requests'));
        $this->assertSame(302, $registry->status());
        $this->assertContains('/login', (string) $registry->header('Location'));
        $this->assertContains('return_to=%2Fadmin%2Frequests', (string) $registry->header('Location'));

        $assignPage = app()->handle(Request::create('GET', '/admin/requests/' . $requestId . '/assign'));
        $this->assertSame(302, $assignPage->status());
        $this->assertContains(
            'return_to=%2Fadmin%2Frequests%2F' . $requestId . '%2Fassign',
            (string) $assignPage->header('Location')
        );

        $post = app()->handle(Request::create('POST', '/admin/requests/' . $requestId . '/assign', [
            'technician_id' => (string) $this->userId(self::TECHNICIAN),
        ]));
        $this->assertSame(302, $post->status());
        $this->assertContains('/login', (string) $post->header('Location'));
        $this->assertNull($this->requestRow($requestId)['technician_id']);
    }

    public function testCustomerAndTechnicianCannotUseAdminWorkspace(): void
    {
        $requestId = $this->createRequest();
        $technicianId = $this->userId(self::TECHNICIAN);

        foreach ([self::CUSTOMER, self::TECHNICIAN] as $email) {
            $this->loginAs($email);
            $this->assertSame(403, app()->handle(Request::create('GET', '/admin/requests'))->status());
            $this->assertSame(
                403,
                app()->handle(Request::create('GET', '/admin/requests/' . $requestId . '/assign'))->status()
            );
            $this->assertSame(403, $this->post('/admin/requests/' . $requestId . '/assign', [
                'technician_id' => (string) $technicianId,
            ])->status());
            $this->assertNull($this->requestRow($requestId)['technician_id']);
            $this->logout();
        }
    }

    public function testRegistryListsEveryRequestAndSupportsFilters(): void
    {
        $fixture = $this->createRequest([
            'public_number' => 'TASK005-REGISTRY',
            'priority' => 'urgent',
            'status' => 'repairing',
            'device_model' => 'TASK005-RegistryModel',
        ]);
        $this->loginAs(self::ADMIN);

        $all = app()->handle(Request::create('GET', '/admin/requests'));
        $body = $all->body();
        $this->assertSame(200, $all->status());
        foreach (['FP-2026-000001', 'FP-2026-000002', 'FP-2026-000003', 'FP-2026-000004', 'TASK005-REGISTRY'] as $number) {
            $this->assertContains($number, $body);
        }
        $this->assertContains('Алексей Ковалёв', $body);
        $this->assertContains('Мария Иванова', $body);
        $this->assertContains('Иван Петров', $body);
        $this->assertContains('Не назначен', $body);
        $this->assertContains('/admin/requests/' . $fixture . '/assign', $body);

        $byNumber = app()->handle(Request::create('GET', '/admin/requests?q=TASK005-REGISTRY'))->body();
        $this->assertContains('TASK005-REGISTRY', $byNumber);
        $this->assertNotContains('FP-2026-000001', $byNumber);

        $byCustomer = app()->handle(Request::create('GET', '/admin/requests?q=' . rawurlencode('Иванова')))->body();
        $this->assertContains('FP-2026-000002', $byCustomer);
        $this->assertContains('FP-2026-000004', $byCustomer);
        $this->assertNotContains('FP-2026-000001', $byCustomer);
        $this->assertNotContains('TASK005-REGISTRY', $byCustomer);

        $byStatus = app()->handle(Request::create('GET', '/admin/requests?status=completed'))->body();
        $this->assertContains('FP-2026-000004', $byStatus);
        $this->assertNotContains('FP-2026-000001', $byStatus);
        $this->assertNotContains('TASK005-REGISTRY', $byStatus);

        $byPriority = app()->handle(Request::create('GET', '/admin/requests?priority=urgent'))->body();
        $this->assertContains('TASK005-REGISTRY', $byPriority);
        $this->assertNotContains('FP-2026-000001', $byPriority);

        $invalid = app()->handle(Request::create('GET', '/admin/requests?status=nope&priority=nope&sort=nope'))->body();
        $this->assertContains('FP-2026-000001', $invalid);
        $this->assertContains('TASK005-REGISTRY', $invalid);

        $empty = app()->handle(Request::create('GET', '/admin/requests?status=rejected'))->body();
        $this->assertContains('Заявки не найдены', $empty);
    }

    public function testRegistryPaginatesEightPerPageAndSortsByUpdate(): void
    {
        $this->loginAs(self::ADMIN);

        for ($index = 1; $index <= 9; $index++) {
            $this->createRequest([
                'device_model' => 'TASK005-Page-' . $index,
                'created_at' => sprintf('2025-01-%02d 10:00:00', $index),
            ]);
        }

        $first = app()->handle(Request::create('GET', '/admin/requests'))->body();
        $this->assertContains('Страница 1 из 2', $first);
        $this->assertSame(8, substr_count($first, 'class="admin-request-primary"'));

        $second = app()->handle(Request::create('GET', '/admin/requests?page=2'))->body();
        $this->assertContains('Страница 2 из 2', $second);
        $this->assertSame(5, substr_count($second, 'class="admin-request-primary"'));
        $this->assertContains('TASK005-Page-1', $second);
        $this->assertContains('TASK005-Page-5', $second);

        $clamped = app()->handle(Request::create('GET', '/admin/requests?page=999'))->body();
        $this->assertContains('Страница 2 из 2', $clamped);

        $invalid = app()->handle(Request::create('GET', '/admin/requests?page=abc'))->body();
        $this->assertContains('Страница 1 из 2', $invalid);

        $this->createRequest([
            'public_number' => 'TASK005-UPDATED',
            'device_model' => 'TASK005-UpdatedModel',
            'created_at' => '2020-01-01 10:00:00',
            'updated_at' => '2030-01-01 10:00:00',
        ]);
        $olderUpdate = $this->createRequest([
            'public_number' => 'TASK005-OLDER-UPDATE',
            'device_model' => 'TASK005-OlderUpdateModel',
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2025-12-01 10:00:00',
        ]);

        $byUpdate = app()->handle(Request::create('GET', '/admin/requests?sort=updated'))->body();
        $this->assertTrue(
            $this->positionOf('TASK005-UPDATED', $byUpdate) < $this->positionOf('TASK005-OLDER-UPDATE', $byUpdate),
            'The most recently updated request must come first.'
        );
        $this->assertContains('TASK005-UPDATED', $byUpdate);
        $this->assertContains('TASK005-OLDER-UPDATE', $byUpdate);
    }

    public function testAssignPageListsOnlyActiveTechnicians(): void
    {
        $this->loginAs(self::ADMIN);
        $active = $this->createUser('technician', 1, 'active-tech');
        $inactive = $this->createUser('technician', 0, 'inactive-tech');
        $customer = $this->createUser('customer', 1, 'plain-customer');
        $requestId = $this->createRequest();

        $page = app()->handle(Request::create('GET', '/admin/requests/' . $requestId . '/assign'));
        $body = $page->body();
        $this->assertSame(200, $page->status());
        $this->assertContains('action="/admin/requests/' . $requestId . '/assign"', $body);
        $this->assertContains('Иван Петров', $body);
        $this->assertContains('Анна Смирнова', $body);
        $this->assertContains($active['email'], $body);
        $this->assertNotContains($inactive['email'], $body);
        $this->assertNotContains($customer['email'], $body);
        $this->assertContains('name="_token"', $body);
    }

    public function testAssignPersistsTechnicianAndAllowsReassignment(): void
    {
        $requestId = $this->createRequest();
        $ivanovId = $this->userId(self::TECHNICIAN);
        $smirnovaId = $this->userId(self::OTHER_TECHNICIAN);
        $this->loginAs(self::ADMIN);

        $response = $this->post('/admin/requests/' . $requestId . '/assign', [
            'technician_id' => (string) $ivanovId,
        ]);
        $this->assertSame(302, $response->status());
        $this->assertSame('/admin/requests', $response->header('Location'));
        $this->assertSame($ivanovId, (int) $this->requestRow($requestId)['technician_id']);

        $list = app()->handle(Request::create('GET', '/admin/requests?q=' . rawurlencode('TASK005-')))->body();
        $this->assertContains('Мастер назначен.', $list);
        $this->assertContains('Иван Петров', $list);

        $same = $this->post('/admin/requests/' . $requestId . '/assign', [
            'technician_id' => (string) $ivanovId,
        ]);
        $this->assertSame(302, $same->status());
        $this->assertSame($ivanovId, (int) $this->requestRow($requestId)['technician_id']);

        $reassign = $this->post('/admin/requests/' . $requestId . '/assign', [
            'technician_id' => (string) $smirnovaId,
        ]);
        $this->assertSame(302, $reassign->status());
        $this->assertSame($smirnovaId, (int) $this->requestRow($requestId)['technician_id']);
    }

    public function testAssignRejectsInvalidAssignees(): void
    {
        $requestId = $this->createRequest();
        $inactive = $this->createUser('technician', 0, 'assign-inactive');
        $customer = $this->createUser('customer', 1, 'assign-customer');
        $this->loginAs(self::ADMIN);

        $invalidValues = ['', 'abc', '999999999', (string) $inactive['id'], (string) $customer['id']];
        foreach ($invalidValues as $value) {
            $response = $this->post('/admin/requests/' . $requestId . '/assign', [
                'technician_id' => $value,
            ]);
            $this->assertSame(302, $response->status());
            $this->assertSame('/admin/requests/' . $requestId . '/assign', $response->header('Location'));
            $this->assertNull($this->requestRow($requestId)['technician_id']);
        }

        $page = app()->handle(Request::create('GET', '/admin/requests/' . $requestId . '/assign'))->body();
        $this->assertContains('Пользователь недоступен для назначения: нужен активный мастер.', $page);
    }

    public function testAssignRejectsTerminalRequests(): void
    {
        $completed = $this->createRequest([
            'status' => 'completed',
            'technician_id' => $this->userId(self::OTHER_TECHNICIAN),
        ]);
        $this->loginAs(self::ADMIN);

        $response = $this->post('/admin/requests/' . $completed . '/assign', [
            'technician_id' => (string) $this->userId(self::TECHNICIAN),
        ]);
        $this->assertSame(302, $response->status());
        $this->assertSame('/admin/requests/' . $completed . '/assign', $response->header('Location'));
        $this->assertSame(
            $this->userId(self::OTHER_TECHNICIAN),
            (int) $this->requestRow($completed)['technician_id']
        );

        $page = app()->handle(Request::create('GET', '/admin/requests/' . $completed . '/assign'))->body();
        $this->assertContains('Завершённую заявку нельзя переназначить.', $page);
    }

    public function testAssignHandlesUnknownRequestsCsrfAndMethodBoundaries(): void
    {
        $requestId = $this->createRequest();
        $this->loginAs(self::ADMIN);

        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/requests/999999999/assign'))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/requests/not-a-number/assign'))->status());
        $this->assertSame(404, $this->post('/admin/requests/999999999/assign', [
            'technician_id' => (string) $this->userId(self::TECHNICIAN),
        ])->status());

        $noToken = app()->handle(Request::create('POST', '/admin/requests/' . $requestId . '/assign', [
            'technician_id' => (string) $this->userId(self::TECHNICIAN),
        ]));
        $this->assertSame(419, $noToken->status());
        $this->assertNull($this->requestRow($requestId)['technician_id']);
    }

    /** @param array<string, mixed> $overrides */
    private function createRequest(array $overrides = []): int
    {
        $customerId = (int) ($overrides['customer_id'] ?? $this->userId(self::CUSTOMER));
        $technicianId = array_key_exists('technician_id', $overrides) ? $overrides['technician_id'] : null;
        $status = (string) ($overrides['status'] ?? 'new');
        $priority = (string) ($overrides['priority'] ?? 'normal');
        $createdAt = (string) ($overrides['created_at'] ?? gmdate('Y-m-d H:i:s'));
        $updatedAt = (string) ($overrides['updated_at'] ?? $createdAt);
        $number = (string) ($overrides['public_number'] ?? 'TASK005-' . strtoupper(bin2hex(random_bytes(4))));

        $statement = $this->pdo()->prepare(
            'INSERT INTO repair_requests
                (public_number, customer_id, technician_id, status, priority, device_type,
                 device_brand, device_model, problem_description, customer_contact,
                 created_at, updated_at, completed_at)
             VALUES
                (:public_number, :customer_id, :technician_id, :status, :priority, :device_type,
                 :device_brand, :device_model, :problem, :contact,
                 :created_at, :updated_at, :completed_at)'
        );
        $statement->execute([
            'public_number' => $number,
            'customer_id' => $customerId,
            'technician_id' => $technicianId,
            'status' => $status,
            'priority' => $priority,
            'device_type' => 'Ноутбук',
            'device_brand' => 'TASK005-Brand',
            'device_model' => (string) ($overrides['device_model'] ?? 'TASK005-Model'),
            'problem' => 'TASK005: тестовая заявка для проверки админского реестра.',
            'contact' => $this->contact('request'),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'completed_at' => $status === 'completed' ? $createdAt : null,
        ]);
        $requestId = (int) $this->pdo()->lastInsertId();

        $this->pdo()->prepare(
            'INSERT INTO request_status_history (request_id, from_status, to_status, reason, changed_by)
             VALUES (:request_id, NULL, :to_status, :reason, :changed_by)'
        )->execute([
            'request_id' => $requestId,
            'to_status' => $status,
            'reason' => 'TASK005: тестовая заявка создана.',
            'changed_by' => $customerId,
        ]);

        return $requestId;
    }

    /** @return array{id: int, email: string} */
    private function createUser(string $role, int $active, string $label): array
    {
        $email = self::USER_PREFIX . $label . '-' . bin2hex(random_bytes(3)) . '@fixpoint.test';

        $statement = $this->pdo()->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, is_active)
             VALUES (:full_name, :email, NULL, :password_hash, :role, :is_active)'
        );
        $statement->execute([
            'full_name' => 'TASK005 ' . $label,
            'email' => $email,
            'password_hash' => password_hash('TASK005!test', PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => $active,
        ]);

        return ['id' => (int) $this->pdo()->lastInsertId(), 'email' => $email];
    }

    private function loginAs(string $email): void
    {
        $response = $this->post('/login', [
            'email' => $email,
            'password' => $this->seedPassword(),
        ]);
        $this->assertSame(302, $response->status());
        $this->assertTrue(app()->auth()->check());
    }

    private function logout(): void
    {
        $response = $this->post('/logout', []);
        $this->assertSame(302, $response->status());
        $this->assertFalse(app()->auth()->check());
    }

    /** @param array<string, string> $data */
    private function post(string $uri, array $data): Response
    {
        $data['_token'] = app()->csrf()->token();

        return app()->handle(Request::create('POST', $uri, $data));
    }

    private function seedPassword(): string
    {
        return (string) Env::get('SEED_PASSWORD', 'FixPoint!Dev1');
    }

    private function contact(string $label): string
    {
        return self::CONTACT_PREFIX . $label . '-' . bin2hex(random_bytes(3));
    }

    private function pdo(): \PDO
    {
        return app()->database()->connection();
    }

    private function userId(string $email): int
    {
        $statement = $this->pdo()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            $this->fail('Seed user not found: ' . $email);
        }

        return (int) $id;
    }

    /** @return array<string, mixed> */
    private function requestRow(int $requestId): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM repair_requests WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $requestId]);
        $row = $statement->fetch();

        return $row === false ? [] : $row;
    }

    private function positionOf(string $needle, string $haystack): int
    {
        $position = strpos($haystack, $needle);

        return $position === false ? PHP_INT_MAX : $position;
    }
}
