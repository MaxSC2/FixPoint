<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

final class ChatCenterHttpTest extends IntegrationTestCase
{
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';
    private const OTHER_CUSTOMER = 'customer.ivanova@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';
    private const OTHER_TECHNICIAN = 'master.smirnova@fixpoint.test';
    private const ADMIN = 'admin@fixpoint.test';

    protected function tearDown(): void
    {
        $statement = $this->pdo()->prepare(
            'SELECT id FROM repair_requests WHERE public_number LIKE :prefix'
        );
        $statement->execute(['prefix' => 'TASK007-%']);
        $ids = $statement->fetchAll(\PDO::FETCH_COLUMN);

        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->pdo()->prepare(
                'DELETE FROM chat_reads WHERE request_id IN (' . $placeholders . ')'
            )->execute($ids);
            $this->pdo()->prepare(
                'DELETE FROM request_messages WHERE request_id IN (' . $placeholders . ')'
            )->execute($ids);
            $this->pdo()->prepare(
                'DELETE FROM request_status_history WHERE request_id IN (' . $placeholders . ')'
            )->execute($ids);
            $this->pdo()->prepare(
                'DELETE FROM repair_requests WHERE id IN (' . $placeholders . ')'
            )->execute($ids);
        }

        parent::tearDown();
    }

    public function testCustomerInboxIsScopedAndUnreadFilterWorks(): void
    {
        $own = $this->seedRequest(self::CUSTOMER, self::TECHNICIAN);
        $foreign = $this->seedRequest(self::OTHER_CUSTOMER, self::OTHER_TECHNICIAN);
        $this->addMessage($own['id'], self::TECHNICIAN, 'Ответ мастера в моей заявке.');
        $this->addMessage($foreign['id'], self::OTHER_TECHNICIAN, 'Чужое сообщение, не показывать.');

        $this->loginAs(self::CUSTOMER);

        $all = app()->handle(Request::create('GET', '/messages'));
        $this->assertSame(200, $all->status());
        $this->assertContains($own['number'], $all->body());
        $this->assertContains('Ответ мастера в моей заявке.', $all->body());
        $this->assertNotContains($foreign['number'], $all->body());
        $this->assertNotContains('Чужое сообщение, не показывать.', $all->body());

        $unread = app()->handle(Request::create('GET', '/messages?filter=unread'));
        $this->assertSame(200, $unread->status());
        $this->assertContains($own['number'], $unread->body());
        $this->assertNotContains($foreign['number'], $unread->body());
    }

    public function testOpeningDialogAdvancesPersistentReadCursor(): void
    {
        $fixture = $this->seedRequest(self::CUSTOMER, self::TECHNICIAN);
        $messageId = $this->addMessage($fixture['id'], self::TECHNICIAN, 'Сообщение для отметки прочтения.');

        $this->loginAs(self::CUSTOMER);

        $inbox = app()->handle(Request::create('GET', '/messages'))->body();
        $this->assertContains('1 непрочит.', $inbox);
        $this->assertContains($fixture['number'], $inbox);

        $opened = $this->post('/messages/' . $fixture['id'] . '/read', []);
        $this->assertSame(302, $opened->status());
        $this->assertSame('/requests/' . $fixture['id'] . '#chat', $opened->header('Location'));

        $row = $this->pdo()->prepare(
            'SELECT last_read_message_id FROM chat_reads WHERE request_id = :request_id AND user_id = :user_id'
        );
        $row->execute([
            'request_id' => $fixture['id'],
            'user_id' => $this->userId(self::CUSTOMER),
        ]);
        $this->assertSame((string) $messageId, (string) $row->fetchColumn());

        $unread = app()->handle(Request::create('GET', '/messages?filter=unread'))->body();
        $this->assertNotContains($fixture['number'], $unread);
        $this->assertContains('Непрочитанных сообщений нет', $unread);
    }

    public function testTechnicianInboxOnlyContainsAssignedRequests(): void
    {
        $assigned = $this->seedRequest(self::CUSTOMER, self::TECHNICIAN);
        $other = $this->seedRequest(self::OTHER_CUSTOMER, self::OTHER_TECHNICIAN);
        $this->addMessage($assigned['id'], self::CUSTOMER, 'Вопрос назначенному мастеру.');
        $this->addMessage($other['id'], self::OTHER_CUSTOMER, 'Чужой вопрос мастеру.');

        $this->loginAs(self::TECHNICIAN);
        $inbox = app()->handle(Request::create('GET', '/messages'));

        $this->assertSame(200, $inbox->status());
        $this->assertContains($assigned['number'], $inbox->body());
        $this->assertNotContains($other['number'], $inbox->body());
    }

    public function testCustomerCannotMarkAnotherCustomersThreadAsRead(): void
    {
        $foreign = $this->seedRequest(self::OTHER_CUSTOMER, self::OTHER_TECHNICIAN);
        $this->addMessage($foreign['id'], self::OTHER_TECHNICIAN, 'Чужой диалог.');

        $this->loginAs(self::CUSTOMER);
        $response = $this->post('/messages/' . $foreign['id'] . '/read', []);

        $this->assertSame(404, $response->status());
        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM chat_reads')->fetchColumn());
    }

    public function testReadActionRequiresCsrfToken(): void
    {
        $fixture = $this->seedRequest(self::CUSTOMER, self::TECHNICIAN);
        $this->addMessage($fixture['id'], self::TECHNICIAN, 'Сообщение должно остаться непрочитанным.');

        $this->loginAs(self::CUSTOMER);
        $response = app()->handle(Request::create('POST', '/messages/' . $fixture['id'] . '/read'));

        $this->assertSame(419, $response->status());
        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM chat_reads')->fetchColumn());
    }

    public function testAdminInboxCanSeeAllThreadsAndOpenTheStaffCard(): void
    {
        $own = $this->seedRequest(self::CUSTOMER, self::TECHNICIAN);
        $other = $this->seedRequest(self::OTHER_CUSTOMER, self::OTHER_TECHNICIAN);
        $this->addMessage($own['id'], self::CUSTOMER, 'Первый диалог для поддержки.');
        $this->addMessage($other['id'], self::OTHER_CUSTOMER, 'Второй диалог для поддержки.');

        $this->loginAs(self::ADMIN);
        $inbox = app()->handle(Request::create('GET', '/messages'));
        $this->assertSame(200, $inbox->status());
        $this->assertContains($own['number'], $inbox->body());
        $this->assertContains($other['number'], $inbox->body());

        $opened = $this->post('/messages/' . $other['id'] . '/read', []);
        $this->assertSame(302, $opened->status());
        $this->assertSame('/technician/requests/' . $other['id'] . '#chat', $opened->header('Location'));
    }

    public function testUnreadBadgeIsVisibleInTheSharedHeader(): void
    {
        $fixture = $this->seedRequest(self::CUSTOMER, self::TECHNICIAN);
        $this->addMessage($fixture['id'], self::TECHNICIAN, 'Новое сообщение для бейджа.');

        $this->loginAs(self::CUSTOMER);
        $dashboard = app()->handle(Request::create('GET', '/account'));
        $this->assertSame(200, $dashboard->status());
        $this->assertContains('href="/messages"', $dashboard->body());
        $this->assertContains('header-messages__badge', $dashboard->body());
        $this->assertContains('aria-label="1 непрочитанных"', $dashboard->body());
    }

    /** @return array{id: int, number: string} */
    private function seedRequest(string $customerEmail, string $technicianEmail): array
    {
        $number = 'TASK007-' . strtoupper(bin2hex(random_bytes(4)));
        $statement = $this->pdo()->prepare(
            'INSERT INTO repair_requests
                (public_number, customer_id, technician_id, status, priority, device_type,
                 device_brand, device_model, problem_description, customer_contact)
             VALUES (:number, :customer_id, :technician_id, \'repairing\', \'normal\', \'Ноутбук\',
                 \'TASK007-Brand\', \'TASK007-Model\', \'TASK007: chat center fixture.\', \'TASK007-contact\')'
        );
        $statement->execute([
            'number' => $number,
            'customer_id' => $this->userId($customerEmail),
            'technician_id' => $this->userId($technicianEmail),
        ]);

        return ['id' => (int) $this->pdo()->lastInsertId(), 'number' => $number];
    }

    private function addMessage(int $requestId, string $authorEmail, string $body): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO request_messages (request_id, author_id, body)
             VALUES (:request_id, :author_id, :body)'
        );
        $statement->execute([
            'request_id' => $requestId,
            'author_id' => $this->userId($authorEmail),
            'body' => $body,
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
