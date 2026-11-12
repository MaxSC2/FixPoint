<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Request;
use FixPoint\Tests\IntegrationTestCase;

final class HttpTest extends IntegrationTestCase
{
    public function testHomeReturns200AndSecurityHeaders(): void
    {
        $response = app()->handle(Request::create('GET', '/'));

        $this->assertSame(200, $response->status());
        $this->assertContains('Сервисный центр FixPoint', $response->body());
        $this->assertContains('Популярные услуги', $response->body());
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertContains('text/html', (string) $response->header('Content-Type'));
    }

    public function testServicesListAndDetailReturnRealDatabaseData(): void
    {
        $list = app()->handle(Request::create('GET', '/services'));
        $this->assertSame(200, $list->status());
        $this->assertContains('Услуги', $list->body());
        $this->assertContains('Чистка ноутбука от пыли', $list->body());
        $this->assertContains('7 000', $list->body());

        $detail = app()->handle(Request::create('GET', '/services/laptop-cleaning'));
        $this->assertSame(200, $detail->status());
        $this->assertContains('Чистка ноутбука от пыли', $detail->body());
        $this->assertContains('Компьютеры и ноутбуки', $detail->body());

        $filtered = app()->handle(Request::create('GET', '/services?q=матриц'));
        $this->assertSame(200, $filtered->status());
        $this->assertContains('Замена матрицы ноутбука', $filtered->body());
        $this->assertNotContains('Чистка ноутбука от пыли', $filtered->body());
    }

    public function testAboutAndContactsPagesAreAvailable(): void
    {
        $about = app()->handle(Request::create('GET', '/about'));
        $this->assertSame(200, $about->status());
        $this->assertContains('О сервисе', $about->body());

        $contacts = app()->handle(Request::create('GET', '/contacts'));
        $this->assertSame(200, $contacts->status());
        $this->assertContains('Контакты', $contacts->body());
        // Missing settings must be an explicit placeholder, not an invented value.
        $this->assertNotContains('+7 700 000 00 00', $contacts->body());
    }

    public function testUnknownServiceAndUnknownRouteReturn404(): void
    {        $service = app()->handle(Request::create('GET', '/services/does-not-exist'));
        $this->assertSame(404, $service->status());

        $route = app()->handle(Request::create('GET', '/definitely-missing'));
        $this->assertSame(404, $route->status());
        $this->assertContains('Страница не найдена', $route->body());
    }

    public function testWrongMethodReturns405WithAllowHeader(): void
    {
        $response = app()->handle(Request::create('POST', '/health'));

        $this->assertSame(405, $response->status());
        $this->assertContains('GET', (string) $response->header('Allow'));
        $this->assertContains('HEAD', (string) $response->header('Allow'));
    }

    public function testHealthReportsDatabaseStatus(): void
    {
        $response = app()->handle(Request::create('GET', '/health', headers: ['Accept' => 'application/json']));

        $this->assertSame(200, $response->status());
        $this->assertContains('application/json', (string) $response->header('Content-Type'));
        $data = json_decode($response->body(), true);
        $this->assertIsArray($data);
        $this->assertSame('ok', $data['status']);
        $this->assertSame('ok', $data['database']['status']);
    }

    public function testHeadRequestMatchesGetWithoutError(): void
    {
        $response = app()->handle(Request::create('HEAD', '/'));

        $this->assertSame(200, $response->status());
    }
}
