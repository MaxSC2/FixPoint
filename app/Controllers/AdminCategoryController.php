<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ValidationException;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\AdminCatalogService;

/**
 * Administrator category directory: list, create and edit. Categories are
 * never hard-deleted; deactivating one hides its services from the public
 * catalogue without destroying records.
 */
final class AdminCategoryController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly AdminCatalogService $catalog
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();

        return $this->renderPage('pages/admin/categories/index', [
            'pageTitle' => 'Категории услуг — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'categories' => $this->catalog->listCategories($user),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $input = $this->categoryInput($request);

        try {
            $this->catalog->createCategory($user, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld($input);

            return Response::redirect('/admin/categories');
        }

        $this->session->flash('success', 'Категория создана.');

        return Response::redirect('/admin/categories');
    }

    public function showEdit(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $categoryId = $this->categoryId($params);
        $category = $this->catalog->findCategory($categoryId, $user);
        if ($category === null) {
            $this->abort(404, 'Category not found');
        }

        return $this->renderPage('pages/admin/categories/edit', [
            'pageTitle' => 'Редактирование категории — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'category' => $category,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $categoryId = $this->categoryId($params);
        $input = $this->categoryInput($request);

        try {
            $this->catalog->updateCategory($categoryId, $user, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld($input);

            return Response::redirect('/admin/categories/' . $categoryId . '/edit');
        }

        $this->session->flash('success', 'Категория сохранена.');

        return Response::redirect('/admin/categories');
    }

    /** @return array<string, string> */
    private function categoryInput(Request $request): array
    {
        $input = [
            'name' => $this->stringPost($request, 'name'),
            'slug' => $this->stringPost($request, 'slug'),
            'description' => $this->stringPost($request, 'description'),
            'sort_order' => $this->stringPost($request, 'sort_order'),
        ];
        if (array_key_exists('is_active', $request->allPost())) {
            $input['is_active'] = $this->stringPost($request, 'is_active');
        }

        return $input;
    }

    /** @return array<string, mixed> */
    private function requireUser(): array
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->abort(403, 'Authentication required');
        }

        return $user;
    }

    private function categoryId(array $params): int
    {
        $categoryId = (int) ($params['id'] ?? 0);
        if ($categoryId < 1) {
            $this->abort(404, 'Category not found');
        }

        return $categoryId;
    }

    private function stringPost(Request $request, string $key): string
    {
        $value = $request->post($key, '');

        return is_string($value) ? trim($value) : '';
    }

    private function flashValidation(ValidationException $exception): void
    {
        $errors = $exception->errors();
        if (isset($errors['general'])) {
            $this->session->flash('error', $errors['general']);
            unset($errors['general']);
        }
        if ($errors !== []) {
            $this->session->flashErrors($errors);
        }
    }
}
