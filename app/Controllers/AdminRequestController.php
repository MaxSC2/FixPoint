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
use FixPoint\Services\RequestService;

/**
 * Administrator request workspace: the global registry and technician
 * assignment. Both routes are admin-only; RequestService validates every
 * filter, assignee and immutable terminal state again.
 */
final class AdminRequestController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly RequestService $requestService
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);

        return $this->renderPage('pages/admin/requests/index', [
            'pageTitle' => 'Реестр заявок — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'requestList' => $this->requestService->listForAdmin(
                $user,
                $request->allQuery(),
                $page === false ? 1 : (int) $page
            ),
        ]);
    }

    public function showAssign(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $requestId = $this->requestId($params);
        $row = $this->requestService->findForAdmin($requestId, $user);
        if ($row === null) {
            $this->abort(404, 'Request not found');
        }

        return $this->renderPage('pages/admin/requests/assign', [
            'pageTitle' => 'Назначение мастера — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'request' => $this->requestService->present($row),
            'technicians' => $this->requestService->techniciansForAssignment($user),
        ]);
    }

    public function assign(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $requestId = $this->requestId($params);
        $technicianId = $this->stringPost($request, 'technician_id');

        try {
            $this->requestService->assignTechnician($requestId, $user, $technicianId);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld(['technician_id' => $technicianId]);

            return Response::redirect('/admin/requests/' . $requestId . '/assign');
        }

        $this->session->flash('success', 'Мастер назначен.');

        return Response::redirect('/admin/requests');
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

    private function requestId(array $params): int
    {
        $requestId = (int) ($params['id'] ?? 0);
        if ($requestId < 1) {
            $this->abort(404, 'Request not found');
        }

        return $requestId;
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
