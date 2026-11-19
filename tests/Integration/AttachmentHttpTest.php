<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\UploadedFile;
use FixPoint\Services\AttachmentService;
use FixPoint\Tests\IntegrationTestCase;

final class AttachmentHttpTest extends IntegrationTestCase
{
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';
    private const OTHER_CUSTOMER = 'customer.ivanova@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';
    private const OTHER_TECHNICIAN = 'master.smirnova@fixpoint.test';

    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        $statement = $this->pdo()->prepare(
            "SELECT id FROM repair_requests
             WHERE public_number LIKE 'TASK008-%' OR problem_description LIKE 'TASK008%'"
        );
        $statement->execute();
        $ids = array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));

        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $keys = $this->pdo()->prepare(
                'SELECT storage_key FROM request_attachments WHERE request_id IN (' . $placeholders . ')'
            );
            $keys->execute($ids);
            app()->make(AttachmentService::class)->cleanup(
                array_map('strval', $keys->fetchAll(\PDO::FETCH_COLUMN))
            );

            $this->pdo()->prepare('DELETE FROM chat_reads WHERE request_id IN (' . $placeholders . ')')
                ->execute($ids);
            $this->pdo()->prepare('DELETE FROM request_messages WHERE request_id IN (' . $placeholders . ')')
                ->execute($ids);
            $this->pdo()->prepare('DELETE FROM request_status_history WHERE request_id IN (' . $placeholders . ')')
                ->execute($ids);
            $this->pdo()->prepare('DELETE FROM repair_requests WHERE id IN (' . $placeholders . ')')
                ->execute($ids);
        }

        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testCustomerCanAttachImagesToRequestAndOnlyParticipantsCanOpenThem(): void
    {
        $this->loginAs(self::CUSTOMER);
        $created = $this->postWithFiles('/requests/create', $this->requestData(), [
            $this->validPng('screen-photo.png'),
        ]);

        $this->assertSame(302, $created->status());
        $location = (string) $created->header('Location');
        $this->assertTrue(preg_match('#^/requests/([0-9]+)$#', $location, $matches) === 1);
        $requestId = (int) $matches[1];

        $card = app()->handle(Request::create('GET', $location));
        $this->assertSame(200, $card->status());
        $this->assertContains('screen-photo.png', $card->body());
        $this->assertContains('/attachments/', $card->body());

        $row = $this->pdo()->prepare(
            'SELECT id, storage_key, mime_type FROM request_attachments
             WHERE request_id = :request_id AND message_id IS NULL'
        );
        $row->execute(['request_id' => $requestId]);
        $attachment = $row->fetch();
        $this->assertTrue(is_array($attachment));
        $this->assertSame('image/png', $attachment['mime_type']);
        $this->assertNotContains('/public/', (string) $attachment['storage_key']);
        $privatePath = rtrim((string) app()->config()->get('paths.base'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $attachment['storage_key']);
        $this->assertTrue(is_file($privatePath));
        $this->assertFalse(is_file(
            rtrim((string) app()->config()->get('paths.base'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $attachment['storage_key'])
        ));

        $url = '/requests/' . $requestId . '/attachments/' . (int) $attachment['id'];
        $image = app()->handle(Request::create('GET', $url));
        $this->assertSame(200, $image->status());
        $this->assertSame('image/png', $image->header('Content-Type'));
        $this->assertContains('private', (string) $image->header('Cache-Control'));
        $this->assertGreaterThan(0, strlen($image->body()));

        // The request id in the route must match the actual parent request.
        $mismatched = app()->handle(Request::create(
            'GET',
            '/requests/999999/attachments/' . (int) $attachment['id']
        ));
        $this->assertSame(404, $mismatched->status());

        $this->logout();
        $this->loginAs(self::OTHER_CUSTOMER);
        $foreign = app()->handle(Request::create('GET', $url));
        $this->assertSame(404, $foreign->status());

        $this->logout();
        $this->loginAs(self::TECHNICIAN);
        $assignedTechnician = app()->handle(Request::create('GET', $url));
        $this->assertSame(200, $assignedTechnician->status());

        $this->logout();
        $this->loginAs(self::OTHER_TECHNICIAN);
        $unassignedTechnician = app()->handle(Request::create('GET', $url));
        $this->assertSame(404, $unassignedTechnician->status());
    }

    public function testChatSupportsImageOnlyMessagesAndRendersTheirAttachments(): void
    {
        $requestId = $this->seedRequest();
        $this->loginAs(self::CUSTOMER);

        $response = $this->postWithFiles('/requests/' . $requestId . '/messages', [
            'body' => '',
        ], [
            $this->validPng('motherboard.png'),
        ]);
        $this->assertSame(302, $response->status());

        $message = $this->pdo()->prepare(
            'SELECT id, body FROM request_messages WHERE request_id = :request_id ORDER BY id DESC LIMIT 1'
        );
        $message->execute(['request_id' => $requestId]);
        $messageRow = $message->fetch();
        $this->assertTrue(is_array($messageRow));
        $this->assertSame(null, $messageRow['body']);

        $attachment = $this->pdo()->prepare(
            'SELECT id, message_id FROM request_attachments WHERE request_id = :request_id'
        );
        $attachment->execute(['request_id' => $requestId]);
        $attachmentRow = $attachment->fetch();
        $this->assertTrue(is_array($attachmentRow));
        $this->assertSame((string) $messageRow['id'], (string) $attachmentRow['message_id']);

        $card = app()->handle(Request::create('GET', '/requests/' . $requestId));
        $this->assertSame(200, $card->status());
        $this->assertContains('motherboard.png', $card->body());
        $this->assertContains('/attachments/' . (int) $attachmentRow['id'], $card->body());

        $this->logout();
        $this->loginAs(self::TECHNICIAN);
        $staffCard = app()->handle(Request::create('GET', '/technician/requests/' . $requestId));
        $this->assertSame(200, $staffCard->status());
        $this->assertContains('motherboard.png', $staffCard->body());
    }

    public function testPerRequestLimitOfTenImagesAppliesAcrossMultipleMessages(): void
    {
        $requestId = $this->seedRequest();
        $this->loginAs(self::CUSTOMER);

        $first = [];
        for ($index = 0; $index < 5; $index++) {
            $first[] = $this->validPng('batch-one-' . $index . '.png');
        }
        $firstResponse = $this->postWithFiles('/requests/' . $requestId . '/messages', ['body' => 'Первый набор.'], $first);
        $this->assertSame(302, $firstResponse->status());

        $second = [];
        for ($index = 0; $index < 5; $index++) {
            $second[] = $this->validPng('batch-two-' . $index . '.png');
        }
        $secondResponse = $this->postWithFiles('/requests/' . $requestId . '/messages', ['body' => 'Второй набор.'], $second);
        $this->assertSame(302, $secondResponse->status());

        $count = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM request_attachments WHERE request_id = :request_id'
        );
        $count->execute(['request_id' => $requestId]);
        $this->assertSame(10, (int) $count->fetchColumn());

        $eleventh = $this->postWithFiles('/requests/' . $requestId . '/messages', [
            'body' => 'Попытка добавить одиннадцатое изображение.',
        ], [
            $this->validPng('batch-three.png'),
        ]);
        $this->assertSame(302, $eleventh->status());

        $card = app()->handle(Request::create('GET', '/requests/' . $requestId));
        $this->assertContains('К одной заявке можно прикрепить не более 10 изображений.', $card->body());

        $count->execute(['request_id' => $requestId]);
        $this->assertSame(10, (int) $count->fetchColumn());
    }

    public function testFakeImageMimeIsRejectedBeforeARequestIsPersisted(): void
    {
        $path = $this->temporaryFile('not-an-image.txt', 'plain text pretending to be a photo');
        $upload = UploadedFile::forTesting($path, 'fake.png');

        $this->loginAs(self::CUSTOMER);
        $response = $this->postWithFiles('/requests/create', $this->requestData(), [$upload]);
        $this->assertSame(302, $response->status());

        $page = app()->handle(Request::create('GET', '/requests/create'));
        $this->assertSame(200, $page->status());
        $this->assertContains('Поддерживаются только изображения JPEG, PNG и WebP.', $page->body());

        $count = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM repair_requests WHERE problem_description = :problem"
        );
        $count->execute(['problem' => $this->requestData()['problem_description']]);
        $this->assertSame(0, (int) $count->fetchColumn());
    }

    public function testSvgAndOversizedFilesAreRejected(): void
    {
        $this->loginAs(self::CUSTOMER);

        $svg = $this->temporaryFile(
            'vector.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="10" height="10"/></svg>'
        );
        $svgResponse = $this->postWithFiles('/requests/create', $this->requestData(), [
            UploadedFile::forTesting($svg, 'vector.svg'),
        ]);
        $this->assertSame(302, $svgResponse->status());
        $svgPage = app()->handle(Request::create('GET', '/requests/create'));
        $this->assertContains('Поддерживаются только изображения JPEG, PNG и WebP.', $svgPage->body());

        $oversized = $this->temporaryFile('oversized.png', str_repeat('x', (8 * 1024 * 1024) + 1));
        $sizeResponse = $this->postWithFiles('/requests/create', $this->requestData(), [
            UploadedFile::forTesting($oversized, 'oversized.png'),
        ]);
        $this->assertSame(302, $sizeResponse->status());
        $sizePage = app()->handle(Request::create('GET', '/requests/create'));
        $this->assertContains('Размер каждого изображения не должен превышать 8 МБ.', $sizePage->body());

        $count = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM repair_requests WHERE problem_description = :problem"
        );
        $count->execute(['problem' => $this->requestData()['problem_description']]);
        $this->assertSame(0, (int) $count->fetchColumn());
    }

    public function testRequestCannotExceedFiveImagesInOneSubmission(): void
    {
        $uploads = [];
        for ($index = 0; $index < 6; $index++) {
            $uploads[] = $this->validPng('photo-' . $index . '.png');
        }

        $this->loginAs(self::CUSTOMER);
        $response = $this->postWithFiles('/requests/create', $this->requestData(), $uploads);
        $this->assertSame(302, $response->status());

        $page = app()->handle(Request::create('GET', '/requests/create'));
        $this->assertContains('За один раз можно прикрепить не более 5 изображений.', $page->body());

        $count = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM repair_requests WHERE problem_description = :problem"
        );
        $count->execute(['problem' => $this->requestData()['problem_description']]);
        $this->assertSame(0, (int) $count->fetchColumn());
    }

    /** @return array<string, string> */
    private function requestData(): array
    {
        return [
            'device_type' => 'Ноутбук',
            'device_brand' => 'TASK008',
            'device_model' => 'Fixture',
            'service_id' => '',
            'problem_description' => 'TASK008 request intake image upload fixture.',
            'customer_contact' => 'TASK008-contact',
        ];
    }

    private function seedRequest(): int
    {
        $number = 'TASK008-' . strtoupper(bin2hex(random_bytes(4)));
        $statement = $this->pdo()->prepare(
            'INSERT INTO repair_requests
                (public_number, customer_id, technician_id, status, priority, device_type,
                 device_brand, device_model, problem_description, customer_contact)
             VALUES (:number, :customer_id, :technician_id, \'repairing\', \'normal\', \'Ноутбук\',
                 \'TASK008-Brand\', \'TASK008-Model\', \'TASK008 chat attachment fixture.\', \'TASK008-contact\')'
        );
        $statement->execute([
            'number' => $number,
            'customer_id' => $this->userId(self::CUSTOMER),
            'technician_id' => $this->userId(self::TECHNICIAN),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function validPng(string $name): UploadedFile
    {
        $path = $this->temporaryFile($name, '');
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data))
                . $type
                . $data
                . pack('N', crc32($type . $data));
        };
        $header = pack('N', 1) . pack('N', 1) . chr(8) . chr(6) . chr(0) . chr(0) . chr(0);
        $pixel = gzcompress("\x00\xFF\x00\x00\xFF");
        $png = "\x89PNG\r\n\x1A\n"
            . $chunk('IHDR', $header)
            . $chunk('IDAT', $pixel)
            . $chunk('IEND', '');

        file_put_contents($path, $png);

        return UploadedFile::forTesting($path, $name);
    }

    private function temporaryFile(string $name, string $contents): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixpoint-task008-' . bin2hex(random_bytes(8)) . '-' . basename($name);
        file_put_contents($path, $contents);
        $this->temporaryPaths[] = $path;

        return $path;
    }

    /** @param array<string, string> $data
     *  @param list<UploadedFile> $uploads
     */
    private function postWithFiles(string $uri, array $data, array $uploads): Response
    {
        $data['_token'] = app()->csrf()->token();

        return app()->handle(Request::create(
            'POST',
            $uri,
            $data,
            [],
            [],
            [],
            ['attachments' => $uploads]
        ));
    }

    /** @param array<string, string> $data */
    private function post(string $uri, array $data): Response
    {
        $data['_token'] = app()->csrf()->token();

        return app()->handle(Request::create('POST', $uri, $data));
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
