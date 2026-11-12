<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Services\CatalogService;

final class HomeController extends Controller
{
    public function __construct(
        \FixPoint\Core\Config $config,
        \FixPoint\Core\ViewRenderer $view,
        \FixPoint\Security\Auth $auth,
        \FixPoint\Security\Csrf $csrf,
        \FixPoint\Core\Session $session,
        private readonly CatalogService $catalog
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $overview = $this->catalog->overview(6);

        return $this->renderPage('pages/public/home', [
            'pageTitle' => 'FixPoint — сервисный центр',
            'activeNav' => 'home',
            'stats' => [
                'categories_count' => $overview['categories_count'],
                'services_count' => $overview['services_count'],
            ],
            'featuredServices' => $overview['popular_services'],
            'foundation' => [
                'environment' => (string) $this->config->get('app.env'),
                'php_version' => PHP_VERSION,
            ],
        ]);
    }
}
