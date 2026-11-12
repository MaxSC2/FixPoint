<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\Session;
use FixPoint\Tests\TestCase;

final class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        app()->session()->start();
        app()->session()->clear();
    }

    protected function tearDown(): void
    {
        app()->session()->clear();
    }

    public function testCookieAndIniHardeningIsApplied(): void
    {
        $session = app()->session();
        $params = session_get_cookie_params();
        $expected = app()->config()->get('session');

        $this->assertSame($expected['name'], session_name());
        $this->assertTrue($params['httponly']);
        $this->assertSame($expected['samesite'], $params['samesite']);
        $this->assertSame($expected['secure'], $params['secure']);
        $this->assertSame('1', ini_get('session.use_strict_mode'));
        $this->assertSame('1', ini_get('session.use_only_cookies'));
        $this->assertNotSame('', $session->id());
    }

    public function testDataFlashErrorsAndOldInputLifecycle(): void
    {
        $session = app()->session();
        $session->set('key', 'value');
        $this->assertTrue($session->has('key'));
        $this->assertSame('value', $session->get('key'));
        $session->remove('key');
        $this->assertFalse($session->has('key'));

        $session->flash('success', 'Сохранено.');
        $this->assertSame(['type' => 'success', 'message' => 'Сохранено.'], $session->pullFlash());
        $this->assertNull($session->pullFlash());

        $session->flashErrors(['email' => 'Некорректный email.']);
        $this->assertSame(['email' => 'Некорректный email.'], $session->pullErrors());
        $this->assertSame([], $session->pullErrors());

        $session->flashOld(['email' => 'a@example.test', 'password' => 'secret']);
        $this->assertSame('a@example.test', $session->pullOld()['email']);
        $this->assertSame([], $session->pullOld());
    }

    public function testUnknownFlashTypeIsRejected(): void
    {
        try {
            app()->session()->flash('danger', 'x');
        } catch (\InvalidArgumentException $exception) {
            $this->assertContains('flash type', $exception->getMessage());

            return;
        }

        $this->fail('Expected unknown flash type rejection.');
    }

    public function testRegenerateChangesSessionId(): void
    {
        $session = app()->session();
        $before = $session->id();
        $session->regenerate(true);
        $after = $session->id();

        $this->assertNotSame('', $before);
        $this->assertNotSame('', $after);
        $this->assertNotSame($before, $after);
    }
}
