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
 * Technician workspace: scoped queue, work card, diagnosis and controlled
 * status transitions. The queue is scoped by technician_id in the repository;
 * detail and mutations re-check assignment in RequestService.
 */
final class TechnicianController extends Controller
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

    public function queue(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);
        $requestList = $this->requestService->listQueue(
            $user,
            $request->allQuery(),
            $page === false ? 1 : (int) $page
        );

        return $this->renderPage('pages/technician/requests/index', [
            'pageTitle' => 'Очередь мастера — ' . $this->config->get('app.name'),
            'activeNav' => 'technician',
            'requestList' => $requestList,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $requestId = $this->requestId($params);
        $row = $this->requestService->findForStaff($requestId, $user);
        if ($row === null) {
            $this->abort(404, 'Request not found');
        }
        $presented = $this->requestService->present($row);

        return $this->renderPage('pages/technician/requests/show', [
            'pageTitle' => 'Рабочая карточка ' . (string) $presented['public_number'],
            'activeNav' => 'technician',
            'request' => $presented,
            'history' => $this->requestService->history($requestId),
            'comments' => $this->requestService->staffComments($requestId, $user),
            'permissions' => $this->requestService->staffPermissions($row, $user),
            'allowedTransitions' => $this->requestService->allowedTransitionsFor($row, $user),
        ]);
    }

    public function updateDiagnosis(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $requestId = $this->requestId($params);
        $input = [
            'diagnostic_result' => $this->stringPost($request, 'diagnostic_result'),
            'estimated_cost' => $this->stringPost($request, 'estimated_cost'),
            'estimated_ready_at' => $this->stringPost($request, 'estimated_ready_at'),
        ];

        try {
            $this->requestService->updateDiagnosis($requestId, $user, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld($input);

            return Response::redirect('/technician/requests/' . $requestId);
        }

        $this->session->flash('success', 'Диагностика сохранена.');

        return Response::redirect('/technician/requests/' . $requestId);
    }

    public function updateStatus(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $requestId = $this->requestId($params);
        $status = $this->stringPost($request, 'status');
        $reason = $this->stringPost($request, 'reason');

        try {
            $this->requestService->changeStatus($requestId, $user, $status, $reason);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld(['status' => $status, 'reason' => $reason]);

            return Response::redirect('/technician/requests/' . $requestId);
        }

        $this->session->flash('success', 'Статус обновлён: ' . status_label($status) . '.');

        return Response::redirect('/technician/requests/' . $requestId);
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
