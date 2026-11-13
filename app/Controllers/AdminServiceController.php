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
 * Administrator service catalogue: list, create and edit. All routes are
 * admin-only; AdminCatalogService validates categories, slugs, prices and
 * visibility before touching the repository.
 */
final class AdminServiceController extends Controller
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

        return $this->renderPage('pages/admin/services/index', [
            'pageTitle' => 'Услуги — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'services' => $this->catalog->listServices($user),
            'categories' => $this->catalog->listCategories($user),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $input = $this->serviceInput($request);

        try {
            $this->catalog->createService($user, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld($input);

            return Response::redirect('/admin/services');
        }

        $this->session->flash('success', 'Услуга создана.');

        return Response::redirect('/admin/services');
    }

    public function showEdit(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $serviceId = $this->serviceId($params);
        $service = $this->catalog->findService($serviceId, $user);
        if ($service === null) {
            $this->abort(404, 'Service not found');
        }

        return $this->renderPage('pages/admin/services/edit', [
            'pageTitle' => 'Редактирование услуги — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'service' => $service,
            'categories' => $this->catalog->listCategories($user),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $serviceId = $this->serviceId($params);
        $input = $this->serviceInput($request);

        try {
            $this->catalog->updateService($serviceId, $user, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld($input);

            return Response::redirect('/admin/services/' . $serviceId . '/edit');
        }

        $this->session->flash('success', 'Услуга сохранена.');

        return Response::redirect('/admin/services');
    }

    /** @return array<string, string> */
    private function serviceInput(Request $request): array
    {
        $input = [
            'category_id' => $this->stringPost($request, 'category_id'),
            'name' => $this->stringPost($request, 'name'),
            'slug' => $this->stringPost($request, 'slug'),
            'short_description' => $this->stringPost($request, 'short_description'),
            'description' => $this->stringPost($request, 'description'),
            'price_from' => $this->stringPost($request, 'price_from'),
            'estimated_minutes' => $this->stringPost($request, 'estimated_minutes'),
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

    private function serviceId(array $params): int
    {
        $serviceId = (int) ($params['id'] ?? 0);
        if ($serviceId < 1) {
            $this->abort(404, 'Service not found');
        }

        return $serviceId;
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
