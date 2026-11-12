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

        $this->assertSame(10, $repository->countAllActive());
        $this->assertSame(10, $repository->countActive());
        $this->assertSame(5, $repository->countActive('', 'computers-laptops'));
        $this->assertGreaterThan(0, $repository->countActive('экран'));

        $page = $repository->paginateActive('', 'computers-laptops', 10, 0);
        $this->assertCount(5, $page);
        foreach ($page as $row) {
            $this->assertSame('computers-laptops', $row['category_slug']);
        }

        $service = $repository->findActiveBySlug('laptop-cleaning');
        $this->assertNotNull($service);
        $this->assertSame('Чистка ноутбука от пыли', $service['name']);
        $this->assertNull($repository->findActiveBySlug('missing-service'));
    }

    public function testCountAllActiveExcludesServicesInDisabledCategories(): void
    {
        $pdo = app()->database()->connection();
        $repository = new ServiceRepository(app()->database());

        $category = $pdo->query(
            "SELECT id FROM service_categories WHERE slug = 'computers-laptops' LIMIT 1"
        )->fetch();
        $this->assertNotSame(false, $category);
        $categoryId = (int) $category['id'];

        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM services WHERE category_id = :category_id AND is_active = 1'
        );
        $count->execute(['category_id' => $categoryId]);
        $categoryServices = (int) $count->fetchColumn();
        $this->assertGreaterThan(0, $categoryServices);

        $before = $repository->countAllActive();
        $disable = $pdo->prepare(
            'UPDATE service_categories SET is_active = 0 WHERE id = :id'
        );
        $enable = $pdo->prepare(
            'UPDATE service_categories SET is_active = 1 WHERE id = :id'
        );

        $disable->execute(['id' => $categoryId]);
        try {
            $this->assertSame($before - $categoryServices, $repository->countAllActive());
        } finally {
            $enable->execute(['id' => $categoryId]);
        }
    }

    public function testCategoryRepositoryReturnsActiveCategoriesInOrder(): void
    {
        $categories = (new ServiceCategoryRepository(app()->database()))->allActive();

        $this->assertCount(5, $categories);
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
        $this->assertSame(10, $result['pagination']['total']);
        $this->assertSame(1, $result['pagination']['current_page']);
        $this->assertSame(10, $result['pagination']['per_page']);
        $this->assertCount(10, $result['items']);
        $this->assertSame('laptop-screen-replacement', $result['items'][0]['slug']);

        $detail = $catalog->findBySlug('laptop-cleaning');
        $this->assertNotNull($detail);
        $this->assertSame('computers-laptops', $detail['category']['slug']);
        $this->assertSame('Компьютеры и ноутбуки', $detail['category_name']);
        $this->assertSame(7000.0, $detail['price_from']);
        $this->assertArrayHasKey('description', $detail);

        $filtered = $catalog->search('', 'diagnostics', 1);
        $this->assertSame(2, $filtered['pagination']['total']);
        $this->assertSame('diagnostics', $filtered['filters']['category']);
    }
}
