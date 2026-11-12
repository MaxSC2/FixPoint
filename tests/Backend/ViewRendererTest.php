<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\Config;
use FixPoint\Core\ViewRenderer;
use FixPoint\Tests\TestCase;
use RuntimeException;

final class ViewRendererTest extends TestCase
{
    public function testBackendFallbackHomeRendersAndEscapesData(): void
    {
        // Point the production view root at an empty directory so the backend
        // fallback is exercised even when frontend templates are present.
        $values = app()->config()->all();
        $values['paths']['views'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixpoint_missing_views_' . bin2hex(random_bytes(5));
        $renderer = new ViewRenderer(new Config($values));

        $response = $renderer->renderPage('pages/public/home', [
            'pageTitle' => '<script>alert(1)</script>',
            'flash' => null,
            'stats' => ['categories_count' => 5, 'services_count' => 10],
            'featuredServices' => [],
            'foundation' => ['environment' => 'testing', 'php_version' => PHP_VERSION],
        ]);

        $body = $response->body();
        $this->assertSame(200, $response->status());
        $this->assertContains('Сервисный центр FixPoint', $body);
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertNotContains('<script>alert(1)</script>', $body);
        $this->assertContains('Активных услуг пока нет', $body);
    }

    public function testProductionHomeViewRendersFrontendTemplateAndEscapesData(): void
    {
        $renderer = new ViewRenderer(app()->config());
        $response = $renderer->renderPage('pages/public/home', [
            'pageTitle' => '<script>alert(1)</script>',
            'activeNav' => 'home',
            'authUser' => null,
            'flash' => null,
            'csrfToken' => str_repeat('a', 64),
            'featuredServices' => [[
                'name' => '<script>alert(1)</script>',
                'slug' => 'demo',
                'short_description' => 'demo',
                'price_from' => 1000,
            ]],
        ]);

        $body = $response->body();
        $this->assertSame(200, $response->status());
        $this->assertContains('site-header', $body);
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertNotContains('<script>alert(1)</script>', $body);
    }

    public function testErrorPageIsNeutral(): void
    {
        $renderer = new ViewRenderer(app()->config());
        $response = $renderer->renderError(404, [
            'title' => 'Страница не найдена',
            'message' => 'Запрошенная страница не существует.',
        ]);

        $this->assertSame(404, $response->status());
        $this->assertContains('Страница не найдена', $response->body());
        $this->assertNotContains('Exception', $response->body());
        $this->assertNotContains('Stack trace', $response->body());
    }

    public function testMissingViewThrows(): void
    {
        $renderer = new ViewRenderer(app()->config());
        try {
            $renderer->renderPage('pages/public/does-not-exist');
        } catch (RuntimeException $exception) {
            $this->assertContains('View not found', $exception->getMessage());

            return;
        }

        $this->fail('Expected missing view exception.');
    }

    public function testExistsReportsFallbackViews(): void
    {
        $renderer = new ViewRenderer(app()->config());

        $this->assertTrue($renderer->exists('pages/public/home'));
        $this->assertFalse($renderer->exists('pages/public/nope'));
    }
}
