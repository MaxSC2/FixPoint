<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

/**
 * End-to-end HTTP auth tests: real routes, middleware, sessions, CSRF and
 * MySQL. Each test starts from a cleared session via IntegrationTestCase.
 */
final class AuthHttpTest extends IntegrationTestCase
{
    private const TEST_EMAIL_PREFIX = 'auth-test-';

    protected function tearDown(): void
    {
        $pdo = app()->database()->connection();
        $statement = $pdo->prepare('DELETE FROM users WHERE email LIKE :pattern');
        $statement->execute(['pattern' => self::TEST_EMAIL_PREFIX . '%@fixpoint.test']);

        parent::tearDown();
    }

    public function testGuestPagesRenderWithCsrfToken(): void
    {
        $login = app()->handle(Request::create('GET', '/login'));
        $this->assertSame(200, $login->status());
        $this->assertContains('Вход', $login->body());
        $this->assertContains('name="_token"', $login->body());

        $register = app()->handle(Request::create('GET', '/register'));
        $this->assertSame(200, $register->status());
        $this->assertContains('Регистрация', $register->body());
        $this->assertContains('name="password_confirmation"', $register->body());
    }

    public function testRegistrationCreatesCustomerAndLogsIn(): void
    {
        $email = self::TEST_EMAIL_PREFIX . 'new@fixpoint.test';
        $response = $this->post('/register', [
            'full_name' => 'Новый Клиент',
            'email' => $email,
            'phone' => '+7 700 999 88 77',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
        ]);

        $this->assertSame(302, $response->status());
        $this->assertSame('/account', $response->header('Location'));
        $this->assertTrue(app()->auth()->check());
        $this->assertSame('customer', app()->auth()->role());
        $this->assertSame($email, app()->auth()->user()['email'] ?? null);

        $statement = app()->database()->connection()->prepare('SELECT * FROM users WHERE email = :email');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        $this->assertNotSame(false, $user);
        $this->assertSame('customer', $user['role']);
        $this->assertSame('1', (string) $user['is_active']);
        $this->assertTrue(password_verify('StrongPass1', (string) $user['password_hash']));

        $account = app()->handle(Request::create('GET', '/account'));
        $this->assertSame(200, $account->status());
        $this->assertContains('Новый Клиент', $account->body());
    }

