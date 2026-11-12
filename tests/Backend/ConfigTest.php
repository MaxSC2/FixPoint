<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\Config;
use FixPoint\Tests\TestCase;
use InvalidArgumentException;

final class ConfigTest extends TestCase
{
    public function testCurrentConfigurationUsesSafeDatabaseDefaults(): void
    {
        $config = app()->config();

        $this->assertSame('testing', $config->get('app.env'));
        $this->assertSame('utf8mb4', $config->get('database.charset'));
        $this->assertSame('utf8mb4_unicode_ci', $config->get('database.collation'));
        $this->assertSame('Lax', $config->get('session.samesite'));
        $this->assertTrue($config->isLocal());
        $this->assertFalse($config->isProduction());
        $this->assertSame(BASE_PATH . '/app/Views', $config->get('paths.views'));
        $this->assertTrue(is_dir((string) $config->get('paths.fallback_views')));
    }

    public function testDotAccessAndDefaults(): void
    {
        $config = new Config(['a' => ['b' => ['c' => 5]], 'plain' => 'x']);

        $this->assertSame(5, $config->get('a.b.c'));
        $this->assertSame('fallback', $config->get('a.missing', 'fallback'));
        $this->assertSame('x', $config->get('plain'));
        $this->assertSame(['b' => ['c' => 5]], $config->get('a'));
    }

    public function testInvalidDatabaseNameIsRejected(): void
    {
        $original = getenv('DB_DATABASE');
        putenv('DB_DATABASE=bad-name; DROP TABLE users');

        try {
            $this->expectException(InvalidArgumentException::class, static fn () => Config::fromEnvironment(BASE_PATH));
        } finally {
            $original === false ? putenv('DB_DATABASE') : putenv('DB_DATABASE=' . $original);
        }
    }

    private function expectException(string $class, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($class, $exception);

            return;
        }

        $this->fail('Expected exception ' . $class . ' was not thrown.');
    }
}
