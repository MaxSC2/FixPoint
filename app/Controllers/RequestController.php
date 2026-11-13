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
 * Customer request flow: scoped list, creation, detail, comments and estimate
 * decisions. Every action re-checks ownership on the server; the route role
 * gate is not treated as sufficient authorization.
 */
final class RequestController extends Controller
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
        $requestList = $this->requestService->listForCustomer(
            (int) $user['id'],
            $request->allQuery(),
            $page === false ? 1 : (int) $page
        );

        return $this->renderPage('pages/customer/requests/index', [
            'pageTitle' => 'Мои заявки — ' . $this->config->get('app.name'),
            'activeNav' => 'requests',
            'requestList' => $requestList,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $this->requireUser();

        return $this->renderPage('pages/customer/requests/create', [
            'pageTitle' => 'Новая заявка — ' . $this->config->get('app.name'),
            'activeNav' => 'requests',
            'services' => $this->requestService->servicesForSelect(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $input = [
            'device_type' => $this->stringPost($request, 'device_type'),
            'device_brand' => $this->stringPost($request, 'device_brand'),
            'device_model' => $this->stringPost($request, 'device_model'),
            'service_id' => $this->stringPost($request, 'service_id'),
            'problem_description' => $this->stringPost($request, 'problem_description'),
            'customer_contact' => $this->stringPost($request, 'customer_contact'),
        ];

        try {
            $requestId = $this->requestService->create((int) $user['id'], $input);
        } catch (ValidationException $exception) {
            $this->session->flashErrors($exception->errors());
            $this->session->flashOld($input);

            return Response::redirect('/requests/create');
        }

        $created = $this->requestService->findVisibleForUser($requestId, $user);
        $number = (string) ($created['public_number'] ?? '');
        $this->session->flash(
            'success',
            $number === '' ? 'Заявка создана.' : 'Заявка ' . $number . ' создана.'
        );

        return Response::redirect('/requests/' . $requestId);
    }

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $requestId = (int) ($params['id'] ?? 0);
        $row = $this->requestService->findVisibleForUser($requestId, $user);
        if ($row === null) {
            $this->abort(404, 'Request not found');
        }

        return $this->renderPage('pages/customer/requests/show', [
            'pageTitle' => (string) $row['public_number'],
            'activeNav' => 'requests',
            'request' => $this->requestService->present($row),
            'history' => $this->requestService->history($requestId),
            'comments' => $this->requestService->visibleComments($requestId),
            'permissions' => $this->requestService->permissionsFor($row, $user),
        ]);
    }

    public function comment(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $requestId = (int) ($params['id'] ?? 0);
        $body = $this->stringPost($request, 'body');

        try {
            $this->requestService->addComment($requestId, $user, $body);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (isset($errors['general'])) {
                $this->session->flash('error', $errors['general']);
                unset($errors['general']);
            }
            if ($errors !== []) {
                $this->session->flashErrors($errors);
            }

            return Response::redirect('/requests/' . $requestId . '#comments');
        }

        $this->session->flash('success', 'Комментарий добавлен.');

        return Response::redirect('/requests/' . $requestId . '#comments');
    }

    public function decideEstimate(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $requestId = (int) ($params['id'] ?? 0);

        if ($this->requestService->findVisibleForUser($requestId, $user) === null) {
            $this->abort(404, 'Request not found');
        }
        if ((string) ($user['role'] ?? '') !== 'customer') {
            $this->abort(403, 'Only the request owner can decide the estimate');
        }

        $decision = $this->stringPost($request, 'decision');

        try {
            $this->requestService->decideEstimate($requestId, $user, $decision);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (isset($errors['general'])) {
                $this->session->flash('error', $errors['general']);
                unset($errors['general']);
            }
            if ($errors !== []) {
                $this->session->flashErrors($errors);
            }

            return Response::redirect('/requests/' . $requestId);
        }

        $this->session->flash(
            'success',
            $decision === 'approve'
                ? 'Смета согласована. Ремонт начнётся после подготовки.'
                : 'Смета отклонена. Заявка отменена.'
        );

        return Response::redirect('/requests/' . $requestId);
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

    private function stringPost(Request $request, string $key): string
    {
        $value = $request->post($key, '');

        return is_string($value) ? trim($value) : '';
    }
}
