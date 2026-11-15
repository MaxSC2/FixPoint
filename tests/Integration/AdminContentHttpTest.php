<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

/** End-to-end tests for admin-managed About page content and safe public rendering. */
final class AdminContentHttpTest extends IntegrationTestCase
{
    private const ADMIN = 'admin@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';

    public function testGuestsAndNonAdminsCannotManagePublicContent(): void
    {
        $guest = app()->handle(Request::create('GET', '/admin/content/about'));
        $this->assertSame(302, $guest->status());
        $this->assertContains('/login', (string) $guest->header('Location'));

        $this->loginAs(self::CUSTOMER);
        $this->assertSame(403, app()->handle(Request::create('GET', '/admin/content/about'))->status());
        $this->assertSame(403, $this->post('/admin/content/about', $this->validInput())->status());
        $this->logout();

        $this->loginAs(self::TECHNICIAN);
        $this->assertSame(403, app()->handle(Request::create('GET', '/admin/content/about'))->status());
        $this->assertSame(403, $this->post('/admin/content/about', $this->validInput())->status());
        $this->logout();
    }

    public function testAdminCanEditAboutContentAndPublicPageEscapesIt(): void
    {
        $before = $this->aboutRow();
        $this->loginAs(self::ADMIN);

        try {
            $editor = app()->handle(Request::create('GET', '/admin/content/about'));
            $this->assertSame(200, $editor->status());
            $this->assertContains('Страница «О сервисе»', $editor->body());
            $this->assertContains('name="title"', $editor->body());

            $input = [
                'title' => 'TASK-M4: Новый заголовок',
                'lead' => 'TASK-M4: краткое описание после обновления.',
                'section_heading' => 'TASK-M4: Новый раздел',
                'section_body' => "Первый абзац TASK-M4.\n\nТекст <script>alert(1)</script> остаётся обычным текстом.",
            ];
            $response = $this->post('/admin/content/about', $input);
            $this->assertSame(302, $response->status());
            $this->assertSame('/admin/content/about', $response->header('Location'));

            $stored = $this->aboutRow();
            $this->assertSame($input['title'], $stored['title']);
            $this->assertSame($input['lead'], $stored['lead']);
            $this->assertSame($input['section_heading'], $stored['section_heading']);
            $this->assertSame($input['section_body'], $stored['section_body']);
            $this->assertSame($this->userId(self::ADMIN), (int) $stored['updated_by']);

            $public = app()->handle(Request::create('GET', '/about'));
            $this->assertSame(200, $public->status());
            $this->assertContains('TASK-M4: Новый заголовок', $public->body());
            $this->assertContains('TASK-M4: Новый раздел', $public->body());
            $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $public->body());
            $this->assertNotContains('<script>alert(1)</script>', $public->body());
        } finally {
            $this->restoreAbout($before);
        }
    }

    public function testInvalidContentAndMissingCsrfDoNotChangeTheDatabase(): void
    {
        $this->loginAs(self::ADMIN);
        $before = $this->aboutRow();

        $invalid = $this->post('/admin/content/about', [
            'title' => 'x',
            'lead' => 'short',
            'section_heading' => '',
            'section_body' => 'too short',
        ]);
        $this->assertSame(302, $invalid->status());
        $this->assertSame('/admin/content/about', $invalid->header('Location'));
        $this->assertSame($before['title'], $this->aboutRow()['title']);

        $noToken = app()->handle(Request::create('POST', '/admin/content/about', $this->validInput()));
        $this->assertSame(419, $noToken->status());
        $this->assertSame($before['title'], $this->aboutRow()['title']);
    }

    /** @return array<string, string> */
    private function validInput(): array
    {
        return [
            'title' => 'Заголовок для проверки блока About',
            'lead' => 'Краткий текст для проверки публичной страницы.',
            'section_heading' => 'Описание процесса ремонта',
            'section_body' => 'Достаточно длинный текст для проверки сохранения контента.',
        ];
    }

    /** @return array<string, mixed> */
    private function aboutRow(): array
    {
        $row = $this->pdo()->query(
            "SELECT block_key, title, lead, section_heading, section_body, updated_by
             FROM site_content_blocks WHERE block_key = 'about' LIMIT 1"
        )->fetch();

        $this->assertTrue(is_array($row), 'The default About content block must exist.');
        return $row;
    }

    /** @param array<string, mixed> $row */
    private function restoreAbout(array $row): void
    {
        $this->pdo()->prepare(
            'UPDATE site_content_blocks
             SET title = :title, lead = :lead, section_heading = :section_heading,
                 section_body = :section_body, updated_by = :updated_by
             WHERE block_key = :block_key'
        )->execute([
            'title' => $row['title'],
            'lead' => $row['lead'],
            'section_heading' => $row['section_heading'],
            'section_body' => $row['section_body'],
            'updated_by' => $row['updated_by'],
            'block_key' => $row['block_key'],
        ]);
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

    private function userId(string $email): int
    {
        $statement = $this->pdo()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        return (int) $statement->fetchColumn();
    }

    private function pdo(): \PDO
    {
        return app()->database()->connection();
    }
}
