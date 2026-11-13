<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

/**
 * End-to-end HTTP tests for the customer request flow: scoped lists, creation,
 * ownership, comments, internal-note hiding, estimate decisions, CSRF and RBAC.
 * Every fixture uses the TASK003- contact prefix and is removed in tearDown so
 * the idempotent seed data stays untouched.
 */
final class RequestHttpTest extends IntegrationTestCase
{
    private const CONTACT_PREFIX = 'TASK003-';
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

        parent::tearDown();
    }

    public function testGuestIsRedirectedFromCustomerRequestRoutes(): void
    {
        $requests = app()->handle(Request::create('GET', '/requests'));
        $this->assertSame(302, $requests->status());
        $this->assertContains('return_to=%2Frequests', (string) $requests->header('Location'));

        $create = app()->handle(Request::create('GET', '/requests/create'));
        $this->assertSame(302, $create->status());
        $this->assertContains('return_to=%2Frequests%2Fcreate', (string) $create->header('Location'));

        $seedId = $this->seedRequestId('FP-2026-000001');
        $show = app()->handle(Request::create('GET', '/requests/' . $seedId));
        $this->assertSame(302, $show->status());
        $this->assertContains('return_to=%2Frequests%2F' . $seedId, (string) $show->header('Location'));

        $post = app()->handle(Request::create('POST', '/requests/create', [
            'device_type' => 'Ноутбук',
            'problem_description' => 'Гостевая попытка создать заявку без входа.',
            'customer_contact' => $this->contact('guest'),
        ]));
        $this->assertSame(302, $post->status());
        $this->assertContains('/login', (string) $post->header('Location'));
        $this->assertSame(0, $this->fixtureRequestCount());
    }

    public function testCustomerListIsScopedAndSupportsFilters(): void
    {
        $this->loginAs(self::CUSTOMER);

        $all = app()->handle(Request::create('GET', '/requests'));
        $body = $all->body();
        $this->assertSame(200, $all->status());
        $this->assertContains('FP-2026-000001', $body);
        $this->assertContains('FP-2026-000003', $body);
        $this->assertNotContains('FP-2026-000002', $body);
        $this->assertNotContains('FP-2026-000004', $body);

        $filtered = app()->handle(Request::create('GET', '/requests?status=awaiting_customer_approval'))->body();
        $this->assertContains('FP-2026-000003', $filtered);
        $this->assertNotContains('FP-2026-000001', $filtered);
        $this->assertNotContains('FP-2026-000002', $filtered);

        $byBrand = app()->handle(Request::create('GET', '/requests?q=ASUS'))->body();
        $this->assertContains('FP-2026-000001', $byBrand);
        $this->assertNotContains('FP-2026-000003', $byBrand);

        $byNumber = app()->handle(Request::create('GET', '/requests?q=000003'))->body();
        $this->assertContains('FP-2026-000003', $byNumber);
        $this->assertNotContains('FP-2026-000001', $byNumber);

        $oldest = app()->handle(Request::create('GET', '/requests?sort=oldest'))->body();
        $newest = app()->handle(Request::create('GET', '/requests?sort=newest'))->body();
        $this->assertTrue(
            $this->positionOf('FP-2026-000001', $oldest) < $this->positionOf('FP-2026-000003', $oldest),
            'Oldest sorting must place the earlier request first.'
        );
        $this->assertTrue(
            $this->positionOf('FP-2026-000003', $newest) < $this->positionOf('FP-2026-000001', $newest),
            'Newest sorting must place the later request first.'
        );

        $invalid = app()->handle(Request::create('GET', '/requests?status=not-a-status&sort=nope'));
        $this->assertSame(200, $invalid->status());
        $this->assertContains('FP-2026-000001', $invalid->body());
        $this->assertContains('FP-2026-000003', $invalid->body());
    }

    public function testCustomerCannotSeeAnotherCustomersRequest(): void
    {
        $foreignId = $this->seedRequestId('FP-2026-000003');
        $ownId = $this->seedRequestId('FP-2026-000002');

        $this->loginAs(self::OTHER_CUSTOMER);

        $list = app()->handle(Request::create('GET', '/requests'))->body();
        $this->assertContains('FP-2026-000002', $list);
        $this->assertContains('FP-2026-000004', $list);
        $this->assertNotContains('FP-2026-000001', $list);
        $this->assertNotContains('FP-2026-000003', $list);

        $this->assertSame(404, app()->handle(Request::create('GET', '/requests/' . $foreignId))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/requests/' . $this->seedRequestId('FP-2026-000001')))->status());
        $this->assertSame(200, app()->handle(Request::create('GET', '/requests/' . $ownId))->status());

        $commentsBefore = $this->commentCount($foreignId);
        $comment = $this->post('/requests/' . $foreignId . '/comments', ['body' => 'Чужой комментарий']);
        $this->assertSame(404, $comment->status());
        $this->assertSame($commentsBefore, $this->commentCount($foreignId));

        $this->logout();
        $this->loginAs(self::TECHNICIAN);
        $this->assertSame(403, app()->handle(Request::create('GET', '/requests'))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/requests/' . $foreignId))->status());
        $assigned = app()->handle(Request::create('GET', '/requests/' . $this->seedRequestId('FP-2026-000001')));
        $this->assertSame(200, $assigned->status());
        $this->assertContains('Ноутбук шумит и нагревается при работе.', $assigned->body());

        $this->logout();
        $this->loginAs(self::ADMIN);
        $this->assertSame(403, app()->handle(Request::create('GET', '/requests'))->status());
        $this->assertSame(200, app()->handle(Request::create('GET', '/requests/' . $foreignId))->status());
    }

    public function testCreateRequestValidatesAndPersistsHistory(): void
    {
        $this->loginAs(self::CUSTOMER);

        $form = app()->handle(Request::create('GET', '/requests/create'));
        $this->assertSame(200, $form->status());
        $this->assertContains('action="/requests/create"', $form->body());
        $this->assertContains('name="_token"', $form->body());
        $this->assertContains('name="problem_description"', $form->body());

        $contact = $this->contact('create');
        $problem = 'Ноутбук перезагружается под нагрузкой, нужна диагностика.';
        $response = $this->post('/requests/create', [
            'device_type' => 'Ноутбук',
            'device_brand' => 'TASK003-Brand',
            'device_model' => 'TASK003-Model',
            'service_id' => (string) $this->firstServiceId(),
            'problem_description' => $problem,
            'customer_contact' => $contact,
        ]);

        $this->assertSame(302, $response->status());
        $location = (string) $response->header('Location');
        $this->assertSame(1, preg_match('#^/requests/\d+$#', $location));
        $requestId = (int) basename($location);

        $detail = app()->handle(Request::create('GET', $location));
        $body = $detail->body();
        $this->assertSame(200, $detail->status());
        $this->assertContains($problem, $body);
        $this->assertContains($contact, $body);
        $this->assertContains('TASK003-Brand', $body);
        $this->assertContains('Заявка создана клиентом.', $body);
        $this->assertContains('Новая', $body);

        $row = $this->requestRow($requestId);
        $this->assertNotSame(false, $row);
        $this->assertSame('new', $row['status']);
        $this->assertSame('normal', $row['priority']);
        $this->assertSame((string) $this->userId(self::CUSTOMER), (string) $row['customer_id']);
        $this->assertSame(1, preg_match('/^FP-\d{4}-\d{6}$/', (string) $row['public_number']));

        $history = $this->pdo()->prepare(
            'SELECT from_status, to_status, reason, changed_by
             FROM request_status_history WHERE request_id = :id ORDER BY id ASC'
        );
        $history->execute(['id' => $requestId]);
        $events = $history->fetchAll();
        $this->assertCount(1, $events);
        $this->assertSame(null, $events[0]['from_status']);
        $this->assertSame('new', $events[0]['to_status']);
        $this->assertSame('Заявка создана клиентом.', $events[0]['reason']);
        $this->assertSame((string) $this->userId(self::CUSTOMER), (string) $events[0]['changed_by']);

        $list = app()->handle(Request::create('GET', '/requests'))->body();
        $this->assertContains((string) $row['public_number'], $list);

        $dashboard = app()->handle(Request::create('GET', '/account'))->body();
        $this->assertContains((string) $row['public_number'], $dashboard);
    }

    public function testCreateValidationKeepsInputButNeverWrites(): void
    {
        $this->loginAs(self::CUSTOMER);

        $response = $this->post('/requests/create', [
            'device_type' => '',
            'device_brand' => 'TASK003-OLD-BRAND',
            'device_model' => '',
            'service_id' => '999999',
            'problem_description' => 'short',
            'customer_contact' => '',
        ]);

        $this->assertSame(302, $response->status());
        $this->assertSame('/requests/create', $response->header('Location'));

        $page = app()->handle(Request::create('GET', '/requests/create'))->body();
        $this->assertContains('Укажите тип устройства.', $page);
        $this->assertContains('Выбранная услуга недоступна.', $page);
        $this->assertContains('не менее 10 символов', $page);
        $this->assertContains('Укажите контакт для связи.', $page);
        $this->assertContains('TASK003-OLD-BRAND', $page);
        $this->assertSame(0, $this->fixtureRequestCount());
    }

    public function testCreateRequiresCsrfForAuthenticatedCustomer(): void
    {
        $this->loginAs(self::CUSTOMER);

        $response = app()->handle(Request::create('POST', '/requests/create', [
            'device_type' => 'Ноутбук',
            'problem_description' => 'Запрос без CSRF-токена не должен создавать заявку.',
            'customer_contact' => $this->contact('csrf'),
        ]));

        $this->assertSame(419, $response->status());
        $this->assertSame(0, $this->fixtureRequestCount());
    }

    public function testCommentsAreVisibleAndInternalNotesAreHidden(): void
    {
        $this->loginAs(self::CUSTOMER);
        $requestId = $this->createRequest('comments');

        $comment = $this->post('/requests/' . $requestId . '/comments', [
            'body' => 'Публичный комментарий <script>alert(1)</script> TASK003.',
        ]);
        $this->assertSame(302, $comment->status());
        $this->assertContains('/requests/' . $requestId . '#comments', (string) $comment->header('Location'));

        $page = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertContains('Публичный комментарий', $page);
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $page);
        $this->assertNotContains('<script>alert(1)</script>', $page);
        $this->assertContains('Алексей Ковалёв', $page);

        $this->pdo()->prepare(
            'INSERT INTO request_comments (request_id, author_id, body, is_internal)
             VALUES (:request_id, :author_id, :body, 1)'
        )->execute([
            'request_id' => $requestId,
            'author_id' => $this->userId(self::TECHNICIAN),
            'body' => 'Внутренняя заметка TASK003: не показывать клиенту.',
        ]);
        $withInternal = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertNotContains('Внутренняя заметка TASK003', $withInternal);

        $seedPage = app()->handle(Request::create('GET', '/requests/' . $this->seedRequestId('FP-2026-000001')))->body();
        $this->assertContains('Спасибо, буду ждать.', $seedPage);
        $this->assertNotContains('Клиент просил не звонить до 18:00.', $seedPage);

        $empty = $this->post('/requests/' . $requestId . '/comments', ['body' => '   ']);
        $this->assertSame(302, $empty->status());
        $this->assertContains('Введите комментарий.', app()->handle(Request::create('GET', '/requests/' . $requestId))->body());
    }

    public function testEstimateApprovalMovesRequestToRepairing(): void
    {
        $this->loginAs(self::CUSTOMER);
        $requestId = $this->createEstimateFixture();

        $page = app()->handle(Request::create('GET', '/requests/' . $requestId));
        $this->assertSame(200, $page->status());
        $this->assertContains('Согласовать предложенную смету?', $page->body());
        $this->assertContains('25 000 ₸', $page->body());

        $decision = $this->post('/requests/' . $requestId . '/estimate-decision', ['decision' => 'approve']);
        $this->assertSame(302, $decision->status());
        $this->assertSame('/requests/' . $requestId, $decision->header('Location'));

        $this->assertSame('repairing', $this->requestRow($requestId)['status']);
        $history = $this->lastHistory($requestId);
        $this->assertSame('awaiting_customer_approval', $history['from_status']);
        $this->assertSame('repairing', $history['to_status']);
        $this->assertSame('Клиент согласовал смету.', $history['reason']);
        $this->assertSame((string) $this->userId(self::CUSTOMER), (string) $history['changed_by']);

        $updated = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertContains('Клиент согласовал смету.', $updated);
        $this->assertContains('В ремонте', $updated);
        $this->assertNotContains('Согласовать предложенную смету?', $updated);
    }

    public function testEstimateRejectionCancelsRequest(): void
    {
        $this->loginAs(self::CUSTOMER);
        $requestId = $this->createEstimateFixture();

        $decision = $this->post('/requests/' . $requestId . '/estimate-decision', ['decision' => 'reject']);
        $this->assertSame(302, $decision->status());

        $this->assertSame('cancelled', $this->requestRow($requestId)['status']);
        $history = $this->lastHistory($requestId);
        $this->assertSame('awaiting_customer_approval', $history['from_status']);
        $this->assertSame('cancelled', $history['to_status']);
        $this->assertSame('Клиент отклонил смету.', $history['reason']);

        $page = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertContains('Клиент отклонил смету.', $page);
        $this->assertContains('Отменена', $page);
        $this->assertNotContains('Согласовать предложенную смету?', $page);
    }

    public function testEstimateDecisionIsRestrictedAndValidated(): void
    {
        $this->loginAs(self::CUSTOMER);
        $requestId = $this->createEstimateFixture();
        $customerId = $this->userId(self::CUSTOMER);
        $technicianId = $this->userId(self::TECHNICIAN);

        $this->pdo()->prepare('UPDATE repair_requests SET technician_id = :tech WHERE id = :id')
            ->execute(['tech' => $technicianId, 'id' => $requestId]);

        $this->logout();
        $this->loginAs(self::TECHNICIAN);
        $this->assertSame(403, $this->post('/requests/' . $requestId . '/estimate-decision', ['decision' => 'approve'])->status());
        $this->assertSame('awaiting_customer_approval', $this->requestRow($requestId)['status']);
        $this->logout();

        $this->loginAs(self::ADMIN);
        $this->assertSame(403, $this->post('/requests/' . $requestId . '/estimate-decision', ['decision' => 'approve'])->status());
        $this->assertSame('awaiting_customer_approval', $this->requestRow($requestId)['status']);
        $this->logout();

        $this->loginAs(self::OTHER_CUSTOMER);
        $this->assertSame(404, $this->post('/requests/' . $requestId . '/estimate-decision', ['decision' => 'approve'])->status());
        $this->assertSame('awaiting_customer_approval', $this->requestRow($requestId)['status']);
        $this->logout();

        $this->loginAs(self::CUSTOMER);
        $invalid = $this->post('/requests/' . $requestId . '/estimate-decision', ['decision' => 'maybe']);
        $this->assertSame(302, $invalid->status());
        $this->assertContains('Выберите решение по смете.', app()->handle(Request::create('GET', '/requests/' . $requestId))->body());
        $this->assertSame('awaiting_customer_approval', $this->requestRow($requestId)['status']);

        $noToken = app()->handle(Request::create('POST', '/requests/' . $requestId . '/estimate-decision', [
            'decision' => 'approve',
        ]));
        $this->assertSame(419, $noToken->status());
        $this->assertSame('awaiting_customer_approval', $this->requestRow($requestId)['status']);

        $plainId = $this->createRequest('wrong-status');
        $wrongStatus = $this->post('/requests/' . $plainId . '/estimate-decision', ['decision' => 'approve']);
        $this->assertSame(302, $wrongStatus->status());
        $this->assertContains(
            'Смета уже обработана или заявка находится в другом статусе.',
            app()->handle(Request::create('GET', '/requests/' . $plainId))->body()
        );
        $this->assertSame('new', $this->requestRow($plainId)['status']);
        $this->assertSame((string) $customerId, (string) $this->requestRow($plainId)['customer_id']);
    }

    public function testRequestListPaginatesEightPerPage(): void
    {
        $this->loginAs(self::CUSTOMER);
        $customerId = $this->userId(self::CUSTOMER);
        $suffix = bin2hex(random_bytes(3));

        $insert = $this->pdo()->prepare(
            'INSERT INTO repair_requests
                (public_number, customer_id, status, priority, device_type, device_brand,
                 device_model, problem_description, customer_contact)
             VALUES
                (:number, :customer_id, \'new\', \'normal\', \'Ноутбук\', \'QA\',
                 :model, :problem, :contact)'
        );
        for ($index = 1; $index <= 9; $index++) {
            $insert->execute([
                'number' => sprintf('TASK003-%s-%02d', $suffix, $index),
                'customer_id' => $customerId,
                'model' => 'PAGE-' . $index,
                'problem' => 'Пагинация TASK003, запись ' . $index . '.',
                'contact' => $this->contact('page-' . $suffix . '-' . $index),
            ]);
        }

        $first = app()->handle(Request::create('GET', '/requests'))->body();
        $this->assertContains('Страница 1 из 2', $first);
        $this->assertSame(8, substr_count($first, '<article class="request-card">'));
        $this->assertNotContains('FP-2026-000001', $first);

        $second = app()->handle(Request::create('GET', '/requests?page=2'))->body();
        $this->assertContains('Страница 2 из 2', $second);
        $this->assertSame(3, substr_count($second, '<article class="request-card">'));
        $this->assertContains('FP-2026-000001', $second);
        $this->assertContains('FP-2026-000003', $second);

        $clamped = app()->handle(Request::create('GET', '/requests?page=999'))->body();
        $this->assertContains('Страница 2 из 2', $clamped);

        $invalid = app()->handle(Request::create('GET', '/requests?page=abc'))->body();
        $this->assertContains('Страница 1 из 2', $invalid);

        $zero = app()->handle(Request::create('GET', '/requests?page=0'))->body();
        $this->assertContains('Страница 1 из 2', $zero);
    }

    public function testNonNumericRequestIdIsNotFound(): void
    {
        $this->loginAs(self::CUSTOMER);

        $this->assertSame(404, app()->handle(Request::create('GET', '/requests/not-a-number'))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/requests/999999999'))->status());
    }

    private function createRequest(string $label): int
    {
        $response = $this->post('/requests/create', [
            'device_type' => 'Ноутбук',
            'device_brand' => 'TASK003-Fixture',
            'device_model' => 'Fixture-' . $label,
            'service_id' => '',
            'problem_description' => 'Тестовая заявка TASK003 для сценария ' . $label . '.',
            'customer_contact' => $this->contact($label),
        ]);

        $this->assertSame(302, $response->status());
        $location = (string) $response->header('Location');
        $this->assertSame(1, preg_match('#^/requests/(\d+)$#', $location, $matches));

        return (int) $matches[1];
    }

    private function createEstimateFixture(): int
    {
        $requestId = $this->createRequest('estimate-' . bin2hex(random_bytes(3)));

        $this->pdo()->prepare(
            'UPDATE repair_requests
             SET status = \'awaiting_customer_approval\',
                 estimated_cost = 25000.00,
                 technician_id = :technician_id,
                 diagnostic_result = \'TASK003: требуется замена матрицы.\'
             WHERE id = :id'
        )->execute([
            'technician_id' => $this->userId(self::TECHNICIAN),
            'id' => $requestId,
        ]);

        $this->pdo()->prepare(
            'INSERT INTO request_status_history (request_id, from_status, to_status, reason, changed_by)
             VALUES (:request_id, \'new\', \'awaiting_customer_approval\', :reason, :changed_by)'
        )->execute([
            'request_id' => $requestId,
            'reason' => 'Смета сформирована мастером.',
            'changed_by' => $this->userId(self::TECHNICIAN),
        ]);

        return $requestId;
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

    private function firstServiceId(): int
    {
        $id = $this->pdo()->query('SELECT id FROM services ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($id === false) {
            $this->fail('No services found in the test database.');
        }

        return (int) $id;
    }

    private function seedRequestId(string $publicNumber): int
    {
        $statement = $this->pdo()->prepare('SELECT id FROM repair_requests WHERE public_number = :number LIMIT 1');
        $statement->execute(['number' => $publicNumber]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            $this->fail('Seed request not found: ' . $publicNumber);
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

    /** @return array<string, mixed> */
    private function lastHistory(int $requestId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT from_status, to_status, reason, changed_by
             FROM request_status_history WHERE request_id = :id ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['id' => $requestId]);
        $row = $statement->fetch();

        return $row === false ? [] : $row;
    }

    private function commentCount(int $requestId): int
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM request_comments WHERE request_id = :id');
        $statement->execute(['id' => $requestId]);

        return (int) $statement->fetchColumn();
    }

    private function fixtureRequestCount(): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM repair_requests WHERE customer_contact LIKE :prefix'
        );
        $statement->execute(['prefix' => self::CONTACT_PREFIX . '%']);

        return (int) $statement->fetchColumn();
    }

    private function positionOf(string $needle, string $haystack): int
    {
        $position = strpos($haystack, $needle);
        $this->assertNotSame(false, $position, 'Expected to find ' . $needle . ' in the page.');

        return (int) $position;
    }
}
