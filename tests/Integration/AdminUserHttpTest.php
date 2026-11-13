<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

/**
 * End-to-end HTTP tests for the administrator user workspace: listing,
 * creation with roles, profile/activity updates, password handling, duplicate
 * emails and last-admin protection. Fixtures use the task005b- email prefix
 * and are removed in tearDown so seed data stays untouched.
 */
final class AdminUserHttpTest extends IntegrationTestCase
{
    private const EMAIL_PREFIX = 'task005b-';
    private const ADMIN = 'admin@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';

    protected function tearDown(): void
    {
        $this->pdo()
            ->prepare('DELETE FROM users WHERE email LIKE :prefix')
            ->execute(['prefix' => self::EMAIL_PREFIX . '%']);
    }

    public function testGuestIsRedirectedFromAdminUserRoutes(): void
    {
        $fixture = $this->createFixtureUser();

        $index = app()->handle(Request::create('GET', '/admin/users'));
        $this->assertSame(302, $index->status());
        $this->assertContains('/login', (string) $index->header('Location'));
        $this->assertContains('return_to=%2Fadmin%2Fusers', (string) $index->header('Location'));

        $edit = app()->handle(Request::create('GET', '/admin/users/' . $fixture['id'] . '/edit'));
        $this->assertSame(302, $edit->status());
        $this->assertContains(
            'return_to=%2Fadmin%2Fusers%2F' . $fixture['id'] . '%2Fedit',
            (string) $edit->header('Location')
        );

        $create = app()->handle(Request::create('POST', '/admin/users', [
            'full_name' => 'TASK005B Guest',
            'email' => $this->email('guest-created'),
            'role' => 'admin',
            'password' => 'StrongPass1',
        ]));
        $this->assertSame(302, $create->status());
        $this->assertContains('/login', (string) $create->header('Location'));
        $this->assertSame(0, $this->userCount($this->email('guest-created')));

        $update = app()->handle(Request::create('POST', '/admin/users/' . $fixture['id'] . '/edit', [
            'full_name' => 'TASK005B Guest Changed',
            'email' => $fixture['email'],
            'role' => 'admin',
            'is_active' => '1',
        ]));
        $this->assertSame(302, $update->status());
        $this->assertSame('customer', $this->userRow($fixture['id'])['role']);
    }

    public function testCustomerAndTechnicianCannotManageUsers(): void
    {
        $fixture = $this->createFixtureUser();
        $adminId = $this->userId(self::ADMIN);

        foreach ([self::CUSTOMER, self::TECHNICIAN] as $email) {
            $this->loginAs($email);

            $this->assertSame(403, app()->handle(Request::create('GET', '/admin/users'))->status());
            $this->assertSame(403, app()->handle(Request::create('GET', '/admin/users/' . $fixture['id'] . '/edit'))->status());
            $this->assertSame(403, $this->post('/admin/users', [
                'full_name' => 'TASK005B Forbidden',
                'email' => $this->email('forbidden'),
                'role' => 'admin',
                'password' => 'StrongPass1',
            ])->status());
            $this->assertSame(403, $this->post('/admin/users/' . $fixture['id'] . '/edit', [
                'full_name' => 'TASK005B Forbidden Change',
                'email' => $fixture['email'],
                'role' => 'admin',
                'is_active' => '1',
            ])->status());

            $this->assertSame(0, $this->userCount($this->email('forbidden')));
            $this->assertSame('customer', $this->userRow($fixture['id'])['role']);
            $this->assertSame('TASK005B Customer', $this->userRow($fixture['id'])['full_name']);
            $this->logout();
        }

        $this->assertSame(1, $this->userCount(self::ADMIN));
        $this->assertGreaterThan(0, $adminId);
    }

