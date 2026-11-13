<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Env;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Tests\IntegrationTestCase;

/**
 * End-to-end HTTP tests for the administrator catalogue workspace: services
 * and categories CRUD, validation, slug uniqueness, admin-only access, CSRF
 * and public visibility. Public queries require both the service and its
 * category to be active, so deactivation is verified to hide without deleting.
 * Fixtures use the TASK005C- prefix in both slugs and are removed in tearDown.
 */
final class AdminCatalogHttpTest extends IntegrationTestCase
{
    private const SLUG_PREFIX = 'task005c-';
    private const ADMIN = 'admin@fixpoint.test';
    private const TECHNICIAN = 'master.ivanov@fixpoint.test';
    private const CUSTOMER = 'customer.kovalev@fixpoint.test';

    protected function tearDown(): void
    {
        $pdo = $this->pdo();

        $pdo->prepare('DELETE FROM services WHERE slug LIKE :prefix')
            ->execute(['prefix' => self::SLUG_PREFIX . '%']);
        $pdo->prepare('DELETE FROM service_categories WHERE slug LIKE :prefix')
            ->execute(['prefix' => self::SLUG_PREFIX . '%']);

        parent::tearDown();
    }

    public function testGuestIsRedirectedFromCatalogRoutes(): void
    {
        $category = $this->createCategory();
        $service = $this->createService((int) $category['id']);

        foreach (['/admin/services', '/admin/categories'] as $uri) {
            $response = app()->handle(Request::create('GET', $uri));
            $this->assertSame(302, $response->status(), 'Guest must be redirected from ' . $uri);
            $this->assertContains('/login', (string) $response->header('Location'));
            $this->assertContains('return_to=' . rawurlencode($uri), (string) $response->header('Location'));
        }

        $edit = app()->handle(Request::create('GET', '/admin/services/' . $service['id'] . '/edit'));
        $this->assertSame(302, $edit->status());
        $this->assertContains('/login', (string) $edit->header('Location'));

        $beforeServices = $this->countServices();
        $beforeCategories = $this->countCategories();

        $createService = app()->handle(Request::create('POST', '/admin/services', [
            'category_id' => (string) $category['id'],
            'name' => 'TASK005C Guest Service',
            'slug' => $this->slug('guest-service'),
        ]));
        $this->assertSame(302, $createService->status());
        $this->assertContains('/login', (string) $createService->header('Location'));

        $createCategory = app()->handle(Request::create('POST', '/admin/categories', [
            'name' => 'TASK005C Guest Category',
            'slug' => $this->slug('guest-category'),
        ]));
        $this->assertSame(302, $createCategory->status());
        $this->assertContains('/login', (string) $createCategory->header('Location'));

        $this->assertSame($beforeServices, $this->countServices());
        $this->assertSame($beforeCategories, $this->countCategories());
    }

    public function testCustomerAndTechnicianCannotManageCatalogue(): void
    {
        $category = $this->createCategory();
        $service = $this->createService((int) $category['id']);

        foreach ([self::CUSTOMER, self::TECHNICIAN] as $email) {
            $this->loginAs($email);

            $this->assertSame(403, app()->handle(Request::create('GET', '/admin/services'))->status());
            $this->assertSame(403, app()->handle(Request::create('GET', '/admin/categories'))->status());
            $this->assertSame(
                403,
                app()->handle(Request::create('GET', '/admin/services/' . $service['id'] . '/edit'))->status()
            );
            $this->assertSame(
                403,
                app()->handle(Request::create('GET', '/admin/categories/' . $category['id'] . '/edit'))->status()
            );

            $createService = $this->post('/admin/services', [
                'category_id' => (string) $category['id'],
                'name' => 'TASK005C Forbidden Service',
                'slug' => $this->slug('forbidden-service'),
            ]);
            $this->assertSame(403, $createService->status());
            $this->assertSame(0, $this->serviceCountBySlug($this->slug('forbidden-service')));

            $createCategory = $this->post('/admin/categories', [
                'name' => 'TASK005C Forbidden Category',
                'slug' => $this->slug('forbidden-category'),
            ]);
            $this->assertSame(403, $createCategory->status());
            $this->assertSame(0, $this->categoryCountBySlug($this->slug('forbidden-category')));

            $updateService = $this->post('/admin/services/' . $service['id'] . '/edit', [
                'category_id' => (string) $category['id'],
                'name' => 'TASK005C Forbidden Edit',
                'slug' => $service['slug'],
                'is_active' => '1',
            ]);
            $this->assertSame(403, $updateService->status());
            $this->assertSame('TASK005C Fixture Service', $this->serviceRow((int) $service['id'])['name']);

            $updateCategory = $this->post('/admin/categories/' . $category['id'] . '/edit', [
                'name' => 'TASK005C Forbidden Category Edit',
                'slug' => $category['slug'],
                'is_active' => '1',
            ]);
            $this->assertSame(403, $updateCategory->status());
            $this->assertSame('TASK005C Fixture Category', $this->categoryRow((int) $category['id'])['name']);

            $this->logout();
        }
    }

