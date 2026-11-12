<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Request;
use FixPoint\Tests\IntegrationTestCase;

/**
 * End-to-end escaping checks: hostile strings from the database and from the
 * query string must be rendered as text, never as executable markup.
 */
final class EscapingTest extends IntegrationTestCase
{
    private const CATEGORY_SLUG = 'xss-test-category';
    private const SERVICE_SLUG = 'xss-test-service';
    private const PAYLOAD = '<script>alert("xss")</script>';

    protected function tearDown(): void
    {
        $pdo = app()->database()->connection();
        $pdo->prepare('DELETE FROM services WHERE slug = :slug')->execute(['slug' => self::SERVICE_SLUG]);
        $pdo->prepare('DELETE FROM service_categories WHERE slug = :slug')->execute(['slug' => self::CATEGORY_SLUG]);
    }

    public function testServiceNameAndDescriptionAreEscapedInListingAndDetail(): void
    {
        $pdo = app()->database()->connection();
        $pdo->prepare(
            'INSERT INTO service_categories (name, slug, description, sort_order, is_active)
             VALUES (:name, :slug, NULL, 999, 1)'
        )->execute(['name' => 'XSS ' . self::PAYLOAD, 'slug' => self::CATEGORY_SLUG]);

        $categoryId = (int) $pdo->query(
            "SELECT id FROM service_categories WHERE slug = 'xss-test-category'"
        )->fetchColumn();

        $pdo->prepare(
            'INSERT INTO services
                (category_id, name, slug, short_description, description, price_from, estimated_minutes, is_active)
             VALUES (:category_id, :name, :slug, :short, :description, 100.00, 30, 1)'
        )->execute([
            'category_id' => $categoryId,
            'name' => 'Услуга ' . self::PAYLOAD,
            'slug' => self::SERVICE_SLUG,
            'short' => 'Кратко ' . self::PAYLOAD,
            'description' => 'Описание ' . self::PAYLOAD,
        ]);

        $list = app()->handle(Request::create('GET', '/services?q=XSS'));
        $this->assertSame(200, $list->status());
        $this->assertContains('Услуга', $list->body());
        $this->assertContains('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $list->body());
        $this->assertNotContains(self::PAYLOAD, $list->body());

        $detail = app()->handle(Request::create('GET', '/services/' . self::SERVICE_SLUG));
        $this->assertSame(200, $detail->status());
        $this->assertContains('&lt;script&gt;', $detail->body());
        $this->assertNotContains(self::PAYLOAD, $detail->body());
    }

    public function testSearchQueryIsEscapedInTheFilterForm(): void
    {
        $response = app()->handle(Request::create('GET', '/services?q=' . rawurlencode('"><script>alert(1)</script>')));

        $this->assertSame(200, $response->status());
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $response->body());
        $this->assertNotContains('<script>alert(1)</script>', $response->body());
        $this->assertNotContains('"><script>', $response->body());
    }

    public function testInvalidCategoryAndPageAreIgnoredSafely(): void
    {
        $response = app()->handle(Request::create(
            'GET',
            '/services?category=' . rawurlencode("' OR 1=1 --") . '&page=-5'
        ));

        $this->assertSame(200, $response->status());
        $this->assertNotContains('SQLSTATE', $response->body());
        $this->assertNotContains('PDOException', $response->body());
        $this->assertContains('Каталог услуг', $response->body());
    }
}
