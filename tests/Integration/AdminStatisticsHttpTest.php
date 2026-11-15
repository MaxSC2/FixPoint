<?php

declare(strict_types=1);

namespace FixPoint\\Tests\\Integration;

use FixPoint\\Core\\Env;
use FixPoint\\Core\\Request;
use FixPoint\\Tests\\IntegrationTestCase;

final class AdminStatisticsHttpTest extends IntegrationTestCase
{
    public function testStatisticsRequiresAdminRole(): void
    {
        $guest = app()->handle(Request::create('GET', '/admin/statistics'));
        $this->assertSame(302, $guest->status());
        $this->assertContains('/login', (string) $guest->header('Location'));

        $this->loginAs('customer.kovalev@fixpoint.test');
        $customer = app()->handle(Request::create('GET', '/admin/statistics'));
        $this->assertSame(403, $customer->status());
    }

    public function testAdminStatisticsUsesTheSelectedInclusiveDateRange(): void
    {
        $this->loginAs('admin@fixpoint.test');

        $response = app()->handle(Request::create(
            'GET',
            '/admin/statistics?from=2026-11-01&to=2026-11-15'
        ));

        $body = $response->body();
        $this->assertSame(200, $response->status());
        $this->assertContains('Статистика сервиса', $body);
        $this->assertContains('name="from" value="2026-11-01"', $body);
        $this->assertContains('name="to" value="2026-11-15"', $body);
        $this->assertContains('<strong>3</strong><small>заявок за период</small>', $body);
        $this->assertContains('Ожидает согласования', $body);
        $this->assertContains('В ремонте', $body);
        $this->assertContains('Заявки по месяцам', $body);
    }

    private function loginAs(string $email): void
    {
        $response = app()->handle(Request::create('POST', '/login', [
            'email' => $email,
            'password' => (string) Env::get('SEED_PASSWORD', 'FixPoint!Dev1'),
            '_token' => app()->csrf()->token(),
        ]));

        $this->assertSame(302, $response->status());
        $this->assertTrue(app()->auth()->check());
    }
}
