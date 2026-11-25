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

    public function create(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();

        return $this->renderPage('pages/admin/requests/create', [
            'pageTitle' => 'Новая заявка — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'customers' => $this->requestService->customersForAdminSelect($user),
            'services' => $this->requestService->servicesForSelect(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $customerId = $this->stringPost($request, 'customer_id');
        $input = [
            'device_type' => $this->stringPost($request, 'device_type'),
            'device_brand' => $this->stringPost($request, 'device_brand'),
            'device_model' => $this->stringPost($request, 'device_model'),
            'service_id' => $this->stringPost($request, 'service_id'),
            'problem_description' => $this->stringPost($request, 'problem_description'),
            'customer_contact' => $this->stringPost($request, 'customer_contact'),
        ];

        try {
            $requestId = $this->requestService->createForAdmin($user, $customerId, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld($input + ['customer_id' => $customerId]);

            return Response::redirect('/admin/requests/create');
        }

        $created = $this->requestService->findForAdmin($requestId, $user);
        $number = is_array($created) ? (string) ($created['public_number'] ?? '') : '';
        $this->session->flash(
            'success',
            $number === '' ? 'Заявка создана.' : 'Заявка ' . $number . ' создана.'
        );

        return Response::redirect('/admin/requests');
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
