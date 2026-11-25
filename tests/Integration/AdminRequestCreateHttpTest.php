<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

/**
 * End-to-end HTTP coverage for administrator request creation. Fixtures use
 * the TASK006- contact prefix and are removed in tearDown so seeded data stays
 * untouched.
 */
final class AdminRequestCreateHttpTest extends IntegrationTestCase
{
    private const CONTACT_PREFIX = 'TASK006-';
    private const USER_PREFIX = 'task006-';
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';
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

    public function testGuestCustomerAndTechnicianCannotUseAdminCreate(): void
    {
        $valid = $this->validPayload('guest', (string) $this->userId(self::CUSTOMER));

        $guestForm = app()->handle(Request::create('GET', '/admin/requests/create'));
        $this->assertSame(302, $guestForm->status());
        $this->assertContains('/login', (string) $guestForm->header('Location'));

        $guestPost = $this->post('/admin/requests/create', $valid);
        $this->assertSame(302, $guestPost->status());
        $this->assertContains('/login', (string) $guestPost->header('Location'));
        $this->assertSame([], $this->requestByContact($valid['customer_contact']));

        foreach ([self::CUSTOMER, self::TECHNICIAN] as $email) {
            $this->loginAs($email);
            $this->assertSame(
                403,
                app()->handle(Request::create('GET', '/admin/requests/create'))->status(),
                $email . ' must not open the admin form'
            );
            $this->assertSame(
                403,
                $this->post('/admin/requests/create', $valid)->status(),
                $email . ' must not create requests through the admin route'
            );
            $this->assertSame([], $this->requestByContact($valid['customer_contact']));
            $this->logout();
        }
    }

    public function testCreateFormHasSingleRegistryCtaAndReusesServices(): void
    {
        $this->loginAs(self::ADMIN);

        $registry = app()->handle(Request::create('GET', '/admin/requests'));
        $this->assertSame(200, $registry->status());
        $this->assertSame(1, substr_count($registry->body(), 'href="/admin/requests/create"'));
        $this->assertContains('Создать заявку', $registry->body());

        $form = app()->handle(Request::create('GET', '/admin/requests/create'));
        $this->assertSame(200, $form->status());
        $body = $form->body();
        $this->assertContains('action="/admin/requests/create"', $body);
        $this->assertContains('name="_token"', $body);
        $this->assertContains('name="customer_id"', $body);
        $this->assertContains('name="device_type"', $body);
        $this->assertContains('name="service_id"', $body);
        $this->assertContains('name="problem_description"', $body);
        $this->assertContains('name="customer_contact"', $body);
        $this->assertContains('<optgroup label=', $body);
        $this->assertContains('Алексей Ковалёв', $body);
        $this->assertContains('Услуги сгруппированы по категориям', $body);
    }

    public function testAdminCreatesRequestForCustomerAndBothCabinetsSeeIt(): void
    {
        $this->loginAs(self::ADMIN);
        $payload = $this->validPayload('create', (string) $this->userId(self::CUSTOMER));
        $serviceId = $this->firstServiceId();
        $payload['service_id'] = (string) $serviceId;

        $response = $this->post('/admin/requests/create', $payload);
        $this->assertSame(302, $response->status());
        $this->assertSame('/admin/requests', (string) $response->header('Location'));

        $row = $this->requestByContact($payload['customer_contact']);
        $this->assertNotSame([], $row);
        $requestId = (int) ($row['id'] ?? 0);
        $number = (string) ($row['public_number'] ?? '');
        $this->assertSame('new', (string) ($row['status'] ?? ''));
        $this->assertSame('normal', (string) ($row['priority'] ?? ''));
        $this->assertSame((string) $this->userId(self::CUSTOMER), (string) ($row['customer_id'] ?? ''));
        $this->assertNull($row['technician_id'] ?? null);
        $this->assertSame((string) $serviceId, (string) ($row['service_id'] ?? ''));
        $this->assertSame(1, preg_match('/^FP-\d{4}-\d{6}$/', $number));

        $history = $this->pdo()->prepare(
            'SELECT from_status, to_status, reason, changed_by
             FROM request_status_history WHERE request_id = :id ORDER BY id ASC'
        );
        $history->execute(['id' => $requestId]);
        $events = $history->fetchAll();
        $this->assertCount(1, $events);
        $this->assertSame(null, $events[0]['from_status']);
        $this->assertSame('new', $events[0]['to_status']);
        $this->assertSame('Заявка создана администратором.', $events[0]['reason']);
        $this->assertSame((string) $this->userId(self::ADMIN), (string) $events[0]['changed_by']);

        $registry = app()->handle(Request::create('GET', '/admin/requests?q=' . rawurlencode($number)));
        $this->assertSame(200, $registry->status());
        $registryBody = $registry->body();
        $this->assertContains($number, $registryBody);
        $this->assertContains('TASK006-Brand', $registryBody);
        $this->assertContains('Алексей Ковалёв', $registryBody);
        $this->assertContains('Не назначен', $registryBody);
        $this->assertContains('/admin/requests/' . $requestId . '/assign', $registryBody);

        $this->logout();
        $this->loginAs(self::CUSTOMER);
        $customerCard = app()->handle(Request::create('GET', '/requests/' . $requestId));
        $this->assertSame(200, $customerCard->status());
        $this->assertContains($number, $customerCard->body());
        $this->assertContains($payload['problem_description'], $customerCard->body());
    }

