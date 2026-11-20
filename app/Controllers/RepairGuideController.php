<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\RepairGuideService;

final class RepairGuideController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly RepairGuideService $guides
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $query = is_string($request->query('q', '')) ? (string) $request->query('q', '') : '';
        $category = is_string($request->query('category', '')) ? (string) $request->query('category', '') : '';
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $data = $this->guides->publicIndex($query, $category, $page === false ? 1 : (int) $page);

        return $this->renderPage('pages/public/guides/index', [
            'pageTitle' => 'Частые неисправности — ' . $this->config->get('app.name'),
            'activeNav' => 'guides',
            'guides' => $data['items'],
            'categories' => $data['categories'],
            'filters' => $data['filters'],
            'pagination' => $data['pagination'],
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $this->abort(404, 'Invalid repair guide slug');
        }

        $guide = $this->guides->publicArticle($slug);

        return $this->renderPage('pages/public/guides/show', [
            'pageTitle' => $guide['title'] . ' — FixPoint',
            'activeNav' => 'guides',
            'guide' => $guide,
        ]);
    }
}