    public function testServiceIndexListsCatalogueAndCreateForm(): void
    {
        $inactiveCategory = $this->createCategory([
            'name' => 'TASK005C Inactive Category',
            'is_active' => 0,
        ]);
        $inactiveService = $this->createService((int) $inactiveCategory['id'], [
            'name' => 'TASK005C Inactive Category Service',
        ]);
        $this->loginAs(self::ADMIN);

        $response = app()->handle(Request::create('GET', '/admin/services'));
        $body = $response->body();
        $this->assertSame(200, $response->status());
        $this->assertContains('Публикация сервисных работ', $body);
        $this->assertContains('Чистка ноутбука от пыли', $body);
        $this->assertContains('TASK005C Inactive Category Service', $body);
        $this->assertContains('TASK005C Inactive Category', $body);
        $this->assertContains('action="/admin/services"', $body);
        $this->assertContains('name="_token"', $body);
        $this->assertContains('name="short_description"', $body);
        $this->assertContains('name="description"', $body);
        $this->assertContains('name="is_active"', $body);
        $this->assertContains(
            '/admin/services/' . $inactiveService['id'] . '/edit',
            $body
        );
        $this->assertNotContains('display_errors', $body);
    }

    public function testCategoryIndexListsAllCategoriesIncludingInactive(): void
    {
        $active = $this->createCategory(['name' => 'TASK005C Active Category']);
        $inactive = $this->createCategory([
            'name' => 'TASK005C Hidden Category',
            'is_active' => 0,
        ]);
        $this->loginAs(self::ADMIN);

        $response = app()->handle(Request::create('GET', '/admin/categories'));
        $body = $response->body();
        $this->assertSame(200, $response->status());
        $this->assertContains('Структура каталога', $body);
        $this->assertContains('TASK005C Active Category', $body);
        $this->assertContains('TASK005C Hidden Category', $body);
        $this->assertContains('Отключена', $body);
        $this->assertContains('action="/admin/categories"', $body);
        $this->assertContains('name="_token"', $body);
        $this->assertContains('name="sort_order"', $body);
        $this->assertContains('name="is_active"', $body);
        $this->assertContains('/admin/categories/' . $active['id'] . '/edit', $body);
        $this->assertContains('/admin/categories/' . $inactive['id'] . '/edit', $body);
    }

