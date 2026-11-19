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

    public function testCatalogPageSizeRejectsUnbalancedGridValues(): void
    {
        $this->withEnvironmentValue('CATALOG_PER_PAGE', '14', function (): void {
            $this->expectException(
                InvalidArgumentException::class,
                static fn () => Config::fromEnvironment(BASE_PATH)
            );
        });
    }

    public function testCatalogPageSizeAcceptsMultiplesOfTwelve(): void
    {
        foreach ([12, 24, 36, 48] as $perPage) {
            $this->withEnvironmentValue('CATALOG_PER_PAGE', (string) $perPage, function () use ($perPage): void {
                $config = Config::fromEnvironment(BASE_PATH);
                $this->assertSame($perPage, $config->get('catalog.per_page'));
            });
        }
    }

    /**
     * Temporarily overrides a process variable without leaking it to later tests.
     */
    private function withEnvironmentValue(string $key, string $value, callable $callback): mixed
    {
        $original = getenv($key);
        $hadEnv = array_key_exists($key, $_ENV);
        $envValue = $_ENV[$key] ?? null;
        $hadServer = array_key_exists($key, $_SERVER);
        $serverValue = $_SERVER[$key] ?? null;

        putenv($key . '=' . $value);

        try {
            return $callback();
        } finally {
            $original === false ? putenv($key) : putenv($key . '=' . $original);

            if ($hadEnv) {
                $_ENV[$key] = $envValue;
            } else {
                unset($_ENV[$key]);
            }

            if ($hadServer) {
                $_SERVER[$key] = $serverValue;
            } else {
                unset($_SERVER[$key]);
            }
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
