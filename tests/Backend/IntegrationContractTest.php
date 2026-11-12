<?php

declare(strict_types=1);

namespace FixPoint\\Tests\\Backend;

use FixPoint\\Tests\\TestCase;

/**
 * Guards the controller/view boundary against accidental key and path drift.
 * Runtime rendering is covered separately by the HTTP smoke suite.
 */
final class IntegrationContractTest extends TestCase
{
    public function testHomeControllerUsesTheFrontendLogicalViewAndKeys(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/app/Controllers/HomeController.php');

        $this->assertNotSame(false, $source);
        $this->assertContains("renderPage('pages/public/home'", (string) $source);
        $this->assertContains("'featuredServices' => $overview['popular_services']", (string) $source);
        $this->assertNotContains("renderPage('pages/public/home/index'", (string) $source);
        $this->assertNotContains("'popular_services' =>", (string) $source);
    }

    public function testServicesControllerUsesTheFrontendCatalogueKey(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/app/Controllers/ServicesController.php');

        $this->assertNotSame(false, $source);
        $this->assertContains("renderPage('pages/public/services/index'", (string) $source);
        $this->assertContains("'services' => $catalog['items']", (string) $source);
        $this->assertNotContains("'items' => $catalog['items']", (string) $source);
    }

    public function testCatalogServiceExposesTopLevelCategoryName(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/app/Services/CatalogService.php');

        $this->assertNotSame(false, $source);
        $this->assertContains("'category_name' => (string) $row['category_name']", (string) $source);
    }
}