    public function testCreateServiceStoresNormalisedValues(): void
    {
        $category = $this->createCategory();
        $slug = $this->slug('created-service');
        $this->loginAs(self::ADMIN);

        $response = $this->post('/admin/services', [
            'category_id' => (string) $category['id'],
            'name' => 'TASK005C Created Service',
            'slug' => $slug,
            'short_description' => 'TASK005C short description',
            'description' => 'TASK005C full description for the created service.',
            'price_from' => '123.4',
            'estimated_minutes' => '45',
            'is_active' => '1',
        ]);
        $this->assertSame(302, $response->status());
        $this->assertSame('/admin/services', $response->header('Location'));

        $row = $this->serviceRowBySlug($slug);
        $this->assertNotNull($row);
        $this->assertSame((int) $category['id'], (int) $row['category_id']);
        $this->assertSame('TASK005C Created Service', $row['name']);
        $this->assertSame('TASK005C short description', $row['short_description']);
        $this->assertSame('TASK005C full description for the created service.', $row['description']);
        $this->assertSame('123.40', (string) $row['price_from']);
        $this->assertSame(45, (int) $row['estimated_minutes']);
        $this->assertSame(1, (int) $row['is_active']);

        $index = app()->handle(Request::create('GET', '/admin/services'))->body();
        $this->assertContains('Услуга создана.', $index);
        $this->assertContains('TASK005C Created Service', $index);

        $public = app()->handle(Request::create('GET', '/services/' . $slug));
        $this->assertSame(200, $public->status());
        $this->assertContains('TASK005C Created Service', $public->body());

        $hiddenSlug = $this->slug('hidden-service');
        $hidden = $this->post('/admin/services', [
            'category_id' => (string) $category['id'],
            'name' => 'TASK005C Hidden Service',
            'slug' => $hiddenSlug,
            'price_from' => '',
            'estimated_minutes' => '',
            'is_active' => '0',
        ]);
        $this->assertSame(302, $hidden->status());
        $hiddenRow = $this->serviceRowBySlug($hiddenSlug);
        $this->assertNotNull($hiddenRow);
        $this->assertNull($hiddenRow['price_from']);
        $this->assertNull($hiddenRow['estimated_minutes']);
        $this->assertSame(0, (int) $hiddenRow['is_active']);
        $this->assertSame(404, app()->handle(Request::create('GET', '/services/' . $hiddenSlug))->status());
    }

    public function testServiceValidationRejectsInvalidInputAndKeepsOldValues(): void
    {
        $category = $this->createCategory();
        $this->loginAs(self::ADMIN);
        $before = $this->countServices();
        $validSlug = $this->slug('validation-service');

        $this->post('/admin/services', [
            'category_id' => '',
            'name' => 'A',
            'slug' => 'Bad Slug',
            'short_description' => str_repeat('s', 256),
            'description' => str_repeat('d', 10001),
            'price_from' => 'abc',
            'estimated_minutes' => '0',
            'is_active' => 'maybe',
        ]);
        $this->assertSame($before, $this->countServices());

        $body = app()->handle(Request::create('GET', '/admin/services'))->body();
        $this->assertContains('Выберите категорию услуги.', $body);
        $this->assertContains('Укажите название длиной от 2 до 160 символов.', $body);
        $this->assertContains('Slug: только строчные латинские буквы', $body);
        $this->assertContains('Краткое описание не должно превышать 255 символов.', $body);
        $this->assertContains('Описание слишком длинное.', $body);
        $this->assertContains('Укажите неотрицательную цену', $body);
        $this->assertContains('Время должно быть от 1 до 100000 минут.', $body);
        $this->assertContains('Некорректное состояние услуги.', $body);
        $this->assertContains('value="A"', $body);
        $this->assertContains('value="Bad Slug"', $body);
        $this->assertContains('value="abc"', $body);

        $unknownCategory = $this->post('/admin/services', [
            'category_id' => '999999999',
            'name' => 'TASK005C Unknown Category',
            'slug' => $validSlug,
        ]);
        $this->assertSame(302, $unknownCategory->status());
        $this->assertSame(0, $this->serviceCountBySlug($validSlug));
        $this->assertContains(
            'Выбранная категория не существует.',
            app()->handle(Request::create('GET', '/admin/services'))->body()
        );

        $existing = $this->createService((int) $category['id'], [
            'name' => 'TASK005C Existing Slug Service',
            'slug' => $this->slug('existing-service'),
        ]);
        $duplicate = $this->post('/admin/services', [
            'category_id' => (string) $category['id'],
            'name' => 'TASK005C Duplicate Slug',
            'slug' => $existing['slug'],
        ]);
        $this->assertSame(302, $duplicate->status());
        $this->assertSame(1, $this->serviceCountBySlug((string) $existing['slug']));
        $this->assertContains(
            'Услуга с таким slug уже существует.',
            app()->handle(Request::create('GET', '/admin/services'))->body()
        );
    }

