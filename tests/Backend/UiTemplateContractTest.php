<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

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

        $this->assertContains('$requestList' . "['items']", $list);
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
        $this->assertContains('$permissions' . "['can_decide_estimate']", $detail);
        $this->assertContains('$permissions' . "['can_comment']", $detail);
        $this->assertContains('action="/requests/<?= e(rawurlencode($requestId)) ?>/estimate-decision"', $detail);
        $this->assertContains('action="/requests/<?= e(rawurlencode($requestId)) ?>/comments"', $detail);
        $this->assertContains('name="_token"', $detail);
        $this->assertContains('value="approve"', $detail);
        $this->assertContains('value="reject"', $detail);
        $this->assertContains('empty($comment' . "['is_internal'])", $detail);
    }

    public function testReusableRequestCardUsesStatusComponentAndRequestIdentifier(): void
    {
        $card = $this->read('app/Views/components/request-card.php');

        $this->assertContains("['id']", $card);
        $this->assertContains("['public_number']", $card);
        $this->assertContains('status-badge.php', $card);
        $this->assertContains("'/requests/'", $card);
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
