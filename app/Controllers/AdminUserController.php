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
use FixPoint\Services\AdminUserService;

/**
 * Administrator user management: list, create and edit accounts. Every route
 * is admin-only; AdminUserService re-checks the role, validates input, hashes
 * new passwords and protects the last active administrator.
 */
final class AdminUserController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly AdminUserService $userService
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();

        return $this->renderPage('pages/admin/users/index', [
            'pageTitle' => 'Пользователи — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'users' => $this->userService->listUsers($user),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $input = [
            'full_name' => $this->stringPost($request, 'full_name'),
            'email' => $this->stringPost($request, 'email'),
            'phone' => $this->stringPost($request, 'phone'),
            'role' => $this->stringPost($request, 'role'),
            'password' => $this->stringPost($request, 'password'),
        ];

        try {
            $this->userService->create($user, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            unset($input['password']);
            $this->session->flashOld($input);

            return Response::redirect('/admin/users');
        }

        $this->session->flash('success', 'Учётная запись создана.');

        return Response::redirect('/admin/users');
    }

    public function showEdit(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $userId = $this->userId($params);
        $editable = $this->userService->findUser($userId, $user);
        if ($editable === null) {
            $this->abort(404, 'User not found');
        }

        return $this->renderPage('pages/admin/users/edit', [
            'pageTitle' => 'Редактирование пользователя — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'user' => $editable,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $userId = $this->userId($params);
        $input = [
            'full_name' => $this->stringPost($request, 'full_name'),
            'email' => $this->stringPost($request, 'email'),
            'phone' => $this->stringPost($request, 'phone'),
            'role' => $this->stringPost($request, 'role'),
            'is_active' => $this->stringPost($request, 'is_active'),
        ];

        try {
            $this->userService->update($userId, $user, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld($input);

            return Response::redirect('/admin/users/' . $userId . '/edit');
        }

        $this->session->flash('success', 'Изменения сохранены.');

        return Response::redirect('/admin/users');
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

    private function userId(array $params): int
    {
        $userId = (int) ($params['id'] ?? 0);
        if ($userId < 1) {
            $this->abort(404, 'User not found');
        }

        return $userId;
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
