<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\ViewRenderer;
use FixPoint\Tests\TestCase;

final class UiTemplateContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testAuthenticationViewsUsePostAndCsrf(): void
    {
        $login = $this->read('app/Views/pages/auth/login.php');
        $register = $this->read('app/Views/pages/auth/register.php');

        foreach ([$login, $register] as $view) {
            $this->assertContains('method="post"', $view);
            $this->assertContains('name="_token"', $view);
            $this->assertContains('value="<?= e($csrfToken ?? \'\') ?>"', $view);
            $this->assertContains('data-password-toggle', $view);
        }

        $this->assertContains('action="/login"', $login);
        $this->assertContains('$old' . "['email']", $login);
        $this->assertContains('name="password"', $login);

        $this->assertContains('action="/register"', $register);
        $this->assertContains('$old' . "['full_name']", $register);
        $this->assertContains('name="email"', $register);
        $this->assertContains('name="phone"', $register);
        $this->assertContains('name="password_confirmation"', $register);
    }

    public function testAuthenticationViewsDoNotRetainSecretsInOldInput(): void
    {
        $login = $this->read('app/Views/pages/auth/login.php');
        $register = $this->read('app/Views/pages/auth/register.php');

        $this->assertNotContains('$old' . "['password']", $login);
        $this->assertNotContains('$old' . "['password']", $register);
        $this->assertNotContains('$old' . "['password_confirmation']", $register);
        $this->assertContains('$old' . "['email']", $login);
        $this->assertContains('$old' . "['full_name']", $register);
    }

    public function testPasswordVisibilityControlHasAccessibleClientBehavior(): void
    {
        $javascript = $this->read('public/assets/js/app.js');

        $this->assertContains('querySelectorAll(\'[data-password-toggle]\')', $javascript);
        $this->assertContains('aria-pressed', $javascript);
        $this->assertContains('aria-controls', $javascript);
        $this->assertContains("input.type = shouldShow ? 'text' : 'password';", $javascript);
    }

    public function testHomepageUsesCommittedLocalRepairIllustration(): void
    {
        $home = $this->read('app/Views/pages/public/home.php');
        $assetPath = $this->root . '/public/assets/images/repair-dossier.svg';

        $this->assertTrue(is_file($assetPath), 'The hero illustration must exist in the repository.');
        $this->assertContains('/assets/images/repair-dossier.svg', $home);
        $this->assertContains('alt=""', $home);
        $this->assertContains('Сервисный центр FixPoint', $home);
        $this->assertContains('Популярные услуги', $home);
    }

    public function testMotionHasNoJavascriptAndReducedMotionFallbacks(): void
    {
        $javascript = $this->read('public/assets/js/app.js');
        $stylesheet = $this->read('public/assets/css/app.css');

        $this->assertContains('IntersectionObserver', $javascript);
        $this->assertContains('revealTargets.forEach((element) => element.classList.add(\'is-visible\'))', $javascript);
        $this->assertContains('@media (prefers-reduced-motion: reduce)', $stylesheet);
        $this->assertContains('.js [data-reveal].is-visible', $stylesheet);
    }

    public function testMobileNavigationRulesAreSeparateCssRules(): void
    {
        $stylesheet = $this->read('public/assets/css/app.css');

        $this->assertContains(".no-js .nav-toggle { display: none; }\n    .js .site-nav { display: none; }", $stylesheet);
        $this->assertNotContains(".no-js .nav-toggle { display: none; }\\n    .js .site-nav", $stylesheet);
        $this->assertContains('.js .site-nav.is-open { display: flex; }', $stylesheet);
    }


    public function testCustomerDashboardUsesOnlyProvidedRecentRequests(): void
    {
        $dashboard = $this->read('app/Views/pages/customer/dashboard.php');

        $this->assertContains('$recentRequests', $dashboard);
        $this->assertContains('array_slice(' . '$recentRequests' . ', 0, 4)', $dashboard);
        $this->assertContains('components/request-card.php', $dashboard);
        $this->assertContains('components/empty-state.php', $dashboard);
        $this->assertNotContains('FP-2026-000001', $dashboard);
    }

    public function testCustomerRequestListUsesScopedListAndRealEmptyState(): void
    {
        $list = $this->read('app/Views/pages/customer/requests/index.php');

        $this->assertContains('$requestList[\'items\']', $list);
        $this->assertContains('method="get"', $list);
        $this->assertContains('name="status"', $list);
        $this->assertContains('name="sort"', $list);
        $this->assertContains('components/request-card.php', $list);
        $this->assertContains('components/empty-state.php', $list);
        $this->assertNotContains('FP-2026-000001', $list);
    }

    public function testCustomerRequestCreationUsesPostCsrfAndContractFields(): void
    {
        $create = $this->read('app/Views/pages/customer/requests/create.php');

        $this->assertContains('method="post"', $create);
        $this->assertContains('action="/requests/create"', $create);
        $this->assertContains('name="_token"', $create);
        $this->assertContains('name="device_type"', $create);
        $this->assertContains('name="device_brand"', $create);
        $this->assertContains('name="device_model"', $create);
        $this->assertContains('name="service_id"', $create);
        $this->assertContains('name="problem_description"', $create);
        $this->assertContains('name="customer_contact"', $create);
        $this->assertNotContains('name="password"', $create);
    }

    public function testCustomerRequestDetailUsesPermissionGatedCsrfActions(): void
    {
        $detail = $this->read('app/Views/pages/customer/requests/show.php');

        $this->assertContains('permissions', $detail);
        $this->assertContains('$permissions[\'can_decide_estimate\']', $detail);
        $this->assertContains('$permissions[\'can_comment\']', $detail);
        $this->assertContains('action="/requests/<?= e(rawurlencode($requestId)) ?>/estimate-decision"', $detail);
        $this->assertContains('action="/requests/<?= e(rawurlencode($requestId)) ?>/comments"', $detail);
        $this->assertContains('name="_token"', $detail);
        $this->assertContains('value="approve"', $detail);
        $this->assertContains('value="reject"', $detail);
        $this->assertContains('empty($comment[\'is_internal\'])', $detail);
    }

    public function testReusableRequestCardUsesStatusComponentAndRequestIdentifier(): void
    {
        $card = $this->read('app/Views/components/request-card.php');

        $this->assertContains("['id']", $card);
        $this->assertContains("['public_number']", $card);
        $this->assertContains('status-badge.php', $card);
        $this->assertContains("'/requests/'", $card);
    }


    public function testTechnicianQueueUsesBackendFiltersAndAssignedRequestLinks(): void
    {
        $queue = $this->read('app/Views/pages/technician/requests/index.php');

        $this->assertContains('$requestList', $queue);
        $this->assertContains('name="q"', $queue);
        $this->assertContains('name="status"', $queue);
        $this->assertContains('name="priority"', $queue);
        $this->assertContains('name="sort"', $queue);
        $this->assertContains('/technician/requests/', $queue);
        $this->assertContains('status-badge.php', $queue);
        $this->assertContains('components/empty-state.php', $queue);
    }

    public function testTechnicianActionsUseCsrfAndBackendProvidedPermissions(): void
    {
        $detail = $this->read('app/Views/pages/technician/requests/show.php');

        $this->assertContains('$permissions[\'can_edit_diagnosis\']', $detail);
        $this->assertContains('$permissions[\'can_change_status\']', $detail);
        $this->assertContains('$permissions[\'can_comment\']', $detail);
        $this->assertContains('$allowedTransitions', $detail);
        $this->assertContains('action="/technician/requests/<?= e(rawurlencode($requestId)) ?>/diagnosis"', $detail);
        $this->assertContains('action="/technician/requests/<?= e(rawurlencode($requestId)) ?>/status"', $detail);
        $this->assertContains('name="diagnostic_result"', $detail);
        $this->assertContains('name="estimated_cost"', $detail);
        $this->assertContains('name="estimated_ready_at"', $detail);
        $this->assertContains('name="is_internal"', $detail);
        $this->assertContains('name="_token"', $detail);
    }

    public function testAdminDashboardUsesProvidedDatabaseMetricsAndRecentRequests(): void
    {
        $dashboard = $this->read('app/Views/pages/admin/dashboard.php');

        $this->assertContains('$stats', $dashboard);
        $this->assertContains('$recentRequests', $dashboard);
        $this->assertContains("'total_requests'", $dashboard);
        $this->assertContains("'requests_waiting_approval'", $dashboard);
        $this->assertContains("'active_customers'", $dashboard);
        $this->assertContains("'active_technicians'", $dashboard);
        $this->assertContains('number_format((int) $stats[$key]', $dashboard);
        $this->assertContains('components/empty-state.php', $dashboard);
    }

    public function testAdminRequestRegistryDisplaysRealAssociatedRecords(): void
    {
        $registry = $this->read('app/Views/pages/admin/requests/index.php');

        $this->assertContains('$requestList', $registry);
        $this->assertContains('name="q"', $registry);
        $this->assertContains('name="status"', $registry);
        $this->assertContains('name="priority"', $registry);
        $this->assertContains("['customer']", $registry);
        $this->assertContains("['technician']", $registry);
        $this->assertContains('admin-request-table', $registry);
        $this->assertContains('status-badge.php', $registry);
        $this->assertContains('components/empty-state.php', $registry);
    }



    public function testCustomerTechnicianAndAdminWorkspaceViewsRenderTheirEmptyStates(): void
    {
        $renderer = new ViewRenderer(app()->config());
        $shared = [
            'pageTitle' => 'UI contract test',
            'activeNav' => '',
            'authUser' => ['id' => 7, 'full_name' => 'Тестовый пользователь', 'role' => 'admin'],
            'flash' => null,
            'errors' => [],
            'old' => [],
            'csrfToken' => 'test-csrf-token',
        ];
        $emptyList = [
            'items' => [],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 0],
            'filters' => ['q' => '', 'status' => '', 'priority' => '', 'sort' => 'newest'],
        ];
        $request = [
            'id' => 15,
            'public_number' => 'FP-TEST-000015',
            'status' => 'new',
            'priority' => 'normal',
            'device_type' => 'Ноутбук',
            'device_brand' => 'Test',
            'device_model' => 'Model',
            'problem_description' => 'Тестовое описание неисправности',
            'customer_contact' => '+70000000000',
            'diagnostic_result' => null,
            'estimated_cost' => null,
            'final_cost' => null,
            'estimated_ready_at' => null,
            'created_at' => '2026-11-13T10:00:00+05:00',
            'updated_at' => '2026-11-13T10:00:00+05:00',
            'customer' => ['id' => 7, 'full_name' => 'Тестовый клиент'],
            'technician' => null,
            'service' => null,
        ];

        $views = [
            ['pages/customer/dashboard', ['recentRequests' => []]],
            ['pages/customer/requests/index', ['requestList' => $emptyList]],
            ['pages/customer/requests/create', ['services' => []]],
            ['pages/customer/requests/show', [
                'request' => $request,
                'history' => [],
                'comments' => [],
                'permissions' => [],
            ]],
            ['pages/technician/requests/index', ['requestList' => $emptyList]],
            ['pages/technician/requests/show', [
                'request' => $request,
                'history' => [],
                'comments' => [],
                'permissions' => [],
                'allowedTransitions' => [],
            ]],
            ['pages/admin/dashboard', ['stats' => [], 'recentRequests' => []]],
            ['pages/admin/requests/index', ['requestList' => $emptyList]],
        ];

        foreach ($views as [$viewName, $viewData]) {
            $response = $renderer->renderPage($viewName, array_merge($shared, $viewData));
            $body = $response->body();

            $this->assertSame(200, $response->status(), 'Unexpected status for ' . $viewName);
            $this->assertContains('<!doctype html>', strtolower($body), 'Document shell missing for ' . $viewName);
            $this->assertContains('FixPoint', $body, 'Brand shell missing for ' . $viewName);
        }
    }


    public function testWorkspaceViewsRenderPopulatedRequestModels(): void
    {
        $renderer = new ViewRenderer(app()->config());
        $shared = [
            'pageTitle' => 'UI populated model test',
            'activeNav' => '',
            'authUser' => ['id' => 7, 'full_name' => 'Тестовый администратор', 'role' => 'admin'],
            'flash' => null,
            'errors' => [],
            'old' => [],
            'csrfToken' => 'test-csrf-token',
        ];
        $request = [
            'id' => 15,
            'public_number' => 'FP-TEST-000015',
            'status' => 'awaiting_customer_approval',
            'priority' => 'high',
            'device_type' => 'Ноутбук',
            'device_brand' => 'ASUS',
            'device_model' => 'TUF Test',
            'problem_description' => 'Устройство перегревается при нагрузке.',
            'customer_contact' => '+70000000000',
            'diagnostic_result' => 'Требуется обслуживание системы охлаждения.',
            'estimated_cost' => 12000,
            'final_cost' => null,
            'estimated_ready_at' => '2026-11-15T18:00:00+05:00',
            'created_at' => '2026-11-13T10:00:00+05:00',
            'updated_at' => '2026-11-13T12:00:00+05:00',
            'customer' => ['id' => 7, 'full_name' => 'Тестовый клиент'],
            'technician' => ['id' => 8, 'full_name' => 'Тестовый мастер'],
            'service' => ['id' => 2, 'name' => 'Обслуживание системы охлаждения'],
        ];
        $history = [[
            'from_status' => 'diagnostics',
            'to_status' => 'awaiting_customer_approval',
            'reason' => 'Смета готова к согласованию.',
            'changed_at' => '2026-11-13T12:00:00+05:00',
            'changed_by_name' => 'Тестовый мастер',
        ]];
        $comments = [[
            'id' => 1,
            'body' => 'Пожалуйста, подтвердите смету.',
            'author_name' => 'Тестовый мастер',
            'author_role' => 'technician',
            'created_at' => '2026-11-13T12:05:00+05:00',
            'is_internal' => false,
        ]];
        $requestList = [
            'items' => [$request],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 1],
            'filters' => ['q' => '', 'status' => '', 'priority' => '', 'sort' => 'newest'],
        ];

        $views = [
            ['pages/customer/dashboard', ['recentRequests' => [$request]], 'FP-TEST-000015'],
            ['pages/customer/requests/index', ['requestList' => $requestList], 'FP-TEST-000015'],
            ['pages/customer/requests/create', ['services' => [['id' => 2, 'name' => 'Обслуживание системы охлаждения']]], 'Создать заявку'],
            ['pages/customer/requests/show', [
                'request' => $request,
                'history' => $history,
                'comments' => $comments,
                'permissions' => ['can_comment' => true, 'can_decide_estimate' => true],
            ], 'FP-TEST-000015'],
            ['pages/technician/requests/index', ['requestList' => $requestList], 'FP-TEST-000015'],
            ['pages/technician/requests/show', [
                'request' => $request,
                'history' => $history,
                'comments' => array_merge($comments, [[
                    'id' => 2,
                    'body' => 'Проверить наличие термопасты.',
                    'author_name' => 'Тестовый мастер',
                    'author_role' => 'technician',
                    'created_at' => '2026-11-13T12:10:00+05:00',
                    'is_internal' => true,
                ]]),
                'permissions' => ['can_edit_diagnosis' => true, 'can_change_status' => true, 'can_comment' => true],
                'allowedTransitions' => ['repairing', 'awaiting_parts'],
            ], 'FP-TEST-000015'],
            ['pages/admin/dashboard', [
                'stats' => [
                    'total_requests' => 1,
                    'requests_new' => 0,
                    'requests_in_progress' => 0,
                    'requests_waiting_approval' => 1,
                    'requests_completed' => 0,
                    'active_customers' => 1,
                    'active_technicians' => 1,
                ],
                'recentRequests' => [$request],
            ], 'FP-TEST-000015'],
            ['pages/admin/requests/index', ['requestList' => $requestList], 'FP-TEST-000015'],
        ];

        foreach ($views as [$viewName, $viewData, $expectedContent]) {
            $response = $renderer->renderPage($viewName, array_merge($shared, $viewData));
            $body = $response->body();

            $this->assertSame(200, $response->status(), 'Unexpected status for ' . $viewName);
            $this->assertContains($expectedContent, $body, 'Expected content missing from ' . $viewName);
            $this->assertContains('FixPoint', $body, 'Brand shell missing for ' . $viewName);
        }
    }


    public function testAdminAssignmentUsesProvidedTechniciansAndCsrf(): void
    {
        $assign = $this->read('app/Views/pages/admin/requests/assign.php');
        $registry = $this->read('app/Views/pages/admin/requests/index.php');

        $this->assertContains('method="post"', $assign);
        $this->assertContains('name="_token"', $assign);
        $this->assertContains('name="technician_id"', $assign);
        $this->assertContains('$technicians', $assign);
        $this->assertContains('/assign"', $registry);
        $this->assertNotContains('Иванов Иван', $assign);
    }


    public function testAdminUserViewsRenderEmptyAndPopulatedModels(): void
    {
        $renderer = new ViewRenderer(app()->config());
        $shared = [
            'pageTitle' => 'Admin users render test',
            'activeNav' => 'admin',
            'authUser' => ['id' => 1, 'full_name' => 'Администратор', 'role' => 'admin'],
            'flash' => null,
            'errors' => [],
            'old' => [],
            'csrfToken' => 'test-csrf-token',
        ];
        $user = [
            'id' => 24,
            'full_name' => 'Тестовый мастер',
            'email' => 'tech@example.test',
            'phone' => '+70000000000',
            'role' => 'technician',
            'is_active' => 1,
        ];

        $empty = $renderer->renderPage('pages/admin/users/index', array_merge($shared, ['users' => []]));
        $this->assertSame(200, $empty->status());
        $this->assertContains('Пользователей пока нет', $empty->body());

        $list = $renderer->renderPage('pages/admin/users/index', array_merge($shared, ['users' => [$user]]));
        $this->assertSame(200, $list->status());
        $this->assertContains('Тестовый мастер', $list->body());
        $this->assertContains('tech@example.test', $list->body());
        $this->assertContains('Мастер', $list->body());

        $edit = $renderer->renderPage('pages/admin/users/edit', array_merge($shared, ['user' => $user]));
        $this->assertSame(200, $edit->status());
        $this->assertContains('Тестовый мастер', $edit->body());
        $this->assertContains('tech@example.test', $edit->body());
        $this->assertContains('name="is_active"', $edit->body());
    }

    public function testAdminUserFormsUseCsrfAndDoNotRetainPasswords(): void
    {
        $create = $this->read('app/Views/pages/admin/users/index.php');
        $edit = $this->read('app/Views/pages/admin/users/edit.php');

        $this->assertContains('method="post"', $create);
        $this->assertContains('action="/admin/users"', $create);
        $this->assertContains('name="_token"', $create);
        $this->assertContains('name="full_name"', $create);
        $this->assertContains('name="email"', $create);
        $this->assertContains('name="phone"', $create);
        $this->assertContains('name="role"', $create);
        $this->assertContains('name="password"', $create);
        $this->assertNotContains('$old' . "['password']", $create);

        $this->assertContains('method="post"', $edit);
        $this->assertContains('name="_token"', $edit);
        $this->assertContains('name="full_name"', $edit);
        $this->assertContains('name="email"', $edit);
        $this->assertContains('name="role"', $edit);
        $this->assertContains('name="is_active"', $edit);
        $this->assertNotContains('name="password"', $edit);
    }


    public function testAdminServiceAndCategoryViewsRenderTheirModels(): void
    {
        $renderer = new ViewRenderer(app()->config());
        $shared = [
            'pageTitle' => 'Admin catalogue render test',
            'activeNav' => 'admin',
            'authUser' => ['id' => 1, 'full_name' => 'Администратор', 'role' => 'admin'],
            'flash' => null,
            'errors' => [],
            'old' => [],
            'csrfToken' => 'test-csrf-token',
        ];
        $category = [
            'id' => 3,
            'name' => 'Компьютеры и ноутбуки',
            'slug' => 'computers',
            'description' => 'Ремонт и обслуживание компьютерной техники.',
            'sort_order' => 10,
            'is_active' => 1,
        ];
        $service = [
            'id' => 9,
            'category_id' => 3,
            'category_name' => 'Компьютеры и ноутбуки',
            'name' => 'Чистка ноутбука',
            'slug' => 'laptop-cleaning',
            'short_description' => 'Очистка системы охлаждения.',
            'description' => 'Разборка, очистка и проверка системы охлаждения.',
            'price_from' => 8000,
            'estimated_minutes' => 90,
            'is_active' => 1,
        ];

        $views = [
            ['pages/admin/services/index', ['services' => [], 'categories' => []], 'Добавить услугу'],
            ['pages/admin/services/index', ['services' => [$service], 'categories' => [$category]], 'Чистка ноутбука'],
            ['pages/admin/services/edit', ['service' => $service, 'categories' => [$category]], 'laptop-cleaning'],
            ['pages/admin/categories/index', ['categories' => []], 'Создать категорию'],
            ['pages/admin/categories/index', ['categories' => [$category]], 'Компьютеры и ноутбуки'],
            ['pages/admin/categories/edit', ['category' => $category], 'computers'],
        ];

        foreach ($views as [$viewName, $viewData, $expected]) {
            $response = $renderer->renderPage($viewName, array_merge($shared, $viewData));
            $this->assertSame(200, $response->status(), 'Unexpected status for ' . $viewName);
            $this->assertContains($expected, $response->body(), 'Expected content missing from ' . $viewName);
        }
    }

    public function testAdminServiceAndCategoryFormsUsePostAndCsrf(): void
    {
        $serviceCreate = $this->read('app/Views/pages/admin/services/index.php');
        $serviceEdit = $this->read('app/Views/pages/admin/services/edit.php');
        $categoryCreate = $this->read('app/Views/pages/admin/categories/index.php');
        $categoryEdit = $this->read('app/Views/pages/admin/categories/edit.php');

        foreach ([$serviceCreate, $serviceEdit, $categoryCreate, $categoryEdit] as $view) {
            $this->assertContains('method="post"', $view);
            $this->assertContains('name="_token"', $view);
        }
        foreach (['category_id', 'name', 'slug', 'short_description', 'description', 'price_from', 'estimated_minutes', 'is_active'] as $field) {
            $this->assertContains('name="' . $field . '"', $serviceCreate);
            $this->assertContains('name="' . $field . '"', $serviceEdit);
        }
        foreach (['name', 'slug', 'description', 'sort_order', 'is_active'] as $field) {
            $this->assertContains('name="' . $field . '"', $categoryCreate);
            $this->assertContains('name="' . $field . '"', $categoryEdit);
        }
    }

    private function read(string $relativePath): string
    {
        $path = $this->root . '/' . $relativePath;
        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException('Unable to read UI contract file: ' . $relativePath);
        }

        return $content;
    }
}