    public function testIndexListsUsersAndCreateFormWithoutPasswordHashes(): void
    {
        $fixture = $this->createFixtureUser(['full_name' => 'TASK005B Listed User']);
        $this->loginAs(self::ADMIN);

        $response = app()->handle(Request::create('GET', '/admin/users'));
        $body = $response->body();
        $this->assertSame(200, $response->status());
        $this->assertContains('Пользователи', $body);
        $this->assertContains(self::ADMIN, $body);
        $this->assertContains(self::TECHNICIAN, $body);
        $this->assertContains(self::CUSTOMER, $body);
        $this->assertContains('inactive@fixpoint.test', $body);
        $this->assertContains($fixture['email'], $body);
        $this->assertContains('TASK005B Listed User', $body);
        $this->assertContains('action="/admin/users"', $body);
        $this->assertContains('name="_token"', $body);
        $this->assertContains('name="password"', $body);
        $this->assertNotContains('password_hash', $body);
        $this->assertNotContains('$2y$', $body);
        $this->assertNotContains('FixPoint!Dev1', $body);
    }

    public function testCreateStoresActiveUserWithSelectedRoleAndHashedPassword(): void
    {
        $email = $this->email('created-tech');
        $this->loginAs(self::ADMIN);

        $response = $this->post('/admin/users', [
            'full_name' => 'TASK005B New Technician',
            'email' => $email,
            'phone' => '+7 700 111 22 33',
            'role' => 'technician',
            'password' => 'StrongPass1',
        ]);
        $this->assertSame(302, $response->status());
        $this->assertSame('/admin/users', $response->header('Location'));

        $row = $this->userRow($this->userId($email));
        $this->assertSame('TASK005B New Technician', $row['full_name']);
        $this->assertSame($email, $row['email']);
        $this->assertSame('+7 700 111 22 33', $row['phone']);
        $this->assertSame('technician', $row['role']);
        $this->assertSame(1, (int) $row['is_active']);
        $this->assertNotSame('StrongPass1', $row['password_hash']);
        $this->assertTrue(password_verify('StrongPass1', (string) $row['password_hash']));

        $list = app()->handle(Request::create('GET', '/admin/users'))->body();
        $this->assertContains('Учётная запись создана.', $list);
        $this->assertContains($email, $list);
        $this->assertNotContains('StrongPass1', $list);

        $this->logout();
        $login = $this->post('/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ]);
        $this->assertSame(302, $login->status());
        $this->assertSame('/technician/requests', $login->header('Location'));
        $this->assertTrue(app()->auth()->check());
        $this->assertSame('technician', app()->auth()->role());
    }

    public function testCreateValidatesInputAndKeepsOldValues(): void
    {
        $before = $this->countUsers();
        $validEmail = $this->email('validation');
        $this->loginAs(self::ADMIN);

        $cases = [
            [['full_name' => 'A', 'email' => $validEmail, 'role' => 'customer', 'password' => 'StrongPass1'], 'имя'],
            [['full_name' => 'TASK005B Bad Mail', 'email' => 'not-an-email', 'role' => 'customer', 'password' => 'StrongPass1'], 'email'],
            [['full_name' => 'TASK005B Short Pass', 'email' => $validEmail, 'role' => 'customer', 'password' => 'short1'], 'пароль'],
            [['full_name' => 'TASK005B Letters Only', 'email' => $validEmail, 'role' => 'customer', 'password' => 'onlyletters'], 'Пароль'],
            [['full_name' => 'TASK005B Bad Role', 'email' => $validEmail, 'role' => 'owner', 'password' => 'StrongPass1'], 'роль'],
            [['full_name' => 'TASK005B Bad Phone', 'email' => $validEmail, 'phone' => 'abc', 'role' => 'customer', 'password' => 'StrongPass1'], 'телефон'],
        ];

        foreach ($cases as [$payload, $label]) {
            $response = $this->post('/admin/users', $payload);
            $this->assertSame(302, $response->status(), 'Validation failure must redirect for: ' . $label);
            $this->assertSame('/admin/users', $response->header('Location'));
        }

        $duplicate = $this->post('/admin/users', [
            'full_name' => 'TASK005B Duplicate',
            'email' => self::CUSTOMER,
            'phone' => '+7 700 444 55 66',
            'role' => 'admin',
            'password' => 'StrongPass1',
        ]);
        $this->assertSame(302, $duplicate->status());
        $this->assertSame($before, $this->countUsers());
        $this->assertSame(1, $this->userCount(self::CUSTOMER));

        $body = app()->handle(Request::create('GET', '/admin/users'))->body();
        $this->assertContains('Пользователь с таким email уже существует.', $body);
        $this->assertContains('value="TASK005B Duplicate"', $body);
        $this->assertContains('value="+7 700 444 55 66"', $body);
        $this->assertContains('value="admin" selected', $body);
        $this->assertNotContains('StrongPass1', $body);
    }

    public function testEditFormLoadsUserAndUnknownIdsAreNotFound(): void
    {
        $fixture = $this->createFixtureUser([
            'full_name' => 'TASK005B Editable',
            'phone' => '+7 700 777 88 99',
        ]);
        $this->loginAs(self::ADMIN);

        $response = app()->handle(Request::create('GET', '/admin/users/' . $fixture['id'] . '/edit'));
        $body = $response->body();
        $this->assertSame(200, $response->status());
        $this->assertContains('Редактировать пользователя', $body);
        $this->assertContains('action="/admin/users/' . $fixture['id'] . '/edit"', $body);
        $this->assertContains('value="TASK005B Editable"', $body);
        $this->assertContains('value="' . $fixture['email'] . '"', $body);
        $this->assertContains('value="+7 700 777 88 99"', $body);
        $this->assertContains('name="_token"', $body);
        $this->assertContains('name="is_active"', $body);
        $this->assertNotContains('name="password"', $body);
        $this->assertNotContains('password_hash', $body);
        $this->assertNotContains('$2y$', $body);

        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/users/999999999/edit'))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/users/0/edit'))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/users/not-a-number/edit'))->status());
    }

    public function testUpdateChangesProfileRoleAndActivityWithoutTouchingPassword(): void
    {
        $fixture = $this->createFixtureUser([
            'role' => 'customer',
            'active' => 1,
            'password' => 'CustomerPass1',
        ]);
        $originalHash = (string) $this->userRow($fixture['id'])['password_hash'];
        $newEmail = $this->email('updated-tech');
        $this->loginAs(self::ADMIN);

        $response = $this->post('/admin/users/' . $fixture['id'] . '/edit', [
            'full_name' => 'TASK005B Promoted Master',
            'email' => $newEmail,
            'phone' => '+7 700 123 45 67',
            'role' => 'technician',
            'is_active' => '1',
        ]);
        $this->assertSame(302, $response->status());
        $this->assertSame('/admin/users', $response->header('Location'));

        $row = $this->userRow($fixture['id']);
        $this->assertSame('TASK005B Promoted Master', $row['full_name']);
        $this->assertSame($newEmail, $row['email']);
        $this->assertSame('+7 700 123 45 67', $row['phone']);
        $this->assertSame('technician', $row['role']);
        $this->assertSame(1, (int) $row['is_active']);
        $this->assertSame($originalHash, $row['password_hash']);

        $deactivate = $this->post('/admin/users/' . $fixture['id'] . '/edit', [
            'full_name' => 'TASK005B Promoted Master',
            'email' => $newEmail,
            'phone' => '+7 700 123 45 67',
            'role' => 'technician',
            'is_active' => '0',
        ]);
        $this->assertSame(302, $deactivate->status());
        $this->assertSame(0, (int) $this->userRow($fixture['id'])['is_active']);

        $this->logout();
        $blocked = $this->post('/login', [
            'email' => $newEmail,
            'password' => 'CustomerPass1',
        ]);
        $this->assertSame(302, $blocked->status());
        $this->assertContains('/login', (string) $blocked->header('Location'));
        $this->assertFalse(app()->auth()->check());

        $this->loginAs(self::ADMIN);
        $reactivate = $this->post('/admin/users/' . $fixture['id'] . '/edit', [
            'full_name' => 'TASK005B Promoted Master',
            'email' => $newEmail,
            'phone' => '+7 700 123 45 67',
            'role' => 'technician',
            'is_active' => '1',
        ]);
        $this->assertSame(302, $reactivate->status());
        $this->assertSame(1, (int) $this->userRow($fixture['id'])['is_active']);

        $this->logout();
        $login = $this->post('/login', [
            'email' => $newEmail,
            'password' => 'CustomerPass1',
        ]);
        $this->assertSame(302, $login->status());
        $this->assertSame('/technician/requests', $login->header('Location'));
        $this->assertSame($originalHash, (string) $this->userRow($fixture['id'])['password_hash']);
    }

    public function testUpdateRejectsDuplicateEmailAndInvalidInput(): void
    {
        $fixture = $this->createFixtureUser(['phone' => '+7 700 000 11 22']);
        $original = $this->userRow($fixture['id']);
        $this->loginAs(self::ADMIN);

        $duplicate = $this->post('/admin/users/' . $fixture['id'] . '/edit', [
            'full_name' => 'TASK005B Duplicate Update',
            'email' => self::CUSTOMER,
            'phone' => '+7 700 000 11 22',
            'role' => 'technician',
            'is_active' => '1',
        ]);
        $this->assertSame(302, $duplicate->status());
        $this->assertSame('/admin/users/' . $fixture['id'] . '/edit', $duplicate->header('Location'));
        $this->assertSame($original['email'], $this->userRow($fixture['id'])['email']);
        $this->assertSame('customer', $this->userRow($fixture['id'])['role']);

        $body = app()->handle(Request::create('GET', '/admin/users/' . $fixture['id'] . '/edit'))->body();
        $this->assertContains('Пользователь с таким email уже существует.', $body);
        $this->assertContains('value="TASK005B Duplicate Update"', $body);

        $invalidRole = $this->post('/admin/users/' . $fixture['id'] . '/edit', [
            'full_name' => 'TASK005B Bad Update Role',
            'email' => $fixture['email'],
            'phone' => '+7 700 000 11 22',
            'role' => 'owner',
            'is_active' => '1',
        ]);
        $this->assertSame(302, $invalidRole->status());
        $this->assertSame('customer', $this->userRow($fixture['id'])['role']);
        $this->assertSame($original['full_name'], $this->userRow($fixture['id'])['full_name']);

        $invalidActive = $this->post('/admin/users/' . $fixture['id'] . '/edit', [
            'full_name' => 'TASK005B Bad Active',
            'email' => $fixture['email'],
            'phone' => '+7 700 000 11 22',
            'role' => 'customer',
            'is_active' => 'maybe',
        ]);
        $this->assertSame(302, $invalidActive->status());
        $this->assertSame(1, (int) $this->userRow($fixture['id'])['is_active']);

        $body = app()->handle(Request::create('GET', '/admin/users/' . $fixture['id'] . '/edit'))->body();
        $this->assertContains('Некорректное состояние учётной записи.', $body);
    }

    public function testAdminCannotDemoteOrDeactivateSelf(): void
    {
        $adminId = $this->userId(self::ADMIN);
        $this->loginAs(self::ADMIN);

        $demote = $this->post('/admin/users/' . $adminId . '/edit', [
            'full_name' => 'Администратор FixPoint',
            'email' => self::ADMIN,
            'phone' => '+7 700 000 00 01',
            'role' => 'customer',
            'is_active' => '1',
        ]);
        $this->assertSame(302, $demote->status());
        $this->assertSame('/admin/users/' . $adminId . '/edit', $demote->header('Location'));
        $this->assertSame('admin', $this->userRow($adminId)['role']);

        $demotedBody = app()->handle(Request::create('GET', '/admin/users/' . $adminId . '/edit'))->body();
        $this->assertContains('Нельзя снять с себя роль администратора.', $demotedBody);

        $deactivate = $this->post('/admin/users/' . $adminId . '/edit', [
            'full_name' => 'Администратор FixPoint',
            'email' => self::ADMIN,
            'phone' => '+7 700 000 00 01',
            'role' => 'admin',
            'is_active' => '0',
        ]);
        $this->assertSame(302, $deactivate->status());
        $this->assertSame(1, (int) $this->userRow($adminId)['is_active']);

        $deactivatedBody = app()->handle(Request::create('GET', '/admin/users/' . $adminId . '/edit'))->body();
        $this->assertContains('Нельзя отключить собственную учётную запись.', $deactivatedBody);

        $both = $this->post('/admin/users/' . $adminId . '/edit', [
            'full_name' => 'Администратор FixPoint',
            'email' => self::ADMIN,
            'phone' => '+7 700 000 00 01',
            'role' => 'technician',
            'is_active' => '0',
        ]);
        $this->assertSame(302, $both->status());
        $row = $this->userRow($adminId);
        $this->assertSame('admin', $row['role']);
        $this->assertSame(1, (int) $row['is_active']);
    }

    public function testCsrfAndUnknownUsersAreRejected(): void
    {
        $fixture = $this->createFixtureUser();
        $this->loginAs(self::ADMIN);
        $email = $this->email('csrf');

        $noTokenCreate = app()->handle(Request::create('POST', '/admin/users', [
            'full_name' => 'TASK005B No Token',
            'email' => $email,
            'role' => 'admin',
            'password' => 'StrongPass1',
        ]));
        $this->assertSame(419, $noTokenCreate->status());
        $this->assertSame(0, $this->userCount($email));

        $noTokenEdit = app()->handle(Request::create('POST', '/admin/users/' . $fixture['id'] . '/edit', [
            'full_name' => 'TASK005B No Token Edit',
            'email' => $fixture['email'],
            'role' => 'admin',
            'is_active' => '1',
        ]));
        $this->assertSame(419, $noTokenEdit->status());
        $this->assertSame('customer', $this->userRow($fixture['id'])['role']);

        $this->assertSame(404, $this->post('/admin/users/999999999/edit', [
            'full_name' => 'TASK005B Unknown',
            'email' => $fixture['email'],
            'role' => 'customer',
            'is_active' => '1',
        ])->status());
    }

    /** @param array<string, mixed> $overrides
     *  @return array{id: int, email: string, password: string} */
    private function createFixtureUser(array $overrides = []): array
    {
        $role = (string) ($overrides['role'] ?? 'customer');
        $active = (int) ($overrides['active'] ?? 1);
        $password = (string) ($overrides['password'] ?? 'FixturePass1');
        $email = (string) ($overrides['email'] ?? $this->email('fixture'));

        $statement = $this->pdo()->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, is_active)
             VALUES (:full_name, :email, :phone, :password_hash, :role, :is_active)'
        );
        $statement->execute([
            'full_name' => (string) ($overrides['full_name'] ?? 'TASK005B Customer'),
            'email' => $email,
            'phone' => $overrides['phone'] ?? null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => $active,
        ]);

        return [
            'id' => (int) $this->pdo()->lastInsertId(),
            'email' => $email,
            'password' => $password,
        ];
    }

    private function loginAs(string $email): void
    {
        $response = $this->post('/login', [
            'email' => $email,
            'password' => (string) Env::get('SEED_PASSWORD', 'FixPoint!Dev1'),
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

    private function pdo(): \PDO
    {
        return app()->database()->connection();
    }

    private function email(string $label): string
    {
        return self::EMAIL_PREFIX . $label . '-' . bin2hex(random_bytes(3)) . '@fixpoint.test';
    }

    private function userId(string $email): int
    {
        $statement = $this->pdo()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            $this->fail('User not found: ' . $email);
        }

        return (int) $id;
    }

    /** @return array<string, mixed> */
    private function userRow(int $id): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? [] : $row;
    }

    private function userCount(string $email): int
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $statement->execute(['email' => $email]);

        return (int) $statement->fetchColumn();
    }

    private function countUsers(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