    public function testRegistrationValidationKeepsInputButNeverPassword(): void
    {
        $response = $this->post('/register', [
            'full_name' => 'Тестовый Пользователь',
            'email' => 'not-an-email',
            'phone' => 'abc',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $this->assertSame(302, $response->status());
        $this->assertSame('/register', $response->header('Location'));

        $page = app()->handle(Request::create('GET', '/register'));
        $body = $page->body();
        $this->assertContains('Укажите корректный email.', $body);
        $this->assertContains('Пароль должен быть не короче 8 символов.', $body);
        $this->assertContains('Пароли не совпадают.', $body);
        $this->assertContains('Тестовый Пользователь', $body);
        $this->assertNotContains('short', $body);
        $this->assertNotContains('different', $body);
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $response = $this->post('/register', [
            'full_name' => 'Дубликат',
            'email' => 'admin@fixpoint.test',
            'phone' => '',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
        ]);

        $this->assertSame(302, $response->status());
        $page = app()->handle(Request::create('GET', '/register'));
        $this->assertContains('уже зарегистрирован', $page->body());
        $this->assertFalse(app()->auth()->check());
    }

    public function testLoginSuccessAndLogout(): void
    {
        $response = $this->post('/login', [
            'email' => 'customer.kovalev@fixpoint.test',
            'password' => $this->seedPassword(),
        ]);

        $this->assertSame(302, $response->status());
        $this->assertSame('/account', $response->header('Location'));
        $this->assertTrue(app()->auth()->check());
        $this->assertSame('customer', app()->auth()->role());

        $account = app()->handle(Request::create('GET', '/account'));
        $this->assertSame(200, $account->status());
        $this->assertContains('Алексей Ковалёв', $account->body());

        $logout = $this->post('/logout', []);
        $this->assertSame(302, $logout->status());
        $this->assertSame('/', $logout->header('Location'));
        $this->assertFalse(app()->auth()->check());

        $protected = app()->handle(Request::create('GET', '/account'));
        $this->assertSame(302, $protected->status());
        $this->assertContains('/login?return_to=', (string) $protected->header('Location'));
    }

    public function testInvalidLoginIsGenericAndKeepsOnlyEmail(): void
    {
        $response = $this->post('/login', [
            'email' => 'customer.kovalev@fixpoint.test',
            'password' => 'definitely-wrong-password',
        ]);

        $this->assertSame(302, $response->status());
        $this->assertSame('/login', $response->header('Location'));
        $this->assertFalse(app()->auth()->check());

        $page = app()->handle(Request::create('GET', '/login'));
        $body = $page->body();
        $this->assertContains('Неверный email или пароль.', $body);
        $this->assertContains('customer.kovalev@fixpoint.test', $body);
        $this->assertNotContains('definitely-wrong-password', $body);
    }

    public function testCsrfIsRequiredForAuthPosts(): void
    {
        $login = app()->handle(Request::create('POST', '/login', [
            'email' => 'customer.kovalev@fixpoint.test',
            'password' => $this->seedPassword(),
        ]));
        $this->assertSame(419, $login->status());

        $register = app()->handle(Request::create('POST', '/register', [
            'full_name' => 'Без Токена',
            'email' => self::TEST_EMAIL_PREFIX . 'csrf@fixpoint.test',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
        ]));
        $this->assertSame(419, $register->status());
        $this->assertFalse(app()->auth()->check());
    }

    public function testGuestsAreRedirectedWithLocalReturnTo(): void
    {
        $account = app()->handle(Request::create('GET', '/account'));
        $this->assertSame(302, $account->status());
        $this->assertContains('return_to=%2Faccount', (string) $account->header('Location'));

        $admin = app()->handle(Request::create('GET', '/admin'));
        $this->assertSame(302, $admin->status());
        $this->assertContains('return_to=%2Fadmin', (string) $admin->header('Location'));
    }

    public function testRoleGatesRejectWrongRoles(): void
    {
        $this->loginAs('customer.kovalev@fixpoint.test');
        $this->assertSame(403, app()->handle(Request::create('GET', '/admin'))->status());
        $this->assertSame(403, app()->handle(Request::create('GET', '/technician/requests'))->status());
        $this->assertSame(200, app()->handle(Request::create('GET', '/account'))->status());
        $this->logout();

        $this->loginAs('master.ivanov@fixpoint.test');
        $this->assertSame(403, app()->handle(Request::create('GET', '/account'))->status());
        $this->assertSame(403, app()->handle(Request::create('GET', '/admin'))->status());
        $this->assertSame(200, app()->handle(Request::create('GET', '/technician/requests'))->status());
        $this->logout();

        $this->loginAs('admin@fixpoint.test');
        $this->assertSame(403, app()->handle(Request::create('GET', '/account'))->status());
        $this->assertSame(403, app()->handle(Request::create('GET', '/technician/requests'))->status());
        $this->assertSame(200, app()->handle(Request::create('GET', '/admin'))->status());
    }

    public function testAuthenticatedUserIsRedirectedAwayFromGuestPages(): void
    {
        $this->loginAs('customer.kovalev@fixpoint.test');

        $login = app()->handle(Request::create('GET', '/login'));
        $this->assertSame(302, $login->status());
        $this->assertSame('/account', $login->header('Location'));

        $register = app()->handle(Request::create('GET', '/register'));
        $this->assertSame(302, $register->status());
        $this->assertSame('/account', $register->header('Location'));
    }

    public function testReturnToCannotRedirectOffSite(): void
    {
        $offSite = $this->post(
            '/login?return_to=https%3A%2F%2Fevil.test%2Fsteal',
            ['email' => 'customer.kovalev@fixpoint.test', 'password' => $this->seedPassword()]
        );
        $this->assertSame('/account', $offSite->header('Location'));

        $this->logout();

        $protocolRelative = $this->post(
            '/login?return_to=%2F%2Fevil.test',
            ['email' => 'customer.kovalev@fixpoint.test', 'password' => $this->seedPassword()]
        );
        $this->assertSame('/account', $protocolRelative->header('Location'));
    }

    public function testRoleLandingPagesShowOnlyScopedRequests(): void
    {
        $this->loginAs('customer.kovalev@fixpoint.test');
        $customerPage = app()->handle(Request::create('GET', '/account'))->body();
        $this->assertContains('FP-2026-000001', $customerPage);
        $this->assertContains('FP-2026-000003', $customerPage);
        $this->assertNotContains('FP-2026-000002', $customerPage);
        $this->assertNotContains('FP-2026-000004', $customerPage);
        $this->logout();

        $this->loginAs('master.ivanov@fixpoint.test');
        $technicianPage = app()->handle(Request::create('GET', '/technician/requests'))->body();
        $this->assertContains('FP-2026-000001', $technicianPage);
        $this->assertContains('FP-2026-000004', $technicianPage);
        $this->assertNotContains('FP-2026-000003', $technicianPage);
        $this->logout();

        $this->loginAs('admin@fixpoint.test');
        $adminPage = app()->handle(Request::create('GET', '/admin'))->body();
        $this->assertContains('FP-2026-000001', $adminPage);
        $this->assertContains('FP-2026-000002', $adminPage);
        $this->assertContains('FP-2026-000003', $adminPage);
        $this->assertContains('FP-2026-000004', $adminPage);
    }

    private function loginAs(string $email): void
    {
        $response = $this->post('/login', [
            'email' => $email,
            'password' => $this->seedPassword(),
        ]);
        $this->assertSame(302, $response->status());
        $this->assertTrue(app()->auth()->check());
    }

    private function logout(): void
    {
        $response = $this->post('/logout', []);
        $this->assertSame(302, $response->status());
        $this->assertFalse(app()->auth()->check());
    }

    /** @param array<string, string> $data */
    private function post(string $uri, array $data): Response
    {
        $data['_token'] = app()->csrf()->token();

        return app()->handle(Request::create('POST', $uri, $data));
    }

    private function seedPassword(): string
    {
        return (string) Env::get('SEED_PASSWORD', 'FixPoint!Dev1');
    }
}
