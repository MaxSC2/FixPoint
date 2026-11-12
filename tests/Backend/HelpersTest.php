<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\Request;
use FixPoint\Tests\TestCase;
use InvalidArgumentException;

final class HelpersTest extends TestCase
{
    public function testEscapingHandlesNullScalarsAndMarkup(): void
    {
        $this->assertSame('', e(null));
        $this->assertSame('42', e(42));
        $this->assertSame('&lt;b&gt;&quot;x&quot;&lt;/b&gt;', e('<b>"x"</b>'));
        $this->assertSame('', e(['array']));
    }

    public function testUrlAndAssetUseConfiguredBase(): void
    {
        $base = rtrim((string) config('app.url'), '/');

        $this->assertSame($base . '/services', url('/services'));
        $this->assertSame($base . '/assets/css/app.css', asset('css/app.css'));
    }

    public function testRedirectOnlyAllowsLocalPaths(): void
    {
        $response = redirect('/services?page=2');
        $this->assertSame(302, $response->status());
        $this->assertSame('/services?page=2', $response->header('Location'));

        try {
            redirect('//evil.example/steal');
        } catch (InvalidArgumentException $exception) {
            $this->assertContains('local application path', $exception->getMessage());
        } finally {
            $this->assertFalse(is_safe_local_path('https://evil.example'));
            $this->assertFalse(is_safe_local_path("//evil.example"));
            $this->assertFalse(is_safe_local_path("/line\nbreak"));
            $this->assertTrue(is_safe_local_path('/account'));
        }
    }

    public function testBackUsesSameOriginRefererOnly(): void
    {
        $safe = back(Request::create('GET', '/requests/create', headers: [
            'Referer' => rtrim((string) config('app.url'), '/') . '/services?q=screen',
        ]));
        $this->assertSame('/services?q=screen', $safe->header('Location'));

        $unsafe = back(Request::create('GET', '/requests/create', headers: [
            'Referer' => 'https://evil.example/phish',
        ]));
        $this->assertSame('/', $unsafe->header('Location'));
    }

    public function testCsrfFieldContainsToken(): void
    {
        app()->session()->start();
        app()->session()->clear();

        $field = csrf_field();

        $this->assertContains('name="_token"', $field);
        $this->assertContains(csrf_token(), $field);
    }

    public function testFormattingHelpers(): void
    {
        $this->assertContains('25 000', format_money(25000));
        $this->assertContains('₸', format_money(25000));
        $this->assertSame('—', format_money(null));
        $this->assertSame('Новая', status_label('new'));
        $this->assertSame('Ожидает запчасти', status_label('awaiting_parts'));
        $this->assertSame('legacy_status', status_label('legacy_status'));
        $this->assertNotSame('—', format_datetime('2026-11-12 10:30:00'));
        $this->assertSame('—', format_datetime(null));
        $this->assertSame('fallback', old_value([], 'email', 'fallback'));
        $this->assertSame('a@example.test', old_value(['email' => 'a@example.test'], 'email'));
    }

    public function testBasePathPointsToRepositoryRoot(): void
    {
        $this->assertSame(BASE_PATH, base_path());
        $this->assertTrue(is_file(base_path('app/bootstrap.php')));
    }
}
