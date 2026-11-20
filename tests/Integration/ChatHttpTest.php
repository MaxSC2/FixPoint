<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

/**
 * Chat of a repair request: the customer, the assigned technician and support
 * share one thread, and nobody outside of it may read or write.
 */
final class ChatHttpTest extends IntegrationTestCase
{
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';
    private const OTHER_CUSTOMER = 'customer.ivanova@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';
    private const OTHER_TECHNICIAN = 'master.smirnova@fixpoint.test';
    private const ADMIN = 'admin@fixpoint.test';

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        $statement = $pdo->prepare('SELECT id FROM repair_requests WHERE public_number LIKE :prefix');
        $statement->execute(['prefix' => 'TASK006-%']);
        $ids = $statement->fetchAll(\PDO::FETCH_COLUMN);

        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare('DELETE FROM chat_reads WHERE request_id IN (' . $placeholders . ')')
                ->execute($ids);
            $pdo->prepare('DELETE FROM request_messages WHERE request_id IN (' . $placeholders . ')')
                ->execute($ids);
            $pdo->prepare('DELETE FROM request_status_history WHERE request_id IN (' . $placeholders . ')')
                ->execute($ids);
            $pdo->prepare('DELETE FROM repair_requests WHERE id IN (' . $placeholders . ')')
                ->execute($ids);
        }

        parent::tearDown();
    }

    public function testCustomerTechnicianAndSupportShareOneThread(): void
    {
        $requestId = $this->seedRequestForCustomer();

        // The customer asks a question.
        $this->loginAs(self::CUSTOMER);
        $customerPost = $this->post('/requests/' . $requestId . '/messages', [
            'body' => 'Устройство готово к выдаче? Можно забрать после 18:00?',
        ]);
        $this->assertSame(302, $customerPost->status());
        $this->assertSame('/requests/' . $requestId, $customerPost->header('Location'));

        $customerCard = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertContains('Устройство готово к выдаче?', $customerCard);
        $this->assertContains('Чат по заявке', $customerCard);
        $this->assertContains('1 сообщение', $customerCard);

        // The assigned technician answers.
        $this->logout();
        $this->loginAs(self::TECHNICIAN);
        $technicianPost = $this->post('/technician/requests/' . $requestId . '/messages', [
            'body' => 'Да, ремонт закончен, можно забрать после 18:00.',
        ]);
        $this->assertSame(302, $technicianPost->status());
        $this->assertSame('/technician/requests/' . $requestId, $technicianPost->header('Location'));

        $technicianCard = app()->handle(Request::create('GET', '/technician/requests/' . $requestId))->body();
        $this->assertContains('Устройство готово к выдаче?', $technicianCard);
        $this->assertContains('Да, ремонт закончен, можно забрать после 18:00.', $technicianCard);
        $this->assertContains('2 сообщения', $technicianCard);

        // Support answers as well and sees the whole thread.
        $this->logout();
        $this->loginAs(self::ADMIN);
        $adminPost = $this->post('/technician/requests/' . $requestId . '/messages', [
            'body' => 'Поддержка подключилась: выписка и гарантия уже оформлены.',
        ]);
        $this->assertSame(302, $adminPost->status());

        $adminCard = app()->handle(Request::create('GET', '/technician/requests/' . $requestId))->body();
        $this->assertContains('Поддержка подключилась: выписка и гарантия уже оформлены.', $adminCard);
        $this->assertContains('Устройство готово к выдаче?', $adminCard);
        $this->assertContains('Поддержка', $adminCard);

        // The customer sees the answers.
        $this->logout();
        $this->loginAs(self::CUSTOMER);
        $customerCard = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertContains('Да, ремонт закончен, можно забрать после 18:00.', $customerCard);
        $this->assertContains('Поддержка подключилась: выписка и гарантия уже оформлены.', $customerCard);
        $this->assertContains('3 сообщения', $customerCard);
    }

    public function testForeignUsersCannotReadOrWriteTheChat(): void
    {
        $requestId = $this->seedRequestForCustomer();
        $this->loginAs(self::CUSTOMER);
        $this->post('/requests/' . $requestId . '/messages', ['body' => 'Сообщение владельца заявки.']);
        $this->logout();

        // Another customer neither sees nor writes into the thread.
        $this->loginAs(self::OTHER_CUSTOMER);
        $foreignRead = app()->handle(Request::create('GET', '/requests/' . $requestId));
        $this->assertSame(404, $foreignRead->status());
        $foreignPost = $this->post('/requests/' . $requestId . '/messages', ['body' => 'Чужое сообщение.']);
        $this->assertSame(404, $foreignPost->status());
        $this->logout();

        // A technician without the assignment has no access either.
        $this->loginAs(self::OTHER_TECHNICIAN);
        $staffRead = app()->handle(Request::create('GET', '/technician/requests/' . $requestId));
        $this->assertSame(404, $staffRead->status());
        $staffPost = $this->post('/technician/requests/' . $requestId . '/messages', ['body' => 'Чужое сообщение мастера.']);
        $this->assertSame(404, $staffPost->status());
        $this->logout();

        // Nothing of the foreign attempts was written.
        $this->loginAs(self::CUSTOMER);
        $card = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertNotContains('Чужое сообщение', $card);
        $this->assertContains('1 сообщение', $card);
    }

    public function testEmptyMessageIsRejected(): void
    {
        $requestId = $this->seedRequestForCustomer();
        $this->loginAs(self::CUSTOMER);

        $response = $this->post('/requests/' . $requestId . '/messages', ['body' => '   ']);
        $this->assertSame(302, $response->status());

        $card = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertContains('Введите сообщение или прикрепите изображение.', $card);
        $this->assertContains('0 сообщений', $card);
    }

    public function testChatMessageIsEscaped(): void
    {
        $requestId = $this->seedRequestForCustomer();
        $this->loginAs(self::CUSTOMER);

        $this->post('/requests/' . $requestId . '/messages', [
            'body' => '<script>alert("xss")</script>',
        ]);

        $card = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertNotContains('<script>alert("xss")</script>', $card);
        $this->assertContains('&lt;script&gt;', $card);
    }

    public function testChatRequiresCsrfToken(): void
    {
        $requestId = $this->seedRequestForCustomer();
        $this->loginAs(self::CUSTOMER);

        $response = app()->handle(Request::create('POST', '/requests/' . $requestId . '/messages', [
            'body' => 'Без токена.',
        ]));
        $this->assertSame(419, $response->status());

        $card = app()->handle(Request::create('GET', '/requests/' . $requestId))->body();
        $this->assertNotContains('Без токена.', $card);
    }

    /** A request of the customer, assigned to the technician. */
    private function seedRequestForCustomer(): int
    {        $customerId = $this->userId(self::CUSTOMER);
        $technicianId = $this->userId(self::TECHNICIAN);

        $statement = $this->pdo()->prepare(
            'INSERT INTO repair_requests
                (public_number, customer_id, technician_id, status, priority, device_type,
                 device_brand, device_model, problem_description, customer_contact)
             VALUES (:number, :customer_id, :technician_id, \'repairing\', \'normal\', \'Смартфон\',
                 \'TASK006-Brand\', \'TASK006-Model\', \'TASK006: тестовая заявка для чата.\', \'TASK006-contact\')'
        );
        $statement->execute([
            'number' => 'TASK006-' . strtoupper(bin2hex(random_bytes(4))),
            'customer_id' => $customerId,
            'technician_id' => $technicianId,
        ]);

        return (int) $this->pdo()->lastInsertId();
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

    private function pdo(): \PDO
    {
        return app()->database()->connection();
    }

    private function userId(string $email): int
    {
        $statement = $this->pdo()->prepare('SELECT id FROM users WHERE email = :email');
        $statement->execute(['email' => $email]);
        $id = $statement->fetchColumn();

        $this->assertNotSame(false, $id, 'Unknown user: ' . $email);

        return (int) $id;
    }
}
