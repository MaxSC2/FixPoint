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
        $this->assertContains('name="email"', $login);
        $this->assertContains('name="password"', $login);

        $this->assertContains('action="/register"', $register);
        $this->assertContains('name="full_name"', $register);
        $this->assertContains('name="email"', $register);
        $this->assertContains('name="phone"', $register);
        $this->assertContains('name="password_confirmation"', $register);
    }

    public function testAuthenticationViewsDoNotRetainSecretsInOldInput(): void
    {
        $login = $this->read('app/Views/pages/auth/login.php');
        $register = $this->read('app/Views/pages/auth/register.php');

        $this->assertNotContains("$old['password']", $login);
        $this->assertNotContains("$old['password']", $register);
        $this->assertNotContains("$old['password_confirmation']", $register);
        $this->assertContains("$old['email']", $login);
        $this->assertContains("$old['full_name']", $register);
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
