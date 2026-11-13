<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

/**
 * End-to-end HTTP tests for the technician workspace: scoped queue, filters,
 * pagination, diagnosis/estimate persistence, controlled status transitions,
 * internal notes and staff/admin authorization. Fixtures use the TASK004-
 * contact prefix and are removed in tearDown so seed data stays untouched.
 */
final class TechnicianHttpTest extends IntegrationTestCase
{
    private const CONTACT_PREFIX = 'TASK004-';
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

    public function testGuestIsRedirectedFromStaffRoutes(): void
    {
        $requestId = $this->createFixture();

        $queue = app()->handle(Request::create('GET', '/technician/requests'));
        $this->assertSame(302, $queue->status());
        $this->assertContains('/login', (string) $queue->header('Location'));
        $this->assertContains('return_to=%2Ftechnician%2Frequests', (string) $queue->header('Location'));

        $detail = app()->handle(Request::create('GET', '/technician/requests/' . $requestId));
        $this->assertSame(302, $detail->status());
        $this->assertContains('return_to=%2Ftechnician%2Frequests%2F' . $requestId, (string) $detail->header('Location'));

        $post = app()->handle(Request::create('POST', '/technician/requests/' . $requestId . '/status', [
            'status' => 'accepted',
        ]));
        $this->assertSame(302, $post->status());
        $this->assertContains('/login', (string) $post->header('Location'));
        $this->assertSame('new', $this->requestRow($requestId)['status']);
    }

