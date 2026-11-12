<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ViewRenderer;
use FixPoint\Core\Config;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\CatalogService;

final class ServicesController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly CatalogService $catalog
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $categories = $this->catalog->categories();
        $validSlugs = array_column($categories, 'slug');

        $query = trim((string) $request->query('q', ''));
        $query = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $query);
        if (mb_strlen($query) > 100) {
            $query = mb_substr($query, 0, 100);
        }

        $category = trim((string) $request->query('category', ''));
        if ($category !== '' && !in_array($category, $validSlugs, true)) {
            $category = '';
        }

        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $page = $page === false ? 1 : (int) $page;

        $catalog = $this->catalog->search($query, $category, $page);

        return $this->renderPage('pages/public/services/index', [
            'pageTitle' => 'Услуги — ' . $this->config->get('app.name'),
            'activeNav' => 'services',
            'categories' => $categories,
            'services' => $catalog['items'],
            'pagination' => $catalog['pagination'],
            'filters' => $catalog['filters'],
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if (preg_match('/^[a-z0-9-]{1,180}$/', $slug) !== 1) {
            $this->abort(404, 'Invalid service slug');
        }

        $service = $this->catalog->findBySlug($slug);
        if ($service === null) {
            $this->abort(404, 'Service not found');
        }

        return $this->renderPage('pages/public/services/show', [
            'pageTitle' => $service['name'] . ' — ' . $this->config->get('app.name'),
            'activeNav' => 'services',
            'service' => $service,
        ]);
    }
}
