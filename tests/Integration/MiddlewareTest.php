<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\HttpException;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Middleware\CsrfMiddleware;
use FixPoint\Middleware\RoleMiddleware;
use FixPoint\Services\AuthService;
use FixPoint\Tests\IntegrationTestCase;

final class MiddlewareTest extends IntegrationTestCase
{
    public function testRoleMiddlewareEnforcesServerSideRoles(): void
    {
        $this->loginAs('customer.kovalev@fixpoint.test');
        $middleware = new RoleMiddleware(app()->auth(), app()->session(), 'admin');

        try {
            $middleware->handle(
                Request::create('GET', '/admin'),
                static fn (): Response => Response::html('admin')
            );
            $this->fail('Expected 403 for customer on admin route.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->statusCode);
        }

        $customerMiddleware = new RoleMiddleware(app()->auth(), app()->session(), 'customer');
        $called = false;
        $response = $customerMiddleware->handle(
            Request::create('GET', '/account'),
            static function () use (&$called): Response {
                $called = true;

                return Response::html('account');
            }
        );

        $this->assertTrue($called);
        $this->assertSame(200, $response->status());
    }

    public function testTechnicianRoleIsAcceptedOnlyForTechnicianRoutes(): void
    {
        $this->loginAs('master.ivanov@fixpoint.test');
        $middleware = new RoleMiddleware(app()->auth(), app()->session(), 'technician', 'admin');

        $response = $middleware->handle(
            Request::create('GET', '/technician/requests'),
            static fn (): Response => Response::html('queue')
        );

        $this->assertSame(200, $response->status());
    }

    public function testCsrfMiddlewareRejectsMissingAndAcceptsValidToken(): void
    {
        $middleware = new CsrfMiddleware(app()->csrf());

        try {
            $middleware->handle(
                Request::create('POST', '/logout'),
                static fn (): Response => Response::html('ok')
            );
            $this->fail('Expected 419 for missing CSRF token.');
        } catch (HttpException $exception) {
            $this->assertSame(419, $exception->statusCode);
        }

        $jsonResponse = $middleware->handle(
            Request::create('POST', '/logout', headers: ['Accept' => 'application/json']),
            static fn (): Response => Response::html('ok')
        );
        $this->assertSame(419, $jsonResponse->status());

        $token = app()->csrf()->token();
        $called = false;
        $response = $middleware->handle(
            Request::create('POST', '/logout', post: ['_token' => $token]),
            static function () use (&$called): Response {
                $called = true;

                return Response::html('ok');
            }
        );

        $this->assertTrue($called);
        $this->assertSame(200, $response->status());
    }

    public function testCsrfMiddlewareIgnoresGetRequests(): void
    {
        $called = false;
        $response = (new CsrfMiddleware(app()->csrf()))->handle(
            Request::create('GET', '/services'),
            static function () use (&$called): Response {
                $called = true;

                return Response::html('ok');
            }
        );

        $this->assertTrue($called);
        $this->assertSame(200, $response->status());
    }

    private function loginAs(string $email): void
    {
        $password = Env::get('SEED_PASSWORD', 'FixPoint!Dev1') ?? 'FixPoint!Dev1';
        $this->assertTrue(app()->make(AuthService::class)->attempt($email, $password));
    }
}
