<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Controllers\HomeController;
use FixPoint\Controllers\PageController;
use FixPoint\Controllers\ServicesController;
use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\ViewRenderer;
use FixPoint\Services\CatalogService;
use FixPoint\Tests\IntegrationTestCase;

/**
 * The frontend templates depend on exact logical view names and data keys.
 * These tests render temporary marker templates through the real controllers,
 * so a renamed view or key fails here instead of silently falling back.
 */
final class ControllerContractTest extends IntegrationTestCase
{
    /** @var list<string> */
    private array $tempDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $directory) {
            $this->removeDirectory($directory);
        }
        $this->tempDirectories = [];
    }

    public function testHomeControllerUsesContractViewNameAndFeaturedServicesKey(): void
    {
        $config = $this->configWithView(
            'pages/public/home.php',
            '<?php echo json_encode(["marker" => "home", "keys" => array_keys(get_defined_vars())]);'
        );

        $controller = new HomeController(
            $config,
            new ViewRenderer($config),
            app()->auth(),
            app()->csrf(),
            app()->session(),
            app()->make(CatalogService::class)
        );

        $response = $controller->index(Request::create('GET', '/'));
        $data = json_decode($response->body(), true);

        $this->assertIsArray($data);
        $this->assertSame('home', $data['marker'] ?? null);
        $this->assertTrue(in_array('featuredServices', $data['keys'] ?? [], true));
        $this->assertFalse(in_array('popular_services', $data['keys'] ?? [], true));
    }

    public function testServicesControllerUsesServicesKeyNotItems(): void
    {
        $config = $this->configWithView(
            'pages/public/services/index.php',
            '<?php echo json_encode(["marker" => "services", "keys" => array_keys(get_defined_vars())]);'
        );

        $controller = new ServicesController(
            $config,
            new ViewRenderer($config),
            app()->auth(),
            app()->csrf(),
            app()->session(),
            app()->make(CatalogService::class)
        );

        $response = $controller->index(Request::create('GET', '/services'));
        $data = json_decode($response->body(), true);

        $this->assertIsArray($data);
        $this->assertSame('services', $data['marker'] ?? null);
        $this->assertTrue(in_array('services', $data['keys'] ?? [], true));
        $this->assertFalse(in_array('items', $data['keys'] ?? [], true));
    }

    public function testServiceDetailExposesTopLevelCategoryName(): void
    {
        $config = $this->configWithView(
            'pages/public/services/show.php',
            '<?php echo json_encode(["category_name" => $service["category_name"] ?? null]);'
        );

        $controller = new ServicesController(
            $config,
            new ViewRenderer($config),
            app()->auth(),
            app()->csrf(),
            app()->session(),
            app()->make(CatalogService::class)
        );

        $response = $controller->show(Request::create('GET', '/services/laptop-cleaning'), ['slug' => 'laptop-cleaning']);
        $data = json_decode($response->body(), true);

        $this->assertIsArray($data);
        $this->assertSame('Компьютеры и ноутбуки', $data['category_name'] ?? null);
    }

    public function testPageControllerRendersAboutAndContactsViews(): void
    {
        $aboutConfig = $this->configWithView(
            'pages/public/about.php',
            '<?php echo json_encode(["marker" => "about"]);'
        );
        $about = new PageController(
            $aboutConfig,
            new ViewRenderer($aboutConfig),
            app()->auth(),
            app()->csrf(),
            app()->session()
        );
        $aboutResponse = $about->about(Request::create('GET', '/about'));
        $this->assertSame('about', json_decode($aboutResponse->body(), true)['marker'] ?? null);

        $contactsConfig = $this->configWithView(
            'pages/public/contacts.php',
            '<?php echo json_encode(["phone" => $contactPhone ?? null]);'
        );
        $contacts = new PageController(
            $contactsConfig,
            new ViewRenderer($contactsConfig),
            app()->auth(),
            app()->csrf(),
            app()->session()
        );
        $contactsResponse = $contacts->contacts(Request::create('GET', '/contacts'));
        $this->assertSame('', json_decode($contactsResponse->body(), true)['phone'] ?? null);
    }

    private function configWithView(string $relativePath, string $template): Config
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixpoint_contract_' . bin2hex(random_bytes(5));
        $this->tempDirectories[] = $directory;

        $file = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $parent = dirname($file);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            $this->fail('Cannot create temporary view directory.');
        }
        file_put_contents($file, $template);

        $values = app()->config()->all();
        $values['paths']['views'] = $directory;
        $values['paths']['fallback_views'] = $directory . DIRECTORY_SEPARATOR . 'fallback';

        return new Config($values);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
    }
}