    public function testCustomerAndUnassignedTechnicianCannotUseStaffWorkspace(): void
    {
        $this->loginAs(self::CUSTOMER);
        $this->assertSame(403, app()->handle(Request::create('GET', '/technician/requests'))->status());
        $this->assertSame(403, $this->post('/technician/requests/' . $this->seedRequestId('FP-2026-000001') . '/diagnosis', [
            'diagnostic_result' => 'Клиент не должен менять диагностику.',
        ])->status());
        $this->logout();

        $fixture = $this->createFixture(['status' => 'repairing']);
        $this->loginAs(self::OTHER_TECHNICIAN);

        $queue = app()->handle(Request::create('GET', '/technician/requests'));
        $body = $queue->body();
        $this->assertSame(200, $queue->status());
        $this->assertContains('FP-2026-000003', $body);
        $this->assertNotContains('FP-2026-000001', $body);
        $this->assertNotContains('FP-2026-000004', $body);

        $this->assertSame(404, app()->handle(Request::create('GET', '/technician/requests/' . $this->seedRequestId('FP-2026-000001')))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/technician/requests/' . $fixture))->status());
        $this->assertSame(200, app()->handle(Request::create('GET', '/technician/requests/' . $this->seedRequestId('FP-2026-000003')))->status());

        $diagnosis = $this->post('/technician/requests/' . $fixture . '/diagnosis', [
            'diagnostic_result' => 'Чужой мастер не должен сохранить диагностику.',
            'estimated_cost' => '1000',
        ]);
        $this->assertSame(404, $diagnosis->status());
        $this->assertSame(null, $this->requestRow($fixture)['diagnostic_result']);

        $status = $this->post('/technician/requests/' . $fixture . '/status', ['status' => 'accepted']);
        $this->assertSame(404, $status->status());
        $this->assertSame('repairing', $this->requestRow($fixture)['status']);

        $commentsBefore = $this->commentCount($fixture);
        $this->assertSame(404, $this->post('/requests/' . $fixture . '/comments', [
            'body' => 'Чужой комментарий TASK004.',
        ])->status());
        $this->assertSame($commentsBefore, $this->commentCount($fixture));
    }

    public function testQueueIsScopedAndSupportsFilters(): void
    {
        $high = $this->createFixture([
            'priority' => 'high',
            'device_model' => 'TASK004-HighModel',
            'created_at' => '2020-01-02 10:00:00',
        ]);
        $urgent = $this->createFixture([
            'priority' => 'urgent',
            'device_model' => 'TASK004-UrgentModel',
            'created_at' => '2020-01-01 10:00:00',
        ]);
        $low = $this->createFixture([
            'priority' => 'low',
            'device_model' => 'TASK004-LowModel',
            'created_at' => '2020-01-03 10:00:00',
        ]);

        $this->loginAs(self::TECHNICIAN);

        $all = app()->handle(Request::create('GET', '/technician/requests'));
        $body = $all->body();
        $this->assertSame(200, $all->status());
        $this->assertContains('FP-2026-000001', $body);
        $this->assertContains('FP-2026-000004', $body);
        $this->assertNotContains('FP-2026-000002', $body);
        $this->assertNotContains('FP-2026-000003', $body);
        $this->assertContains('TASK004-HighModel', $body);
        $this->assertContains('TASK004-UrgentModel', $body);
        $this->assertContains('TASK004-LowModel', $body);

        $completed = app()->handle(Request::create('GET', '/technician/requests?status=completed'))->body();
        $this->assertContains('FP-2026-000004', $completed);
        $this->assertNotContains('FP-2026-000001', $completed);
        $this->assertNotContains('TASK004-HighModel', $completed);

        $highOnly = app()->handle(Request::create('GET', '/technician/requests?priority=high'))->body();
        $this->assertContains('TASK004-HighModel', $highOnly);
        $this->assertNotContains('TASK004-UrgentModel', $highOnly);
        $this->assertNotContains('TASK004-LowModel', $highOnly);
        $this->assertNotContains('FP-2026-000001', $highOnly);

        $byNumber = app()->handle(Request::create('GET', '/technician/requests?q=000004'))->body();
        $this->assertContains('FP-2026-000004', $byNumber);
        $this->assertNotContains('FP-2026-000001', $byNumber);

        $byCustomer = app()->handle(Request::create('GET', '/technician/requests?q=' . rawurlencode('Иванова')))->body();
        $this->assertContains('FP-2026-000004', $byCustomer);
        $this->assertNotContains('FP-2026-000001', $byCustomer);
        $this->assertNotContains('TASK004-HighModel', $byCustomer);

        $byBrand = app()->handle(Request::create('GET', '/technician/requests?q=ASUS'))->body();
        $this->assertContains('FP-2026-000001', $byBrand);
        $this->assertNotContains('TASK004-HighModel', $byBrand);

        $oldest = app()->handle(Request::create('GET', '/technician/requests?sort=oldest'))->body();
        $newest = app()->handle(Request::create('GET', '/technician/requests?sort=newest'))->body();
        $priority = app()->handle(Request::create('GET', '/technician/requests?sort=priority'))->body();
        $this->assertTrue(
            $this->positionOf('TASK004-UrgentModel', $oldest) < $this->positionOf('TASK004-LowModel', $oldest)
        );
        $this->assertTrue(
            $this->positionOf('TASK004-LowModel', $newest) < $this->positionOf('TASK004-UrgentModel', $newest)
        );
        $this->assertTrue(
            $this->positionOf('TASK004-UrgentModel', $priority) < $this->positionOf('TASK004-HighModel', $priority)
        );
        $this->assertTrue(
            $this->positionOf('TASK004-HighModel', $priority) < $this->positionOf('TASK004-LowModel', $priority)
        );

        $invalid = app()->handle(Request::create('GET', '/technician/requests?status=nope&priority=nope&sort=nope'));
        $this->assertSame(200, $invalid->status());
        $this->assertContains('FP-2026-000001', $invalid->body());

        $empty = app()->handle(Request::create('GET', '/technician/requests?status=rejected'));
        $this->assertSame(200, $empty->status());
        $this->assertContains('В очереди пока нет заявок', $empty->body());
    }

    public function testQueuePaginatesEightPerPage(): void
    {
        $this->loginAs(self::TECHNICIAN);

        for ($index = 1; $index <= 9; $index++) {
            $this->createFixture([
                'device_model' => 'TASK004-Page-' . $index,
                'created_at' => sprintf('2025-01-%02d 10:00:00', $index),
            ]);
        }

        $first = app()->handle(Request::create('GET', '/technician/requests'))->body();
        $this->assertContains('Страница 1 из 2', $first);
        $this->assertSame(8, substr_count($first, '<article class="tech-request-card">'));
        $this->assertContains('FP-2026-000001', $first);
        $this->assertContains('FP-2026-000004', $first);

        $second = app()->handle(Request::create('GET', '/technician/requests?page=2'))->body();
        $this->assertContains('Страница 2 из 2', $second);
        $this->assertSame(3, substr_count($second, '<article class="tech-request-card">'));
        $this->assertContains('TASK004-Page-1', $second);
        $this->assertContains('TASK004-Page-3', $second);

        $clamped = app()->handle(Request::create('GET', '/technician/requests?page=999'))->body();
        $this->assertContains('Страница 2 из 2', $clamped);

        $invalid = app()->handle(Request::create('GET', '/technician/requests?page=abc'))->body();
        $this->assertContains('Страница 1 из 2', $invalid);
    }

    public function testDetailShowsInternalNotesPermissionsAndAllowedTransitions(): void
    {
        $this->loginAs(self::TECHNICIAN);

        $detail = app()->handle(Request::create('GET', '/technician/requests/' . $this->seedRequestId('FP-2026-000001')));
        $body = $detail->body();
        $this->assertSame(200, $detail->status());
        $this->assertContains('Клиент просил не звонить до 18:00.', $body);
        $this->assertContains('Спасибо, буду ждать.', $body);
        $this->assertContains('Внутренняя заметка', $body);
        $this->assertContains('name="is_internal"', $body);
        $this->assertContains('/diagnosis"', $body);
        $this->assertContains('/status"', $body);
        $this->assertContains('<option value="awaiting_parts"', $body);
        $this->assertContains('<option value="ready_for_pickup"', $body);
        $this->assertNotContains('<option value="completed"', $body);

        $completed = app()->handle(Request::create('GET', '/technician/requests/' . $this->seedRequestId('FP-2026-000004')));
        $completedBody = $completed->body();
        $this->assertSame(200, $completed->status());
        $this->assertContains('Завершена', $completedBody);
        $this->assertNotContains('/diagnosis"', $completedBody);
        $this->assertNotContains('/status"', $completedBody);
        $this->assertContains('Для этой заявки сейчас нет доступных переходов статуса', $completedBody);

        $this->logout();
        $this->loginAs(self::ADMIN);
        $adminDetail = app()->handle(Request::create('GET', '/technician/requests/' . $this->seedRequestId('FP-2026-000003')));
        $this->assertSame(200, $adminDetail->status());
        $this->assertContains('Смета отправлена клиенту.', $adminDetail->body());
    }

    public function testDiagnosisPersistsAndValidates(): void
    {
        $requestId = $this->createFixture(['status' => 'diagnostics']);
        $this->loginAs(self::TECHNICIAN);

        $form = app()->handle(Request::create('GET', '/technician/requests/' . $requestId));
        $this->assertSame(200, $form->status());
        $this->assertContains('action="/technician/requests/' . $requestId . '/diagnosis"', $form->body());

        $diagnostic = 'TASK004: обнаружен износ термопасты и загрязнение радиатора.';
        $response = $this->post('/technician/requests/' . $requestId . '/diagnosis', [
            'diagnostic_result' => $diagnostic,
            'estimated_cost' => '15000.00',
            'estimated_ready_at' => '2026-12-01T15:30',
        ]);
        $this->assertSame(302, $response->status());
        $this->assertSame('/technician/requests/' . $requestId, $response->header('Location'));

        $row = $this->requestRow($requestId);
        $this->assertSame($diagnostic, $row['diagnostic_result']);
        $this->assertSame('15000.00', $row['estimated_cost']);
        $this->assertSame('2026-12-01 15:30:00', $row['estimated_ready_at']);

        $detail = app()->handle(Request::create('GET', '/technician/requests/' . $requestId))->body();
        $this->assertContains($diagnostic, $detail);
        $this->assertContains('value="15000.00"', $detail);
        $this->assertContains('Диагностика сохранена.', $detail);

        $cleared = $this->post('/technician/requests/' . $requestId . '/diagnosis', [
            'diagnostic_result' => 'TASK004: повторная диагностика без сметы.',
            'estimated_cost' => '',
            'estimated_ready_at' => '',
        ]);
        $this->assertSame(302, $cleared->status());
        $clearedRow = $this->requestRow($requestId);
        $this->assertSame(null, $clearedRow['estimated_cost']);
        $this->assertSame(null, $clearedRow['estimated_ready_at']);

        $invalid = $this->post('/technician/requests/' . $requestId . '/diagnosis', [
            'diagnostic_result' => 'short',
            'estimated_cost' => 'not-a-number',
            'estimated_ready_at' => 'not-a-date',
        ]);
        $this->assertSame(302, $invalid->status());
        $invalidPage = app()->handle(Request::create('GET', '/technician/requests/' . $requestId))->body();
        $this->assertContains('не менее 10 символов', $invalidPage);
        $this->assertContains('Укажите стоимость числом.', $invalidPage);
        $this->assertContains('Укажите корректную дату готовности.', $invalidPage);
        $this->assertSame('TASK004: повторная диагностика без сметы.', $this->requestRow($requestId)['diagnostic_result']);

        $noToken = app()->handle(Request::create('POST', '/technician/requests/' . $requestId . '/diagnosis', [
            'diagnostic_result' => 'TASK004: запрос без CSRF-токена.',
        ]));
        $this->assertSame(419, $noToken->status());
        $this->assertSame('TASK004: повторная диагностика без сметы.', $this->requestRow($requestId)['diagnostic_result']);

        $terminal = $this->createFixture(['status' => 'completed']);
        $terminalResponse = $this->post('/technician/requests/' . $terminal . '/diagnosis', [
            'diagnostic_result' => 'TASK004: завершённую заявку менять нельзя.',
        ]);
        $this->assertSame(302, $terminalResponse->status());
        $terminalPage = app()->handle(Request::create('GET', '/technician/requests/' . $terminal))->body();
        $this->assertContains('Завершённую заявку нельзя редактировать.', $terminalPage);
        $this->assertSame(null, $this->requestRow($terminal)['diagnostic_result']);
    }

    public function testStatusTransitionsUseMatrixAndHistory(): void
    {
        $requestId = $this->createFixture(['status' => 'repairing']);
        $this->loginAs(self::TECHNICIAN);

        $response = $this->post('/technician/requests/' . $requestId . '/status', [
            'status' => 'awaiting_parts',
            'reason' => 'TASK004: ожидаем поставку запчасти.',
        ]);
        $this->assertSame(302, $response->status());
        $this->assertSame('awaiting_parts', $this->requestRow($requestId)['status']);

        $history = $this->lastHistory($requestId);
        $this->assertSame('repairing', $history['from_status']);
        $this->assertSame('awaiting_parts', $history['to_status']);
        $this->assertSame('TASK004: ожидаем поставку запчасти.', $history['reason']);
        $this->assertSame((string) $this->userId(self::TECHNICIAN), (string) $history['changed_by']);

        $detail = app()->handle(Request::create('GET', '/technician/requests/' . $requestId))->body();
        $this->assertContains('TASK004: ожидаем поставку запчасти.', $detail);
        $this->assertContains('Ожидает запчасти', $detail);

        $invalid = $this->post('/technician/requests/' . $requestId . '/status', [
            'status' => 'completed',
            'reason' => 'TASK004: недопустимый переход.',
        ]);
        $this->assertSame(302, $invalid->status());
        $this->assertSame('awaiting_parts', $this->requestRow($requestId)['status']);
        $this->assertContains(
            'Этот переход статуса недопустим.',
            app()->handle(Request::create('GET', '/technician/requests/' . $requestId))->body()
        );

        $empty = $this->post('/technician/requests/' . $requestId . '/status', ['status' => '']);
        $this->assertSame(302, $empty->status());
        $this->assertSame('awaiting_parts', $this->requestRow($requestId)['status']);

        $noToken = app()->handle(Request::create('POST', '/technician/requests/' . $requestId . '/status', [
            'status' => 'repairing',
        ]));
        $this->assertSame(419, $noToken->status());
        $this->assertSame('awaiting_parts', $this->requestRow($requestId)['status']);

        $terminal = $this->createFixture(['status' => 'completed']);
        $terminalResponse = $this->post('/technician/requests/' . $terminal . '/status', ['status' => 'repairing']);
        $this->assertSame(302, $terminalResponse->status());
        $this->assertSame('completed', $this->requestRow($terminal)['status']);
        $this->assertContains(
            'Завершённую заявку нельзя изменить.',
            app()->handle(Request::create('GET', '/technician/requests/' . $terminal))->body()
        );

        $this->logout();
        $this->loginAs(self::ADMIN);
        $adminFixture = $this->createFixture(['technician_id' => null, 'status' => 'new']);
        $adminResponse = $this->post('/technician/requests/' . $adminFixture . '/status', [
            'status' => 'accepted',
            'reason' => 'TASK004: администратор принял заявку.',
        ]);
        $this->assertSame(302, $adminResponse->status());
        $this->assertSame('accepted', $this->requestRow($adminFixture)['status']);
        $adminHistory = $this->lastHistory($adminFixture);
        $this->assertSame((string) $this->userId(self::ADMIN), (string) $adminHistory['changed_by']);
    }

    public function testInternalCommentsAreHiddenFromCustomers(): void
    {
        $requestId = $this->createFixture(['status' => 'repairing']);
        $this->loginAs(self::TECHNICIAN);

        $internalResponse = $this->post('/requests/' . $requestId . '/comments', [
            'body' => 'TASK004 внутренняя заметка для мастеров.',
            'is_internal' => '1',
        ]);
        $this->assertSame(302, $internalResponse->status());
        $this->assertSame('/technician/requests/' . $requestId . '#comments', $internalResponse->header('Location'));
        $publicResponse = $this->post('/requests/' . $requestId . '/comments', [
            'body' => 'TASK004 публичный комментарий клиенту.',
        ]);
        $this->assertSame(302, $publicResponse->status());
        $this->assertSame('/technician/requests/' . $requestId . '#comments', $publicResponse->header('Location'));

        $staffPage = app()->handle(Request::create('GET', '/technician/requests/' . $requestId))->body();
        $this->assertContains('TASK004 внутренняя заметка для мастеров.', $staffPage);
        $this->assertContains('TASK004 публичный комментарий клиенту.', $staffPage);
        $this->assertContains('Внутренняя заметка', $staffPage);

        $rows = $this->commentRows($requestId);
        $this->assertCount(2, $rows);
        $this->assertSame('1', (string) $rows[0]['is_internal']);
        $this->assertSame('0', (string) $rows[1]['is_internal']);

        $this->logout();
        $this->loginAs(self::CUSTOMER);
        $customerPage = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertContains('TASK004 публичный комментарий клиенту.', $customerPage);
        $this->assertNotContains('TASK004 внутренняя заметка для мастеров.', $customerPage);
        $this->assertNotContains('Внутренняя заметка', $customerPage);

        $this->assertSame(302, $this->post('/requests/' . $requestId . '/comments', [
            'body' => 'TASK004 клиент пытается создать внутреннюю заметку.',
            'is_internal' => '1',
        ])->status());
        $rows = $this->commentRows($requestId);
        $this->assertCount(3, $rows);
        $this->assertSame('0', (string) $rows[2]['is_internal']);
        $this->assertContains(
            'TASK004 клиент пытается создать внутреннюю заметку.',
            app()->handle(Request::create('GET', '/requests/' . $requestId))->body()
        );
    }

    public function testAdminCanOpenStaffDetailsButQueueIsTechnicianOnly(): void
    {
        $fixture = $this->createFixture(['technician_id' => null, 'status' => 'new']);
        $this->loginAs(self::ADMIN);

        $this->assertSame(403, app()->handle(Request::create('GET', '/technician/requests'))->status());

        $detail = app()->handle(Request::create('GET', '/technician/requests/' . $fixture));
        $this->assertSame(200, $detail->status());
        $this->assertContains('Устройство и клиент', $detail->body());
        $this->assertContains('<option value="accepted"', $detail->body());
        $this->assertContains('<option value="rejected"', $detail->body());
        $this->assertContains('<option value="cancelled"', $detail->body());

        $this->assertSame(200, app()->handle(Request::create('GET', '/technician/requests/' . $this->seedRequestId('FP-2026-000002')))->status());

        $service = app()->make(\FixPoint\Services\RequestService::class);
        $adminQueue = $service->listQueue(['id' => $this->userId(self::ADMIN), 'role' => 'admin'], [], 1);
        $this->assertTrue($adminQueue['pagination']['total'] >= 5);
        $this->assertTrue(count($adminQueue['items']) >= 5);
    }

    public function testNonNumericAndUnknownRequestIdsAreNotFound(): void
    {
        $this->loginAs(self::TECHNICIAN);

        $this->assertSame(404, app()->handle(Request::create('GET', '/technician/requests/not-a-number'))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/technician/requests/999999999'))->status());
        $this->assertSame(404, $this->post('/technician/requests/999999999/status', ['status' => 'accepted'])->status());
        $this->assertSame(404, $this->post('/technician/requests/999999999/diagnosis', [
            'diagnostic_result' => 'TASK004: неизвестная заявка.',
        ])->status());
    }

    /** @param array<string, mixed> $overrides */
    private function createFixture(array $overrides = []): int
    {
        $customerId = (int) ($overrides['customer_id'] ?? $this->userId(self::CUSTOMER));
        $technicianId = array_key_exists('technician_id', $overrides)
            ? $overrides['technician_id']
            : $this->userId(self::TECHNICIAN);
        $status = (string) ($overrides['status'] ?? 'new');
        $priority = (string) ($overrides['priority'] ?? 'normal');
        $createdAt = (string) ($overrides['created_at'] ?? gmdate('Y-m-d H:i:s'));
        $number = (string) ($overrides['public_number'] ?? 'TASK004-' . strtoupper(bin2hex(random_bytes(4))));

        $statement = $this->pdo()->prepare(
            'INSERT INTO repair_requests
                (public_number, customer_id, technician_id, status, priority, device_type,
                 device_brand, device_model, problem_description, diagnostic_result,
                 estimated_cost, estimated_ready_at, customer_contact, created_at, updated_at)
             VALUES
                (:public_number, :customer_id, :technician_id, :status, :priority, :device_type,
                 :device_brand, :device_model, :problem, :diagnostic,
                 :estimated_cost, :estimated_ready_at, :contact, :created_at, :updated_at)'
        );
        $statement->execute([
            'public_number' => $number,
            'customer_id' => $customerId,
            'technician_id' => $technicianId,
            'status' => $status,
            'priority' => $priority,
            'device_type' => 'Ноутбук',
            'device_brand' => 'TASK004-Brand',
            'device_model' => (string) ($overrides['device_model'] ?? 'TASK004-Model'),
            'problem' => 'TASK004: тестовая заявка для проверки рабочего места мастера.',
            'diagnostic' => $overrides['diagnostic_result'] ?? null,
            'estimated_cost' => $overrides['estimated_cost'] ?? null,
            'estimated_ready_at' => $overrides['estimated_ready_at'] ?? null,
            'contact' => $this->contact('fixture'),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $requestId = (int) $this->pdo()->lastInsertId();

        $this->pdo()->prepare(
            'INSERT INTO request_status_history (request_id, from_status, to_status, reason, changed_by)
             VALUES (:request_id, NULL, :to_status, :reason, :changed_by)'
        )->execute([
            'request_id' => $requestId,
            'to_status' => $status,
            'reason' => 'TASK004: тестовая заявка создана.',
            'changed_by' => $customerId,
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

    /** @return list<array<string, mixed>> */
    private function commentRows(int $requestId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT body, is_internal FROM request_comments WHERE request_id = :id ORDER BY id ASC'
        );
        $statement->execute(['id' => $requestId]);

        return $statement->fetchAll();
    }

    private function positionOf(string|int $needle, string $haystack): int
    {
        $needle = (string) $needle;
        $position = strpos($haystack, $needle);
        $this->assertNotSame(false, $position, 'Expected to find ' . $needle . ' in the page.');

        return (int) $position;
    }
}
