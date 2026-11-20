<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\HttpException;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ValidationException;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\RepairGuideService;

final class AdminGuideController extends Controller
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
        $user = $this->requireAdmin();
        return $this->renderPage('pages/admin/guides/index', [
            'pageTitle' => 'База знаний — администрирование',
            'activeNav' => 'admin',
            'guides' => $this->guides->adminIndex($user),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $this->requireAdmin();
        return $this->renderForm([]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->requireAdmin();
        $input = $this->input($request);
        try {
            $this->guides->create($user, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception, $input);
            return Response::redirect('/admin/guides/create');
        }
        $this->session->flash('success', 'Статья добавлена в базу знаний.');
        return Response::redirect('/admin/guides');
    }

    public function edit(Request $request, array $params = []): Response
    {
        $user = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $guide = $this->guides->adminFind($user, $id);
        return $this->renderForm($guide);
    }

    public function update(Request $request, array $params = []): Response
    {
        $user = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $input = $this->input($request);
        try {
            $this->guides->update($user, $id, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception, $input);
            return Response::redirect('/admin/guides/' . $id . '/edit');
        }
        $this->session->flash('success', 'Статья обновлена.');
        return Response::redirect('/admin/guides');
    }

    public function archive(Request $request, array $params = []): Response
    {
        $user = $this->requireAdmin();
        $this->guides->archive($user, (int) ($params['id'] ?? 0));
        $this->session->flash('success', 'Статья перемещена в архив.');
        return Response::redirect('/admin/guides');
    }

    /** @return array<string, mixed> */
    private function requireAdmin(): array
    {
        $user = $this->auth->user();
        if ($user === null) {
            throw new HttpException(403, 'Authentication required');
        }
        if (($user['role'] ?? null) !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }
        return $user;
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        $keys = [
            'title', 'slug', 'category_slug', 'device_type', 'summary', 'symptoms',
            'possible_causes', 'steps', 'do_not', 'when_to_contact', 'status',
        ];
        $input = [];
        foreach ($keys as $key) {
            $value = $request->post($key, '');
            $input[$key] = is_string($value) ? trim($value) : '';
        }
        return $input;
    }

    /** @param array<string, mixed> $input */
    private function flashValidation(ValidationException $exception, array $input): void
    {
        $this->session->flashErrors($exception->errors());
        $this->session->flashOld($input);
        $this->session->flash('error', 'Проверьте заполненные поля.');
    }

    /** @param array<string, mixed> $guide */
    private function renderForm(array $guide): Response
    {
        $id = (int) ($guide['id'] ?? 0);
        return $this->renderPage('pages/admin/guides/form', [
            'pageTitle' => $id > 0 ? 'Редактирование статьи' : 'Новая статья',
            'activeNav' => 'admin',
            'guide' => $guide,
            'categories' => $this->guides->categoryOptions(),
            'formAction' => $id > 0 ? '/admin/guides/' . $id . '/edit' : '/admin/guides',
        ]);
    }
}
