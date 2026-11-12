<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\Env;
use FixPoint\Tests\TestCase;

final class EnvTest extends TestCase
{
    private string $file = '';

    /** @var list<string> */
    private array $keys = [];

    protected function setUp(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $this->file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixpoint_env_' . $suffix . '.env';
        $this->keys = [
            'FP_TEST_PLAIN_' . $suffix,
            'FP_TEST_QUOTED_' . $suffix,
            'FP_TEST_SINGLE_' . $suffix,
            'FP_TEST_EXPORT_' . $suffix,
            'FP_TEST_EXISTING_' . $suffix,
            'FP_TEST_HASH_' . $suffix,
        ];
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        foreach ($this->keys as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public function testParsesValuesCommentsQuotesAndExport(): void
    {
        [$plain, $quoted, $single, $export, $existing, $hash] = $this->keys;
        putenv($existing . '=keep-me');
        file_put_contents($this->file, implode("\n", [
            '# comment',
            '',
            $plain . '=value # inline comment',
            $quoted . '="hello world"',
            $single . "='literal value'",
            'export ' . $export . '=exported',
            $existing . '=overwrite-me',
            $hash . '=abc#def',
        ]));

        Env::load($this->file);

        $this->assertSame('value', Env::get($plain));
        $this->assertSame('hello world', Env::get($quoted));
        $this->assertSame('literal value', Env::get($single));
        $this->assertSame('exported', Env::get($export));
        $this->assertSame('keep-me', Env::get($existing), 'Real environment must win over .env.');
        $this->assertSame('abc#def', Env::get($hash));
    }

    public function testBoolAndIntHelpersUseDefaultsForInvalidValues(): void
    {
        $key = $this->keys[0];
        putenv($key . '=true');
        $this->assertTrue(Env::bool($key));
        putenv($key . '=not-a-bool');
        $this->assertFalse(Env::bool($key, false));
        putenv($key . '=42');
        $this->assertSame(42, Env::int($key));
        putenv($key . '=abc');
        $this->assertSame(7, Env::int($key, 7));
    }

    public function testMissingFileIsIgnored(): void
    {
        Env::load(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixpoint_missing_' . bin2hex(random_bytes(4)) . '.env');
        $this->assertTrue(true);
    }
}
