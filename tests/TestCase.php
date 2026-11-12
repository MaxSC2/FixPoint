<?php

declare(strict_types=1);

namespace FixPoint\Tests;

use Throwable;

abstract class TestCase
{
    private int $assertions = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @return array{passed: int, failed: int, assertions: int} */
    public function run(): array
    {
        $methods = array_filter(
            get_class_methods($this),
            static fn (string $method): bool => str_starts_with($method, 'test')
        );
        sort($methods);

        $passed = 0;
        $failed = 0;
        $totalAssertions = 0;

        foreach ($methods as $method) {
            $this->assertions = 0;
            $this->failures = [];
            $this->setUp();

            try {
                $this->{$method}();
            } catch (AssertionFailedException $exception) {
                $this->failures[] = $exception->getMessage();
            } catch (Throwable $exception) {
                $this->failures[] = sprintf(
                    '%s: %s (%s:%d)',
                    $exception::class,
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                );
            } finally {
                try {
                    $this->tearDown();
                } catch (Throwable $exception) {
                    $this->failures[] = 'tearDown failed: ' . $exception->getMessage();
                }
            }

            $totalAssertions += $this->assertions;
            $label = static::class . '::' . $method;
            if ($this->failures === []) {
                echo "PASS {$label}\n";
                $passed++;
                continue;
            }

            echo "FAIL {$label}\n";
            foreach ($this->failures as $failure) {
                echo '     ' . $failure . "\n";
            }
            $failed++;
        }

        return [
            'passed' => $passed,
            'failed' => $failed,
            'assertions' => $totalAssertions,
        ];
    }

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->fail(sprintf(
                '%sExpected %s, got %s.',
                $message === '' ? '' : $message . ' ',
                $this->describe($expected),
                $this->describe($actual)
            ));
        }
    }

    protected function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected === $actual) {
            $this->fail(sprintf(
                '%sExpected values to differ, both are %s.',
                $message === '' ? '' : $message . ' ',
                $this->describe($actual)
            ));
        }
    }

    protected function assertTrue(mixed $actual, string $message = ''): void
    {
        $this->assertSame(true, $actual, $message);
    }

    protected function assertFalse(mixed $actual, string $message = ''): void
    {
        $this->assertSame(false, $actual, $message);
    }

    protected function assertNull(mixed $actual, string $message = ''): void
    {
        $this->assertSame(null, $actual, $message);
    }

    protected function assertNotNull(mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($actual === null) {
            $this->fail(($message === '' ? '' : $message . ' ') . 'Expected a non-null value.');
        }
    }

    protected function assertCount(int $expected, array $actual, string $message = ''): void
    {
        $this->assertSame($expected, count($actual), $message);
    }

    protected function assertGreaterThan(int|float $minimum, int|float $actual, string $message = ''): void
    {
        $this->assertions++;
        if (!($actual > $minimum)) {
            $this->fail(sprintf(
                '%sExpected value > %s, got %s.',
                $message === '' ? '' : $message . ' ',
                $this->describe($minimum),
                $this->describe($actual)
            ));
        }
    }

    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assertions++;
        if (!array_key_exists($key, $array)) {
            $this->fail(($message === '' ? '' : $message . ' ') . 'Missing array key: ' . $key);
        }
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            $this->fail(($message === '' ? '' : $message . ' ') . 'String does not contain ' . $this->describe($needle));
        }
    }

    protected function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (str_contains($haystack, $needle)) {
            $this->fail(($message === '' ? '' : $message . ' ') . 'String must not contain ' . $this->describe($needle));
        }
    }

    protected function assertIsArray(mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if (!is_array($actual)) {
            $this->fail(($message === '' ? '' : $message . ' ') . 'Expected an array, got ' . $this->describe($actual));
        }
    }

    protected function assertInstanceOf(string $class, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if (!$actual instanceof $class) {
            $this->fail(($message === '' ? '' : $message . ' ') . 'Expected instance of ' . $class);
        }
    }

    protected function fail(string $message): never
    {
        throw new AssertionFailedException($message);
    }

    private function describe(mixed $value): string
    {
        if (is_string($value)) {
            $value = strlen($value) > 300 ? substr($value, 0, 300) . '...' : $value;

            return '"' . $value . '"';
        }

        return var_export($value, true);
    }
}

final class AssertionFailedException extends \RuntimeException
{
}
