<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Services\AuthService;
use FixPoint\Tests\IntegrationTestCase;

final class AuthServiceTest extends IntegrationTestCase
{
    public function testValidCredentialsAuthenticateWithoutLeakingPasswordHash(): void
    {
        $password = Env::get('SEED_PASSWORD', 'FixPoint!Dev1') ?? 'FixPoint!Dev1';
        $service = app()->make(AuthService::class);

        $this->assertTrue($service->attempt('admin@fixpoint.test', $password));
        $this->assertTrue(app()->auth()->check());

        $user = app()->auth()->user();
        $this->assertNotNull($user);
        $this->assertSame('admin@fixpoint.test', $user['email']);
        $this->assertSame('admin', $user['role']);
        $this->assertArrayHasKey('full_name', $user);
        $this->assertFalse(array_key_exists('password_hash', $user));
    }

    public function testInvalidInactiveAndUnknownUsersAreRejected(): void
    {
        $password = Env::get('SEED_PASSWORD', 'FixPoint!Dev1') ?? 'FixPoint!Dev1';
        $service = app()->make(AuthService::class);

        $this->assertFalse($service->attempt('admin@fixpoint.test', 'wrong-password'));
        $this->assertFalse($service->attempt('inactive@fixpoint.test', $password));
        $this->assertFalse($service->attempt('nobody@fixpoint.test', $password));
        $this->assertFalse($service->attempt('not-an-email', $password));
        $this->assertFalse(app()->auth()->check());
    }

    public function testLogoutClearsTheSessionUser(): void
    {
        $password = Env::get('SEED_PASSWORD', 'FixPoint!Dev1') ?? 'FixPoint!Dev1';
        $service = app()->make(AuthService::class);

        $this->assertTrue($service->attempt('customer.kovalev@fixpoint.test', $password));
        $this->assertSame('customer', app()->auth()->role());

        $service->logout();

        $this->assertFalse(app()->auth()->check());
        $this->assertNull(app()->auth()->user());
    }

    public function testEmailIsNormalisedToLowerCase(): void
    {
        $password = Env::get('SEED_PASSWORD', 'FixPoint!Dev1') ?? 'FixPoint!Dev1';
        $service = app()->make(AuthService::class);

        $this->assertTrue($service->attempt('  ADMIN@FIXPOINT.TEST ', $password));
    }
}
