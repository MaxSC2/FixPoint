<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

/**
 * End-to-end HTTP tests for the administrator statistics workspace. Fixtures
 * live in fixed 2024 periods so they cannot collide with seed requests, and
 * all aggregates are checked against values stored in MySQL. Fixtures use the
 * TASK005D prefixes and are removed in tearDown.
 */
final class AdminStatisticsHttpTest extends IntegrationTestCase
{
    private const CONTACT_PREFIX = 'TASK005D-';
    private const SLUG_PREFIX = 'task005d-';
    private const ADMIN = 'admin@fixpoint.test';
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';

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

        $pdo->prepare('DELETE FROM services WHERE slug LIKE :prefix')
            ->execute(['prefix' => self::SLUG_PREFIX . '%']);

        parent::tearDown();
    }

    public function testGuestIsRedirectedAndNonAdminsAreForbidden(): void
    {
        $guest = app()->handle(Request::create('GET', '/admin/statistics'));
        $this->assertSame(302, $guest->status());
        $this->assertContains('/login', (string) $guest->header('Location'));
        $this->assertContains('return_to=%2Fadmin%2Fstatistics', (string) $guest->header('Location'));

        foreach ([self::CUSTOMER, self::TECHNICIAN] as $email) {
            $this->loginAs($email);
            $this->assertSame(403, app()->handle(Request::create('GET', '/admin/statistics'))->status());
            $this->logout();
        }

        $this->loginAs(self::ADMIN);
        $response = app()->handle(Request::create('GET', '/admin/statistics'));
        $body = $response->body();
        $this->assertSame(200, $response->status());
        $this->assertContains('Статистика сервиса', $body);
        $this->assertContains('Период анализа', $body);
        $this->assertContains('Распределение по статусам', $body);
        $this->assertContains('Заявки по месяцам', $body);
        $this->assertContains('Часто заказываемые услуги', $body);
    }

    public function testSummaryStatusesMonthsAndTopServicesUsePeriodData(): void
    {
        $serviceA = $this->createService('Main');
        $serviceB = $this->createService('Other');

        $this->createRequest('2024-02-10 09:00:00', 'completed', $serviceA);
        $this->createRequest('2024-02-20 12:00:00', 'repairing', $serviceA);
        $this->createRequest('2024-04-05 15:30:00', 'completed', $serviceB);

        $this->loginAs(self::ADMIN);
        $response = app()->handle(
            Request::create('GET', '/admin/statistics?from=2024-02-01&to=2024-04-30')
        );
        $body = $response->body();

        $this->assertSame(200, $response->status());
        $this->assertNotContains('Период указан некорректно', $body);
        $this->assertContains('admin-record-count"><strong>3</strong>', $body);
        $this->assertContains('66,7%', $body);
        $this->assertContains('Завершена', $body);
        $this->assertContains('В ремонте', $body);

        $february = $this->positionOf('Фев 24', $body);
        $march = $this->positionOf('Мар 24', $body);
        $april = $this->positionOf('Апр 24', $body);
        $this->assertTrue($february < $march, 'February must render before the empty March gap');
        $this->assertTrue($march < $april, 'The empty March gap must render before April');
        $this->assertNotContains('Янв 24', $body);
        $this->assertNotContains('Май 24', $body);

        $this->assertContains('TASK005D Rank Main', $body);
        $this->assertContains('TASK005D Rank Other', $body);
        $this->assertTrue(
            $this->positionOf('TASK005D Rank Main', $body) < $this->positionOf('TASK005D Rank Other', $body),
            'Services with more requests must rank higher'
        );

        $this->assertSame(3, (int) $this->scalar(
            'SELECT COUNT(*) FROM repair_requests
             WHERE customer_contact LIKE :prefix
               AND created_at >= "2024-02-01 00:00:00"
               AND created_at < "2024-05-01 00:00:00"',
            ['prefix' => self::CONTACT_PREFIX . '%']
        ));

        $unfiltered = app()->handle(Request::create('GET', '/admin/statistics'))->body();
        $this->assertContains('TASK005D Rank Main', $unfiltered);
        $this->assertGreaterThan(3, $this->recordCount($unfiltered));
    }

    public function testInvalidAndReversedPeriodsShowAnErrorAndFallBackToAllData(): void
    {
        $this->loginAs(self::ADMIN);
        $all = (int) $this->scalar('SELECT COUNT(*) FROM repair_requests');

        $invalid = app()->handle(
            Request::create('GET', '/admin/statistics?from=2024-02-30')
        );
        $invalidBody = $invalid->body();
        $this->assertSame(200, $invalid->status());
        $this->assertContains('Период указан некорректно', $invalidBody);
        $this->assertContains('построен без фильтра', $invalidBody);
        $this->assertSame($all, $this->recordCount($invalidBody));
        $this->assertContains('id="stats-from" type="date" name="from" value=""', $invalidBody);
        $this->assertContains('id="stats-to" type="date" name="to" value=""', $invalidBody);

        $reversed = app()->handle(
            Request::create('GET', '/admin/statistics?from=2024-05-01&to=2024-01-01')
        );
        $reversedBody = $reversed->body();
        $this->assertSame(200, $reversed->status());
        $this->assertContains('Дата начала позже даты окончания', $reversedBody);
        $this->assertContains('построен без фильтра', $reversedBody);
        $this->assertSame($all, $this->recordCount($reversedBody));

        $garbage = app()->handle(
            Request::create('GET', '/admin/statistics?from=not-a-date&to=2024-01-01')
        );
        $this->assertContains('Период указан некорректно', $garbage->body());
        $this->assertSame($all, $this->recordCount($garbage->body()));
    }

    public function testEmptyPeriodRendersExplicitEmptyStates(): void
    {
        $this->loginAs(self::ADMIN);
        $response = app()->handle(
            Request::create('GET', '/admin/statistics?from=2019-01-01&to=2019-12-31')
        );
        $body = $response->body();

        $this->assertSame(200, $response->status());
        $this->assertSame(0, $this->recordCount($body));
        $this->assertContains('Нет данных по статусам', $body);
        $this->assertContains('Пока нет динамики', $body);
        $this->assertContains('Недостаточно данных', $body);
        $this->assertContains('Нет данных для расчёта', $body);
        $this->assertNotContains('Период указан некорректно', $body);
    }

    public function testPeriodBoundariesAreInclusiveForBothDates(): void
    {
        $service = $this->createService('Boundary');
        $this->createRequest('2024-06-30 00:00:00', 'new', $service);
        $this->createRequest('2024-06-30 23:59:59', 'completed', $service);
        $this->createRequest('2024-07-01 00:00:00', 'new', $service);

        $this->loginAs(self::ADMIN);
        $response = app()->handle(
            Request::create('GET', '/admin/statistics?from=2024-06-30&to=2024-06-30')
        );
        $body = $response->body();

        $this->assertSame(200, $response->status());
        $this->assertSame(2, $this->recordCount($body));
        $this->assertContains('Июн 24', $body);
        $this->assertNotContains('Июл 24', $body);
        $this->assertContains('TASK005D Rank Boundary', $body);

        $july = app()->handle(
            Request::create('GET', '/admin/statistics?from=2024-07-01&to=2024-07-01')
        );
        $this->assertSame(1, $this->recordCount($july->body()));
    }

    public function testTopServicesKeepsFiveBusiestAndOrdersTiesByName(): void
    {
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $suffix) {
            $service = $this->createService('Tie ' . $suffix);
            $this->createRequest('2024-08-0' . (ord($suffix) - 64) . ' 10:00:00', 'new', $service);
        }

        $this->loginAs(self::ADMIN);
        $response = app()->handle(
            Request::create('GET', '/admin/statistics?from=2024-08-01&to=2024-08-31')
        );
        $body = $response->body();

        $this->assertSame(200, $response->status());
        foreach (['A', 'B', 'C', 'D', 'E'] as $suffix) {
            $this->assertContains('TASK005D Rank Tie ' . $suffix, $body);
        }
        $this->assertNotContains('TASK005D Rank Tie F', $body);
        $this->assertSame(5, substr_count($body, 'admin-service-ranking__row'));
    }

    public function testStatisticsLinkExistsOnEveryAdminWorkspacePage(): void
    {
        $this->loginAs(self::ADMIN);

        $userId = (int) $this->scalar(
            'SELECT id FROM users WHERE email = :email',
            ['email' => self::ADMIN]
        );
        $serviceId = (int) $this->scalar('SELECT id FROM services ORDER BY id ASC LIMIT 1');
        $categoryId = (int) $this->scalar('SELECT id FROM service_categories ORDER BY id ASC LIMIT 1');
        $requestId = (int) $this->scalar('SELECT id FROM repair_requests ORDER BY id ASC LIMIT 1');

        $uris = [
            '/admin',
            '/admin/requests',
            '/admin/requests/' . $requestId . '/assign',
            '/admin/users',
            '/admin/users/' . $userId . '/edit',
            '/admin/services',
            '/admin/services/' . $serviceId . '/edit',
            '/admin/categories',
            '/admin/categories/' . $categoryId . '/edit',
        ];

        foreach ($uris as $uri) {
            $response = app()->handle(Request::create('GET', $uri));
            $this->assertSame(200, $response->status(), 'Admin page must load: ' . $uri);
            $this->assertContains(
                'href="/admin/statistics"',
                $response->body(),
                'Statistics link missing on ' . $uri
            );
        }
    }

    private function createService(string $label): int
    {
        $categoryId = (int) $this->scalar(
            'SELECT id FROM service_categories ORDER BY id ASC LIMIT 1'
        );

        $statement = $this->pdo()->prepare(
            'INSERT INTO services
                (category_id, name, slug, short_description, description,
                 price_from, estimated_minutes, is_active)
             VALUES
                (:category_id, :name, :slug, :short_description, :description,
                 :price_from, :estimated_minutes, 1)'
        );
        $statement->execute([
            'category_id' => $categoryId,
            'name' => 'TASK005D Rank ' . $label,
            'slug' => self::SLUG_PREFIX . strtolower(str_replace(' ', '-', $label)) . '-'
                . bin2hex(random_bytes(4)),
            'short_description' => 'TASK005D statistics fixture.',
            'description' => 'TASK005D statistics fixture service.',
            'price_from' => '50.00',
            'estimated_minutes' => 30,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function createRequest(string $createdAt, string $status, int $serviceId): int
    {
        $customerId = (int) $this->scalar(
            'SELECT id FROM users WHERE email = :email',
            ['email' => self::CUSTOMER]
        );

        $statement = $this->pdo()->prepare(
            'INSERT INTO repair_requests
                (public_number, customer_id, technician_id, service_id, status, priority,
                 device_type, device_brand, device_model, problem_description, customer_contact,
                 created_at, updated_at, completed_at)
             VALUES
                (:public_number, :customer_id, NULL, :service_id, :status, "normal",
                 "Ноутбук", "TASK005D", "TASK005D-Model",
                 "TASK005D: фикстура статистики.", :contact,
                 :created_at, :updated_at, :completed_at)'
        );
        $statement->execute([
            'public_number' => 'TASK005D-' . strtoupper(bin2hex(random_bytes(4))),
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'status' => $status,
            'contact' => self::CONTACT_PREFIX . bin2hex(random_bytes(4)),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'completed_at' => $status === 'completed' ? $createdAt : null,
        ]);
        $requestId = (int) $this->pdo()->lastInsertId();

        $this->pdo()->prepare(
            'INSERT INTO request_status_history (request_id, from_status, to_status, reason, changed_by)
             VALUES (:request_id, NULL, :to_status, :reason, :changed_by)'
        )->execute([
            'request_id' => $requestId,
            'to_status' => $status,
            'reason' => 'TASK005D: тестовая заявка статистики.',
            'changed_by' => $customerId,
        ]);

        return $requestId;
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

    /** @param array<string, mixed> $params */
    private function scalar(string $sql, array $params = []): mixed
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    private function recordCount(string $body): int
    {
        $matched = preg_match('/admin-record-count"><strong>([\d\s]+)<\/strong>/u', $body, $matches);
        if ($matched !== 1) {
            $this->fail('Statistics page is missing the request record count.');
        }

        return (int) str_replace(' ', '', (string) $matches[1]);
    }

    private function pdo(): \PDO
    {
        return app()->database()->connection();
    }

    private function positionOf(string $needle, string $haystack): int
    {
        $position = strpos($haystack, $needle);

        return $position === false ? PHP_INT_MAX : $position;
    }
}
