<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Middleware\AuthMiddleware;
use FixPoint\Middleware\RoleMiddleware;
use FixPoint\Tests\TestCase;
use InvalidArgumentException;

final class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        app()->session()->start();
        app()->session()->clear();
        app()->auth()->logout();
        app()->session()->clear();
    }

    public function testGuestIsRedirectedToLoginWithSafeReturnTo(): void
    {
        $called = false;
        $middleware = new AuthMiddleware(app()->auth(), app()->session());
        $response = $middleware->handle(
            Request::create('GET', '/account?tab=settings'),
            static function () use (&$called): Response {
                $called = true;

                return Response::html('private');
            }
        );

        $this->assertFalse($called);
        $this->assertSame(302, $response->status());
        $location = (string) $response->header('Location');
        $this->assertContains('/login?return_to=', $location);
        $this->assertContains(rawurlencode('/account?tab=settings'), $location);
        $this->assertSame('info', app()->session()->pullFlash()['type']);
    }

    public function testGuestJsonRequestGets401(): void
    {
        $middleware = new AuthMiddleware(app()->auth(), app()->session());
        $response = $middleware->handle(
            Request::create('GET', '/account', headers: ['Accept' => 'application/json']),
            static fn (): Response => Response::html('private')
        );

        $this->assertSame(401, $response->status());
        $this->assertContains('unauthorized', $response->body());
    }

    public function testRoleMiddlewareDeniesGuestsEvenWithoutAuthMiddleware(): void
    {
        $middleware = new RoleMiddleware(app()->auth(), app()->session(), 'admin');
        $response = $middleware->handle(
            Request::create('GET', '/admin'),
            static fn (): Response => Response::html('admin')
        );

        $this->assertSame(302, $response->status());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testUnknownRoleIsRejectedAtConstruction(): void
    {
        try {
            new RoleMiddleware(app()->auth(), app()->session(), 'superuser');
        } catch (InvalidArgumentException $exception) {
            $this->assertContains('Unknown role', $exception->getMessage());

            return;
        }

        $this->fail('Expected unknown role rejection.');
    }
}
