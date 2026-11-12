<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\Config;
use FixPoint\Repositories\ServiceCategoryRepository;
use FixPoint\Repositories\ServiceRepository;

/**
 * Public catalogue read model. Shapes follow docs/10-INTEGRATION-CONTRACT.md:
 * lists contain items + pagination + filters, and one view maps to one service.
 */
final class CatalogService
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceCategoryRepository $categories,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   pagination: array{current_page: int, last_page: int, per_page: int, total: int},
     *   filters: array{q: string, category: string}
     * }
     */
    public function search(string $query, string $categorySlug, int $page): array
    {
        $query = trim($query);
        $categorySlug = trim($categorySlug);
        $perPage = (int) $this->config->get('catalog.per_page');
        $total = $this->services->countActive($query, $categorySlug);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $lastPage);
        $offset = ($currentPage - 1) * $perPage;

        $items = $total === 0
            ? []
            : array_map(
                fn (array $row): array => $this->mapService($row),
                $this->services->paginateActive($query, $categorySlug, $perPage, $offset)
            );

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'filters' => [
                'q' => $query,
                'category' => $categorySlug,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->services->findActiveBySlug($slug);

        return $row === null ? null : $this->mapService($row, true);
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'description' => $row['description'] === null ? null : (string) $row['description'],
            'sort_order' => (int) $row['sort_order'],
        ], $this->categories->allActive());
    }

    /**
     * @return array{
     *   categories_count: int,
     *   services_count: int,
     *   popular_services: list<array<string, mixed>>
     * }
     */
    public function overview(int $popularLimit = 6): array
    {
        return [
            'categories_count' => $this->categories->countActive(),
            'services_count' => $this->services->countAllActive(),
            'popular_services' => array_map(
                fn (array $row): array => $this->mapService($row),
                $this->services->popularActive($popularLimit)
            ),
        ];
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed> */
    private function mapService(array $row, bool $withDescription = false): array
    {
        $service = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'short_description' => $row['short_description'] === null ? null : (string) $row['short_description'],
            'price_from' => $row['price_from'] === null ? null : (float) $row['price_from'],
            'estimated_minutes' => $row['estimated_minutes'] === null ? null : (int) $row['estimated_minutes'],
            'category' => [
                'id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
                'name' => (string) $row['category_name'],
                'slug' => (string) $row['category_slug'],
            ],
        ];

        if ($withDescription) {
            $service['description'] = $row['description'] === null ? null : (string) $row['description'];
        }

        return $service;
    }
}