    public function testEditServiceUpdatesRecordAndValidatesOwnSlug(): void
    {
        $category = $this->createCategory();
        $otherCategory = $this->createCategory(['name' => 'TASK005C Other Category']);
        $service = $this->createService((int) $category['id'], [
            'name' => 'TASK005C Editable Service',
            'slug' => $this->slug('editable-service'),
            'price_from' => '50.00',
            'estimated_minutes' => 30,
        ]);
        $other = $this->createService((int) $category['id'], [
            'name' => 'TASK005C Other Service',
            'slug' => $this->slug('other-service'),
        ]);
        $this->loginAs(self::ADMIN);

        $form = app()->handle(Request::create('GET', '/admin/services/' . $service['id'] . '/edit'));
        $body = $form->body();
        $this->assertSame(200, $form->status());
        $this->assertContains('action="/admin/services/' . $service['id'] . '/edit"', $body);
        $this->assertContains('value="TASK005C Editable Service"', $body);
        $this->assertContains('value="' . $service['slug'] . '"', $body);
        $this->assertContains('TASK005C Other Category', $body);
        $this->assertContains('name="_token"', $body);
        $this->assertContains('name="is_active"', $body);

        $newSlug = $this->slug('updated-service');
        $update = $this->post('/admin/services/' . $service['id'] . '/edit', [
            'category_id' => (string) $otherCategory['id'],
            'name' => 'TASK005C Updated Service',
            'slug' => $newSlug,
            'short_description' => 'TASK005C updated short',
            'description' => 'TASK005C updated description.',
            'price_from' => '',
            'estimated_minutes' => '',
            'is_active' => '0',
        ]);
        $this->assertSame(302, $update->status());
        $this->assertSame('/admin/services', $update->header('Location'));

        $row = $this->serviceRow((int) $service['id']);
        $this->assertSame((int) $otherCategory['id'], (int) $row['category_id']);
        $this->assertSame('TASK005C Updated Service', $row['name']);
        $this->assertSame($newSlug, $row['slug']);
        $this->assertSame('TASK005C updated short', $row['short_description']);
        $this->assertSame('TASK005C updated description.', $row['description']);
        $this->assertNull($row['price_from']);
        $this->assertNull($row['estimated_minutes']);
        $this->assertSame(0, (int) $row['is_active']);
        $this->assertSame(404, app()->handle(Request::create('GET', '/services/' . $newSlug))->status());

        $duplicate = $this->post('/admin/services/' . $service['id'] . '/edit', [
            'category_id' => (string) $otherCategory['id'],
            'name' => 'TASK005C Duplicate Edit',
            'slug' => (string) $other['slug'],
            'is_active' => '0',
        ]);
        $this->assertSame(302, $duplicate->status());
        $this->assertSame(
            '/admin/services/' . $service['id'] . '/edit',
            $duplicate->header('Location')
        );
        $this->assertSame($newSlug, $this->serviceRow((int) $service['id'])['slug']);
        $this->assertContains(
            'Услуга с таким slug уже существует.',
            app()->handle(Request::create('GET', '/admin/services/' . $service['id'] . '/edit'))->body()
        );

        $invalid = $this->post('/admin/services/' . $service['id'] . '/edit', [
            'category_id' => (string) $otherCategory['id'],
            'name' => 'A',
            'slug' => $newSlug,
            'price_from' => '10.999',
            'estimated_minutes' => '100001',
            'is_active' => 'maybe',
        ]);
        $this->assertSame(302, $invalid->status());
        $invalidBody = app()->handle(
            Request::create('GET', '/admin/services/' . $service['id'] . '/edit')
        )->body();
        $this->assertContains('Укажите название длиной от 2 до 160 символов.', $invalidBody);
        $this->assertContains('Укажите неотрицательную цену', $invalidBody);
        $this->assertContains('Время должно быть от 1 до 100000 минут.', $invalidBody);
        $this->assertContains('Некорректное состояние услуги.', $invalidBody);
        $this->assertContains('value="A"', $invalidBody);
        $this->assertSame('TASK005C Updated Service', $this->serviceRow((int) $service['id'])['name']);

        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/services/999999999/edit'))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/services/0/edit'))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/services/not-a-number/edit'))->status());
        $this->assertSame(404, $this->post('/admin/services/999999999/edit', [
            'category_id' => (string) $category['id'],
            'name' => 'TASK005C Unknown Service',
            'slug' => $this->slug('unknown-service'),
        ])->status());
    }

    public function testCreateAndEditCategoryPersistsValues(): void
    {
        $slug = $this->slug('created-category');
        $this->loginAs(self::ADMIN);

        $create = $this->post('/admin/categories', [
            'name' => 'TASK005C Created Category',
            'slug' => $slug,
            'description' => 'TASK005C category description.',
            'sort_order' => '42',
            'is_active' => '1',
        ]);
        $this->assertSame(302, $create->status());
        $this->assertSame('/admin/categories', $create->header('Location'));

        $row = $this->categoryRowBySlug($slug);
        $this->assertNotNull($row);
        $this->assertSame('TASK005C Created Category', $row['name']);
        $this->assertSame('TASK005C category description.', $row['description']);
        $this->assertSame(42, (int) $row['sort_order']);
        $this->assertSame(1, (int) $row['is_active']);

        $index = app()->handle(Request::create('GET', '/admin/categories'))->body();
        $this->assertContains('Категория создана.', $index);
        $this->assertContains('TASK005C Created Category', $index);

        $edit = app()->handle(Request::create('GET', '/admin/categories/' . $row['id'] . '/edit'));
        $this->assertSame(200, $edit->status());
        $this->assertContains('action="/admin/categories/' . $row['id'] . '/edit"', $edit->body());
        $this->assertContains('value="42"', $edit->body());

        $newSlug = $this->slug('updated-category');
        $update = $this->post('/admin/categories/' . $row['id'] . '/edit', [
            'name' => 'TASK005C Updated Category',
            'slug' => $newSlug,
            'description' => '',
            'sort_order' => '7',
            'is_active' => '0',
        ]);
        $this->assertSame(302, $update->status());
        $this->assertSame('/admin/categories', $update->header('Location'));

        $updated = $this->categoryRow((int) $row['id']);
        $this->assertSame('TASK005C Updated Category', $updated['name']);
        $this->assertSame($newSlug, $updated['slug']);
        $this->assertNull($updated['description']);
        $this->assertSame(7, (int) $updated['sort_order']);
        $this->assertSame(0, (int) $updated['is_active']);

        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/categories/999999999/edit'))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/categories/0/edit'))->status());
        $this->assertSame(404, app()->handle(Request::create('GET', '/admin/categories/not-a-number/edit'))->status());
    }

    public function testCategoryValidationRejectsInvalidInputAndDuplicateSlug(): void
    {
        $existing = $this->createCategory([
            'name' => 'TASK005C Existing Category',
            'slug' => $this->slug('existing-category'),
        ]);
        $this->loginAs(self::ADMIN);
        $before = $this->countCategories();

        $this->post('/admin/categories', [
            'name' => 'A',
            'slug' => 'Bad Slug',
            'description' => str_repeat('d', 2001),
            'sort_order' => 'abc',
            'is_active' => 'maybe',
        ]);
        $this->assertSame($before, $this->countCategories());

        $body = app()->handle(Request::create('GET', '/admin/categories'))->body();
        $this->assertContains('Укажите название длиной от 2 до 120 символов.', $body);
        $this->assertContains('Slug: только строчные латинские буквы', $body);
        $this->assertContains('Описание не должно превышать 2000 символов.', $body);
        $this->assertContains('Порядок должен быть целым неотрицательным числом.', $body);
        $this->assertContains('Некорректное состояние категории.', $body);
        $this->assertContains('value="A"', $body);
        $this->assertContains('value="Bad Slug"', $body);

        $duplicate = $this->post('/admin/categories', [
            'name' => 'TASK005C Duplicate Category',
            'slug' => (string) $existing['slug'],
        ]);
        $this->assertSame(302, $duplicate->status());
        $this->assertSame(1, $this->categoryCountBySlug((string) $existing['slug']));
        $this->assertContains(
            'Категория с таким slug уже существует.',
            app()->handle(Request::create('GET', '/admin/categories'))->body()
        );

        $update = $this->post('/admin/categories/' . $existing['id'] . '/edit', [
            'name' => 'TASK005C Duplicate Edit Category',
            'slug' => (string) $existing['slug'],
            'sort_order' => '100001',
            'is_active' => '1',
        ]);
        $this->assertSame(302, $update->status());
        $this->assertSame('TASK005C Existing Category', $this->categoryRow((int) $existing['id'])['name']);
        $editBody = app()->handle(
            Request::create('GET', '/admin/categories/' . $existing['id'] . '/edit')
        )->body();
        $this->assertContains('Порядок не должен превышать 100000.', $editBody);
        $this->assertContains('value="TASK005C Duplicate Edit Category"', $editBody);
    }

    public function testCategoryDeactivationHidesServicesWithoutDeletingThem(): void
    {
        $category = $this->createCategory(['name' => 'TASK005C Visibility Category']);
        $service = $this->createService((int) $category['id'], [
            'name' => 'TASK005C Visibility Service',
            'slug' => $this->slug('visibility-service'),
        ]);
        $this->loginAs(self::ADMIN);

        $publicList = app()->handle(Request::create('GET', '/services?q=' . rawurlencode('TASK005C Visibility')));
        $this->assertSame(200, $publicList->status());
        $this->assertContains('TASK005C Visibility Service', $publicList->body());
        $this->assertSame(
            200,
            app()->handle(Request::create('GET', '/services/' . $service['slug']))->status()
        );

        $deactivate = $this->post('/admin/categories/' . $category['id'] . '/edit', [
            'name' => 'TASK005C Visibility Category',
            'slug' => (string) $category['slug'],
            'description' => '',
            'sort_order' => '900',
            'is_active' => '0',
        ]);
        $this->assertSame(302, $deactivate->status());
        $this->assertSame(0, (int) $this->categoryRow((int) $category['id'])['is_active']);
        $this->assertNotNull($this->serviceRow((int) $service['id']));
        $this->assertSame(1, (int) $this->serviceRow((int) $service['id'])['is_active']);

        $hiddenList = app()->handle(Request::create('GET', '/services?q=' . rawurlencode('TASK005C Visibility')));
        $this->assertNotContains('TASK005C Visibility Service', $hiddenList->body());
        $this->assertSame(
            404,
            app()->handle(Request::create('GET', '/services/' . $service['slug']))->status()
        );
        $adminList = app()->handle(Request::create('GET', '/admin/services'))->body();
        $this->assertContains('TASK005C Visibility Service', $adminList);
        $this->assertContains('TASK005C Visibility Category', $adminList);

        $reactivate = $this->post('/admin/categories/' . $category['id'] . '/edit', [
            'name' => 'TASK005C Visibility Category',
            'slug' => (string) $category['slug'],
            'description' => '',
            'sort_order' => '900',
            'is_active' => '1',
        ]);
        $this->assertSame(302, $reactivate->status());
        $this->assertSame(1, (int) $this->categoryRow((int) $category['id'])['is_active']);
        $visibleAgain = app()->handle(Request::create('GET', '/services?q=' . rawurlencode('TASK005C Visibility')));
        $this->assertContains('TASK005C Visibility Service', $visibleAgain->body());
        $this->assertSame(
            200,
            app()->handle(Request::create('GET', '/services/' . $service['slug']))->status()
        );
    }

    public function testCsrfAndUnknownRecordsAreRejected(): void
    {
        $category = $this->createCategory();
        $service = $this->createService((int) $category['id']);
        $this->loginAs(self::ADMIN);
        $slug = $this->slug('csrf-service');
        $categorySlug = $this->slug('csrf-category');

        $noTokenService = app()->handle(Request::create('POST', '/admin/services', [
            'category_id' => (string) $category['id'],
            'name' => 'TASK005C No Token Service',
            'slug' => $slug,
        ]));
        $this->assertSame(419, $noTokenService->status());
        $this->assertSame(0, $this->serviceCountBySlug($slug));

        $noTokenCategory = app()->handle(Request::create('POST', '/admin/categories', [
            'name' => 'TASK005C No Token Category',
            'slug' => $categorySlug,
        ]));
        $this->assertSame(419, $noTokenCategory->status());
        $this->assertSame(0, $this->categoryCountBySlug($categorySlug));

        $noTokenUpdate = app()->handle(Request::create('POST', '/admin/services/' . $service['id'] . '/edit', [
            'category_id' => (string) $category['id'],
            'name' => 'TASK005C No Token Update',
            'slug' => (string) $service['slug'],
            'is_active' => '1',
        ]));
        $this->assertSame(419, $noTokenUpdate->status());
        $this->assertSame('TASK005C Fixture Service', $this->serviceRow((int) $service['id'])['name']);

        $this->assertSame(404, $this->post('/admin/services/999999999/edit', [
            'category_id' => (string) $category['id'],
            'name' => 'TASK005C Unknown',
            'slug' => $this->slug('unknown'),
        ])->status());
        $this->assertSame(404, $this->post('/admin/categories/999999999/edit', [
            'name' => 'TASK005C Unknown Category',
            'slug' => $this->slug('unknown-category'),
        ])->status());
    }

    /** @param array<string, mixed> $overrides
     *  @return array{id: int, slug: string} */
    private function createCategory(array $overrides = []): array
    {
        $slug = (string) ($overrides['slug'] ?? $this->slug('category'));
        $statement = $this->pdo()->prepare(
            'INSERT INTO service_categories (name, slug, description, sort_order, is_active)
             VALUES (:name, :slug, :description, :sort_order, :is_active)'
        );
        $statement->execute([
            'name' => (string) ($overrides['name'] ?? 'TASK005C Fixture Category'),
            'slug' => $slug,
            'description' => $overrides['description'] ?? 'TASK005C fixture category description.',
            'sort_order' => (int) ($overrides['sort_order'] ?? 900),
            'is_active' => (int) ($overrides['is_active'] ?? 1),
        ]);

        return ['id' => (int) $this->pdo()->lastInsertId(), 'slug' => $slug];
    }

    /** @param array<string, mixed> $overrides
     *  @return array{id: int, slug: string} */
    private function createService(int $categoryId, array $overrides = []): array
    {
        $slug = (string) ($overrides['slug'] ?? $this->slug('service'));
        $statement = $this->pdo()->prepare(
            'INSERT INTO services
                (category_id, name, slug, short_description, description,
                 price_from, estimated_minutes, is_active)
             VALUES
                (:category_id, :name, :slug, :short_description, :description,
                 :price_from, :estimated_minutes, :is_active)'
        );
        $statement->execute([
            'category_id' => $categoryId,
            'name' => (string) ($overrides['name'] ?? 'TASK005C Fixture Service'),
            'slug' => $slug,
            'short_description' => $overrides['short_description'] ?? 'TASK005C fixture short description.',
            'description' => $overrides['description'] ?? 'TASK005C fixture full description.',
            'price_from' => $overrides['price_from'] ?? '99.00',
            'estimated_minutes' => $overrides['estimated_minutes'] ?? 60,
            'is_active' => (int) ($overrides['is_active'] ?? 1),
        ]);

        return ['id' => (int) $this->pdo()->lastInsertId(), 'slug' => $slug];
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

    /** @param array<string, string> $data */
    private function post(string $uri, array $data): Response
    {
        $data['_token'] = app()->csrf()->token();

        return app()->handle(Request::create('POST', $uri, $data));
    }

    private function pdo(): \PDO
    {
        return app()->database()->connection();
    }

    private function slug(string $label): string
    {
        return self::SLUG_PREFIX . $label . '-' . bin2hex(random_bytes(4));
    }

    private function countServices(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM services')->fetchColumn();
    }

    private function countCategories(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM service_categories')->fetchColumn();
    }

    private function serviceCountBySlug(string $slug): int
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM services WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);

        return (int) $statement->fetchColumn();
    }

    private function categoryCountBySlug(string $slug): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM service_categories WHERE slug = :slug'
        );
        $statement->execute(['slug' => $slug]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function serviceRow(int $id): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? [] : $row;
    }

    /** @return array<string, mixed>|null */
    private function serviceRowBySlug(string $slug): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM services WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed> */
    private function categoryRow(int $id): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM service_categories WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? [] : $row;
    }

    /** @return array<string, mixed>|null */
    private function categoryRowBySlug(string $slug): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM service_categories WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
