<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

final class RepairGuideHttpTest extends IntegrationTestCase
{
    private const ADMIN = 'admin@fixpoint.test';
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';

    public function testPublicKnowledgeBaseSearchAndArticleRendering(): void
    {
        $index = app()->handle(Request::create('GET', '/guides?q=ноутбук'));
        $this->assertSame(200, $index->status());
        $this->assertContains('Частые неисправности', $index->body());
        $this->assertContains('Ноутбук не включается', $index->body());

        $article = app()->handle(Request::create('GET', '/guides/laptop-wont-start'));
        $this->assertSame(200, $article->status());
        $this->assertContains('Что можно проверить самостоятельно', $article->body());
        $this->assertContains('Не разбирайте корпус', $article->body());
        $this->assertContains('Создать заявку по этой проблеме', $article->body());
    }

    public function testGuestsAndNonAdminsCannotManageGuides(): void
    {
        $this->assertSame(200, app()->handle(Request::create('GET', '/guides'))->status());
        $guestAdmin = app()->handle(Request::create('GET', '/admin/guides'));
        $this->assertSame(302, $guestAdmin->status());
        $this->assertContains('/login', (string) $guestAdmin->header('Location'));

        $this->loginAs(self::CUSTOMER);
        $this->assertSame(403, app()->handle(Request::create('GET', '/admin/guides'))->status());
        $this->assertSame(403, app()->handle(Request::create('GET', '/admin/guides/create'))->status());
        $this->logout();

        $this->loginAs(self::TECHNICIAN);
        $this->assertSame(403, app()->handle(Request::create('GET', '/admin/guides'))->status());
    }

    public function testAdminCanCreateDraftPublishAndArchiveGuide(): void
    {
        $this->loginAs(self::ADMIN);
        $slug = 'module-five-test-guide-' . bin2hex(random_bytes(4));
        $input = $this->validInput($slug);

        try {
            $response = $this->post('/admin/guides', $input);
            $this->assertSame(302, $response->status());
            $row = $this->guideBySlug($slug);
            $this->assertSame('draft', $row['status']);
            $this->assertSame(404, app()->handle(Request::create('GET', '/guides/' . $slug))->status());

            $input['status'] = 'published';
            $response = $this->post('/admin/guides/' . (int) $row['id'] . '/edit', $input);
            $this->assertSame(302, $response->status());
            $public = app()->handle(Request::create('GET', '/guides/' . $slug));
            $this->assertSame(200, $public->status());
            $this->assertContains('Тестовая статья модуля пять', $public->body());
            $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $public->body());
            $this->assertNotContains('<script>alert(1)</script>', $public->body());

            $archive = $this->post('/admin/guides/' . (int) $row['id'] . '/archive', []);
            $this->assertSame(302, $archive->status());
            $this->assertSame(404, app()->handle(Request::create('GET', '/guides/' . $slug))->status());
        } finally {
            $this->pdo()->prepare('DELETE FROM repair_guides WHERE slug = :slug')->execute(['slug' => $slug]);
        }
    }

    public function testInvalidAndDuplicateSlugsAreRejected(): void
    {
        $this->loginAs(self::ADMIN);
        $slug = 'module-five-duplicate-' . bin2hex(random_bytes(4));
        $first = $this->validInput($slug);
        $this->post('/admin/guides', $first);

        try {
            $this->assertSame(1, $this->countBySlug($slug));
            $second = $this->validInput($slug);
            $this->post('/admin/guides', $second);
            $this->assertSame(1, $this->countBySlug($slug));

            $invalid = $this->validInput('invalid slug with spaces');
            $this->post('/admin/guides', $invalid);
            $this->assertSame(0, $this->countBySlug('invalid slug with spaces'));
        } finally {
            $this->pdo()->prepare('DELETE FROM repair_guides WHERE slug = :slug')->execute(['slug' => $slug]);
        }
    }

    /** @return array<string, string> */
    private function validInput(string $slug): array
    {
        return [
            'title' => 'Тестовая статья модуля пять',
            'slug' => $slug,
            'category_slug' => 'laptops-pc',
            'device_type' => 'Ноутбук',
            'summary' => 'Краткое описание тестовой проблемы для проверки публикации.',
            'symptoms' => 'Устройство ведёт себя необычно и не выполняет ожидаемое действие.',
            'possible_causes' => 'Возможны настройки, внешнее подключение или аппаратная неисправность.',
            'steps' => "Проверьте внешнее подключение и состояние кабеля.\nПерезагрузите устройство обычным способом.\nЗапишите точный текст сообщения об ошибке.",
            'do_not' => 'Не вскрывайте корпус и не используйте неизвестные программы для ремонта.',
            'when_to_contact' => 'Обратитесь к мастеру, если безопасные проверки не устранили проблему.',
            'status' => 'draft',
        ];
    }

    /** @return array<string, mixed> */
    private function guideBySlug(string $slug): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM repair_guides WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();
        $this->assertTrue(is_array($row), 'Guide must exist after admin creation.');
        return $row;
    }

    private function countBySlug(string $slug): int
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM repair_guides WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        return (int) $statement->fetchColumn();
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
