<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\App;
use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Tests\TestCase;

/**
 * Error policy: HTTP responses must never contain SQL, exception messages,
 * file paths or stack traces. A 500 response carries only a correlation id.
 */
final class ErrorTest extends TestCase
{
    private string $tempBase = '';

    private ?Config $originalConfig = null;

    protected function tearDown(): void
    {
        // App::__construct replaces the static instance; restore the real one.
        if ($this->originalConfig instanceof Config) {
            new App($this->originalConfig);
        }

        if ($this->tempBase !== '' && is_dir($this->tempBase)) {
            foreach (glob($this->tempBase . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tempBase . '/config');
            @rmdir($this->tempBase);
        }
    }

    public function testNotFoundPageIsNeutral(): void
    {
        $response = app()->handle(Request::create('GET', '/definitely-missing'));

        $this->assertSame(404, $response->status());
        $this->assertContains('Страница не найдена', $response->body());
        $this->assertNotContains('Route not found', $response->body());
        $this->assertNotContains('HttpException', $response->body());
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
    }

    public function testUnhandledExceptionReturnsNeutral500AndLogsDetails(): void
    {
        $originalConfig = app()->config();
        $this->originalConfig = $originalConfig;

        $this->tempBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixpoint_app_' . bin2hex(random_bytes(4));
        mkdir($this->tempBase . '/config', 0775, true);
        file_put_contents(
            $this->tempBase . '/config/routes.php',
            <<<'PHP'
<?php
return static function (\FixPoint\Core\Router $router, \FixPoint\Core\App $app): void {
    throw new \RuntimeException('SQLSTATE[HY000] secret stack trace fragment');
};
PHP
        );

        $values = $originalConfig->all();
        $values['paths']['base'] = $this->tempBase;
        $values['logging']['path'] = $this->tempBase . '/app.log';
        $app = new App(new Config($values));

        $response = $app->handle(Request::create('GET', '/boom'));

        $this->assertSame(500, $response->status());
        $this->assertContains('Внутренняя ошибка', $response->body());
        $this->assertNotContains('SQLSTATE', $response->body());
        $this->assertNotContains('RuntimeException', $response->body());
        $this->assertNotContains('secret stack trace fragment', $response->body());
        $this->assertNotContains('Stack trace', $response->body());
        $this->assertSame(1, preg_match('/[0-9a-f]{16}/', $response->body()), 'Error id must be shown.');
        $this->assertSame('DENY', $response->header('X-Frame-Options'));

        $log = (string) @file_get_contents($this->tempBase . '/app.log');
        $this->assertContains('Unhandled application error', $log);
        $this->assertContains('secret stack trace fragment', $log, 'Details belong in the log, not the response.');
    }

    public function testJsonErrorsUseMachineReadableCodes(): void
    {
        $this->originalConfig = app()->config();

        $json404 = app()->handle(Request::create('GET', '/missing-json', headers: ['Accept' => 'application/json']));
        $this->assertSame(404, $json404->status());
        $decoded = json_decode($json404->body(), true);
        $this->assertIsArray($decoded);
        $this->assertSame('not_found', $decoded['error']);

        $this->tempBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixpoint_app_' . bin2hex(random_bytes(4));
        mkdir($this->tempBase . '/config', 0775, true);
        file_put_contents(
            $this->tempBase . '/config/routes.php',
            "<?php return static function (): void { throw new \\RuntimeException('boom'); };\n"
        );

        $values = app()->config()->all();
        $values['paths']['base'] = $this->tempBase;
        $values['logging']['path'] = $this->tempBase . '/app.log';
        $app = new App(new Config($values));

        $json500 = $app->handle(Request::create('GET', '/boom', headers: ['Accept' => 'application/json']));
        $this->assertSame(500, $json500->status());
        $decoded500 = json_decode($json500->body(), true);
        $this->assertIsArray($decoded500);
        $this->assertSame('server_error', $decoded500['error']);
        $this->assertArrayHasKey('error_id', $decoded500);
        $this->assertNotContains('boom', $json500->body());
    }

    public function testMethodNotAllowedIsExplicit(): void
    {
        $response = app()->handle(Request::create('POST', '/services'));

        $this->assertSame(405, $response->status());
        $this->assertContains('GET', (string) $response->header('Allow'));
        $this->assertContains('HEAD', (string) $response->header('Allow'));
        $this->assertNotContains('Method not allowed', $response->body());
    }
}
