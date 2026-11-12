<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\ViewRenderer;
use FixPoint\Tests\TestCase;
use RuntimeException;

final class ViewRendererTest extends TestCase
{
    public function testBackendFallbackHomeRendersAndEscapesData(): void
    {
        $renderer = new ViewRenderer(app()->config());
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
