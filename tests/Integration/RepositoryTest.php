<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Repositories\ServiceCategoryRepository;
use FixPoint\Repositories\ServiceRepository;
use FixPoint\Repositories\UserRepository;
use FixPoint\Services\CatalogService;
use FixPoint\Tests\IntegrationTestCase;

final class RepositoryTest extends IntegrationTestCase
{
    public function testServiceRepositoryCountsAndFilters(): void
    {
        $repository = new ServiceRepository(app()->database());

        $this->assertSame(100, $repository->countAllActive());
        $this->assertSame(100, $repository->countActive());
        $this->assertSame(22, $repository->countActive('', 'computers-laptops'));
        $this->assertGreaterThan(0, $repository->countActive('экран'));

        $page = $repository->paginateActive('', 'computers-laptops', 10, 0);
        $this->assertCount(10, $page);
        foreach ($page as $row) {
            $this->assertSame('computers-laptops', $row['category_slug']);
        }

        $service = $repository->findActiveBySlug('laptop-cleaning');
        $this->assertNotNull($service);
        $this->assertSame('Чистка ноутбука от пыли', $service['name']);
        $this->assertNull($repository->findActiveBySlug('missing-service'));
    }

    public function testCountAllActiveExcludesServicesInInactiveCategories(): void
    {
        $pdo = app()->database()->connection();
        $repository = new ServiceRepository(app()->database());

        $pdo->exec("UPDATE service_categories SET is_active = 0 WHERE slug = 'diagnostics'");
        try {
            $this->assertSame(88, $repository->countAllActive());
            $this->assertSame(88, $repository->countActive());
        } finally {
            $pdo->exec("UPDATE service_categories SET is_active = 1 WHERE slug = 'diagnostics'");
        }
    }

    public function testCategoryRepositoryReturnsActiveCategoriesInOrder(): void
    {        $categories = (new ServiceCategoryRepository(app()->database()))->allActive();

        $this->assertCount(7, $categories);
        $this->assertSame('computers-laptops', $categories[0]['slug']);
        $this->assertSame('diagnostics', $categories[4]['slug']);
    }

    public function testUserRepositoryFindsActiveAndInactiveUsers(): void
    {
        $repository = new UserRepository(app()->database());

        $admin = $repository->findByEmail('admin@fixpoint.test');
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin['role']);
        $this->assertArrayHasKey('password_hash', $admin);

        $inactive = $repository->findByEmail('inactive@fixpoint.test');
        $this->assertNotNull($inactive);
        $this->assertSame(0, (int) $inactive['is_active']);
        $this->assertNull($repository->findByEmail('nobody@fixpoint.test'));
    }

    public function testCatalogServiceBuildsContractShapes(): void
    {
        $catalog = app()->make(CatalogService::class);
        $result = $catalog->search('', '', 1);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertSame(100, $result['pagination']['total']);
        $this->assertSame(1, $result['pagination']['current_page']);
        $this->assertSame(12, $result['pagination']['per_page']);
        $this->assertCount(12, $result['items']);
        $this->assertTrue(in_array('laptop-screen-replacement', array_column($result['items'], 'slug'), true));

        $detail = $catalog->findBySlug('laptop-cleaning');
        $this->assertNotNull($detail);
        $this->assertSame('computers-laptops', $detail['category']['slug']);
        $this->assertSame(7000.0, $detail['price_from']);
        $this->assertArrayHasKey('description', $detail);

        $filtered = $catalog->search('', 'diagnostics', 1);
        $this->assertSame(12, $filtered['pagination']['total']);
        $this->assertSame('diagnostics', $filtered['filters']['category']);
    }
}
