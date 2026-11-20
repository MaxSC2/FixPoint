<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

final class TechnicianProfileReviewHttpTest extends IntegrationTestCase
{
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';
    private const OTHER_CUSTOMER = 'customer.ivanova@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';
    private const ADMIN = 'admin@fixpoint.test';
    private const FIXTURE_PREFIX = 'M5PROFILE-';

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        $statement = $pdo->prepare(
            'SELECT id FROM repair_requests WHERE customer_contact LIKE :prefix'
        );
        $statement->execute(['prefix' => self::FIXTURE_PREFIX . '%']);
        $ids = array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));

        if ($ids !== []) {
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $pdo->prepare(
                'DELETE FROM technician_reviews WHERE request_id IN (' . $placeholders . ')'
            )->execute($ids);
            $pdo->prepare(
                'DELETE FROM repair_requests WHERE id IN (' . $placeholders . ')'
            )->execute($ids);
        }

        parent::tearDown();
    }

    public function testPublicDirectoryAndProfileDoNotExposePrivateAccountFields(): void
    {
        $technician = $this->user(self::TECHNICIAN);
        $directory = app()->handle(Request::create('GET', '/masters'));
        $this->assertSame(200, $directory->status());
        $this->assertContains('Мастера', $directory->body());
        $this->assertContains((string) $technician['full_name'], $directory->body());

        $profile = app()->handle(Request::create('GET', '/masters/' . (int) $technician['id']));
        $this->assertSame(200, $profile->status());
        $this->assertContains((string) $technician['full_name'], $profile->body());
        $this->assertContains('Специализация не указана', $profile->body());
        $this->assertNotContains(self::TECHNICIAN, $profile->body());
        $this->assertNotContains('Пароль', $profile->body());
    }

    public function testTechnicianCanEditOwnPublicProfile(): void
    {
        $technician = $this->user(self::TECHNICIAN);
        $technicianId = (int) $technician['id'];
        $originalProfile = $this->profileRow($technicianId);
        $originalSpecialties = $this->specialtyIds($technicianId);
        $categoryId = (int) $this->pdo()->query(
            'SELECT id FROM service_categories WHERE is_active = 1 ORDER BY sort_order, id LIMIT 1'
        )->fetchColumn();

        try {
            $this->loginAs(self::TECHNICIAN);
            $form = app()->handle(Request::create('GET', '/technician/profile'));
            $this->assertSame(200, $form->status());
            $this->assertContains('Мой публичный профиль', $form->body());

            $response = $this->post('/technician/profile', [
                'headline' => 'Диагностика ноутбуков и ПК',
                'bio' => 'Работаю с диагностикой, настройкой и обслуживанием компьютеров.',
                'is_public' => '1',
                'specialties' => [$categoryId],
            ]);
            $this->assertSame(302, $response->status());

            $saved = $this->profileRow($technicianId);
            $this->assertSame('Диагностика ноутбуков и ПК', $saved['headline']);
            $this->assertSame('1', (string) $saved['is_public']);
            $this->assertSame([$categoryId], $this->specialtyIds($technicianId));

            $public = app()->handle(Request::create('GET', '/masters/' . $technicianId));
            $this->assertSame(200, $public->status());
            $this->assertContains('Диагностика ноутбуков и ПК', $public->body());
        } finally {
            $this->restoreProfile($technicianId, $originalProfile, $originalSpecialties);
        }
    }

    public function testReviewRequiresCompletedOwnedRequestAndAllowsOnlyOnePerRequest(): void
    {
        $customer = $this->user(self::CUSTOMER);
        $technician = $this->user(self::TECHNICIAN);
        $otherCustomer = $this->user(self::OTHER_CUSTOMER);
        $completedId = $this->createRequest(
            (int) $customer['id'],
            (int) $technician['id'],
            'completed'
        );
        $pendingId = $this->createRequest(
            (int) $customer['id'],
            (int) $technician['id'],
            'repairing'
        );

        $this->loginAs(self::CUSTOMER);
        $reviewPage = app()->handle(Request::create('GET', '/requests/' . $completedId . '/review'));
        $this->assertSame(200, $reviewPage->status());
        $this->assertContains('Оцените работу мастера', $reviewPage->body());

        $response = $this->post('/requests/' . $completedId . '/review', [
            'rating' => '5',
            'review_text' => 'Работа выполнена аккуратно. <script>alert(1)</script>',
        ]);
        $this->assertSame(302, $response->status());
        $this->assertSame(1, $this->reviewCount($completedId));

        $duplicate = $this->post('/requests/' . $completedId . '/review', [
            'rating' => '4',
            'review_text' => 'Повторный отзыв не должен сохраниться.',
        ]);
        $this->assertSame(302, $duplicate->status());
        $this->assertSame(1, $this->reviewCount($completedId));

        $pending = $this->post('/requests/' . $pendingId . '/review', [
            'rating' => '5',
            'review_text' => 'Слишком ранняя оценка.',
        ]);
        $this->assertSame(302, $pending->status());
        $this->assertSame(0, $this->reviewCount($pendingId));

        $this->logout();
        $this->loginAs(self::OTHER_CUSTOMER);
        $foreign = $this->post('/requests/' . $completedId . '/review', [
            'rating' => '1',
            'review_text' => 'Чужую заявку оценивать нельзя.',
        ]);
        $this->assertSame(404, $foreign->status());
        $this->assertSame(1, $this->reviewCount($completedId));

        $this->logout();
        $profile = app()->handle(Request::create('GET', '/masters/' . (int) $technician['id']));
        $this->assertSame(200, $profile->status());
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $profile->body());
        $this->assertNotContains('<script>alert(1)</script>', $profile->body());
    }

    public function testAdminCanHideReviewAndItLeavesPublicRating(): void
    {
        $customer = $this->user(self::CUSTOMER);
        $technician = $this->user(self::TECHNICIAN);
        $requestId = $this->createRequest(
            (int) $customer['id'],
            (int) $technician['id'],
            'completed'
        );
        $this->loginAs(self::CUSTOMER);
        $this->post('/requests/' . $requestId . '/review', [
            'rating' => '5',
            'review_text' => 'M5PROFILE review moderation fixture.',
        ]);
        $reviewId = $this->reviewId($requestId);
        $this->assertGreaterThan(0, $reviewId);

        $this->logout();
        $this->loginAs(self::ADMIN);
        $list = app()->handle(Request::create('GET', '/admin/reviews'));
        $this->assertSame(200, $list->status());
        $this->assertContains('M5PROFILE review moderation fixture.', $list->body());

        $missingReason = $this->post('/admin/reviews/' . $reviewId . '/moderate', [
            'status' => 'hidden',
            'reason' => '',
        ]);
        $this->assertSame(302, $missingReason->status());
        $this->assertSame('published', $this->reviewStatus($reviewId));

        $moderation = $this->post('/admin/reviews/' . $reviewId . '/moderate', [
            'status' => 'hidden',
            'reason' => 'Тестовая проверка модерации.',
        ]);
        $this->assertSame(302, $moderation->status());
        $this->assertSame('hidden', $this->reviewStatus($reviewId));

        $profile = app()->handle(Request::create('GET', '/masters/' . (int) $technician['id']));
        $this->assertSame(200, $profile->status());
        $this->assertNotContains('M5PROFILE review moderation fixture.', $profile->body());
        $this->assertContains('Пока без оценок', $profile->body());
    }

    public function testGuestsAndWrongRolesCannotUseProfileAndReviewManagementRoutes(): void
    {
        $technician = $this->user(self::TECHNICIAN);
        $requestId = $this->createRequest(
            (int) $this->user(self::CUSTOMER)['id'],
            (int) $technician['id'],
            'completed'
        );

        $guestProfile = app()->handle(Request::create('GET', '/technician/profile'));
        $this->assertSame(302, $guestProfile->status());
        $this->assertContains('/login', (string) $guestProfile->header('Location'));

        $this->loginAs(self::CUSTOMER);
        $this->assertSame(403, app()->handle(Request::create('GET', '/technician/profile'))->status());
        $this->assertSame(403, app()->handle(Request::create('GET', '/admin/reviews'))->status());

        $this->logout();
        $this->loginAs(self::TECHNICIAN);
        $this->assertSame(403, app()->handle(Request::create('GET', '/admin/reviews'))->status());
        $this->assertSame(403, app()->handle(Request::create('GET', '/requests/' . $requestId . '/review'))->status());
    }

    /** @return array<string, mixed> */
    private function user(string $email): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, email, full_name FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();
        $this->assertTrue(is_array($row), 'Seed user must exist: ' . $email);
        return $row;
    }

    private function createRequest(int $customerId, int $technicianId, string $status): int
    {
        $contact = self::FIXTURE_PREFIX . bin2hex(random_bytes(8));
        $number = 'M5-' . strtoupper(bin2hex(random_bytes(6)));
        $statement = $this->pdo()->prepare(
            'INSERT INTO repair_requests
                (public_number, customer_id, technician_id, status, priority,
                 device_type, problem_description, customer_contact)
             VALUES
                (:public_number, :customer_id, :technician_id, :status, "normal",
                 "Ноутбук", "Тестовая заявка для проверки профилей и отзывов.", :customer_contact)'
        );
        $statement->execute([
            'public_number' => $number,
            'customer_id' => $customerId,
            'technician_id' => $technicianId,
            'status' => $status,
            'customer_contact' => $contact,
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    private function reviewCount(int $requestId): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM technician_reviews WHERE request_id = :request_id'
        );
        $statement->execute(['request_id' => $requestId]);
        return (int) $statement->fetchColumn();
    }

    private function reviewId(int $requestId): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT id FROM technician_reviews WHERE request_id = :request_id LIMIT 1'
        );
        $statement->execute(['request_id' => $requestId]);
        return (int) $statement->fetchColumn();
    }

    private function reviewStatus(int $reviewId): string
    {
        $statement = $this->pdo()->prepare(
            'SELECT status FROM technician_reviews WHERE id = :id'
        );
        $statement->execute(['id' => $reviewId]);
        return (string) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    private function profileRow(int $technicianId): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT technician_id, headline, bio, is_public
             FROM technician_profiles WHERE technician_id = :technician_id'
        );
        $statement->execute(['technician_id' => $technicianId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<int> */
    private function specialtyIds(int $technicianId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT category_id FROM technician_profile_specialties
             WHERE technician_id = :technician_id ORDER BY category_id'
        );
        $statement->execute(['technician_id' => $technicianId]);
        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @param array<string, mixed>|null $profile @param list<int> $specialties */
    private function restoreProfile(int $technicianId, ?array $profile, array $specialties): void
    {
        if ($profile === null) {
            $this->pdo()->prepare(
                'DELETE FROM technician_profiles WHERE technician_id = :technician_id'
            )->execute(['technician_id' => $technicianId]);
            return;
        }

        $this->pdo()->prepare(
            'INSERT INTO technician_profiles (technician_id, headline, bio, is_public)
             VALUES (:technician_id, :headline, :bio, :is_public)
             ON DUPLICATE KEY UPDATE headline = VALUES(headline), bio = VALUES(bio), is_public = VALUES(is_public)'
        )->execute([
            'technician_id' => $technicianId,
            'headline' => $profile['headline'],
            'bio' => $profile['bio'],
            'is_public' => $profile['is_public'],
        ]);
        $this->pdo()->prepare(
            'DELETE FROM technician_profile_specialties WHERE technician_id = :technician_id'
        )->execute(['technician_id' => $technicianId]);
        $insert = $this->pdo()->prepare(
            'INSERT INTO technician_profile_specialties (technician_id, category_id)
             VALUES (:technician_id, :category_id)'
        );
        foreach ($specialties as $categoryId) {
            $insert->execute(['technician_id' => $technicianId, 'category_id' => $categoryId]);
        }
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

    /** @param array<string, mixed> $data */
    private function post(string $uri, array $data): Response
    {
        $data['_token'] = app()->csrf()->token();
        return app()->handle(Request::create('POST', $uri, $data));
    }

    private function pdo(): \PDO
    {
        return app()->database()->connection();
    }
}