    public function testCreateRequiresCsrfTokenAndWritesNothing(): void
    {
        $this->loginAs(self::ADMIN);
        $payload = $this->validPayload('csrf', (string) $this->userId(self::CUSTOMER));

        $response = app()->handle(Request::create('POST', '/admin/requests/create', $payload));
        $this->assertSame(419, $response->status());
        $this->assertSame([], $this->requestByContact($payload['customer_contact']));
    }

    public function testInvalidCustomerIsRejectedWithoutWriting(): void
    {
        $this->loginAs(self::ADMIN);

        $inactiveId = $this->createUser('customer', 0, 'inactive');
        $technicianId = $this->userId(self::TECHNICIAN);
        $attempts = [
            'missing' => '999999999',
            'inactive' => (string) $inactiveId,
            'technician' => (string) $technicianId,
        ];

        foreach ($attempts as $label => $customerId) {
            $payload = $this->validPayload('bad-customer-' . $label, $customerId);
            $response = $this->post('/admin/requests/create', $payload);
            $this->assertSame(302, $response->status());
            $this->assertSame('/admin/requests/create', (string) $response->header('Location'));
            $this->assertSame([], $this->requestByContact($payload['customer_contact']));

            $form = app()->handle(Request::create('GET', '/admin/requests/create'));
            $this->assertSame(200, $form->status());
            $this->assertContains('Выберите активного клиента из списка.', $form->body());
        }
    }

    public function testInvalidFieldsAreRejectedAndOldInputIsKept(): void
    {
        $this->loginAs(self::ADMIN);

        $response = $this->post('/admin/requests/create', [
            'customer_id' => (string) $this->userId(self::CUSTOMER),
            'device_type' => '',
            'device_brand' => 'TASK006-OLD-BRAND',
            'device_model' => '',
            'service_id' => '999999',
            'problem_description' => 'Коротко',
            'customer_contact' => 'x',
        ]);

        $this->assertSame(302, $response->status());
        $this->assertSame('/admin/requests/create', (string) $response->header('Location'));
        $this->assertSame([], $this->requestByContact('x'));

        $form = app()->handle(Request::create('GET', '/admin/requests/create'));
        $body = $form->body();
        $this->assertSame(200, $form->status());
        $this->assertContains('Укажите тип устройства.', $body);
        $this->assertContains('Опишите проблему подробнее', $body);
        $this->assertContains('Выбранная услуга недоступна.', $body);
        $this->assertContains('Укажите контакт длиной от 3 до 80 символов.', $body);
        $this->assertContains('TASK006-OLD-BRAND', $body);

        $count = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM repair_requests WHERE customer_contact = :contact'
        );
        $count->execute(['contact' => 'x']);
        $this->assertSame(0, (int) $count->fetchColumn());
    }

    /** @return array<string, string> */
    private function validPayload(string $label, string $customerId): array
    {
        return [
            'customer_id' => $customerId,
            'device_type' => 'Ноутбук',
            'device_brand' => 'TASK006-Brand',
            'device_model' => 'TASK006-Model ' . $label,
            'service_id' => (string) $this->firstServiceId(),
            'problem_description' => 'Клиент сообщает, что ноутбук выключается под нагрузкой. Нужна диагностика.',
            'customer_contact' => self::CONTACT_PREFIX . $label . '-' . bin2hex(random_bytes(3)),
        ];
    }

    private function firstServiceId(): int
    {
        $statement = $this->pdo()->query(
            'SELECT s.id FROM services s
             INNER JOIN service_categories c ON c.id = s.category_id
             WHERE s.is_active = 1 AND c.is_active = 1
             ORDER BY s.id ASC LIMIT 1'
        );
        $id = $statement->fetchColumn();
        if ($id === false) {
            $this->fail('No active service fixture found.');
        }

        return (int) $id;
    }

    /** @return array<string, mixed> */
    private function requestByContact(string $contact): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM repair_requests WHERE customer_contact = :contact ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['contact' => $contact]);
        $row = $statement->fetch();

        return $row === false ? [] : $row;
    }

    private function createUser(string $role, int $active, string $label): int
    {
        $email = self::USER_PREFIX . $label . '-' . bin2hex(random_bytes(3)) . '@fixpoint.test';
        $statement = $this->pdo()->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, is_active)
             VALUES (:full_name, :email, NULL, :password_hash, :role, :is_active)'
        );
        $statement->execute([
            'full_name' => 'TASK006 ' . $label,
            'email' => $email,
            'password_hash' => password_hash('TASK006!test', PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => $active,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function loginAs(string $email): void
    {
        $response = $this->post('/login', [
            'email' => $email,
            'password' => (string) Env::get('SEED_PASSWORD', 'FixPoint!Dev1'),
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

    private function pdo(): \PDO
    {
        return app()->database()->connection();
    }
}
