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
use FixPoint\Services\AppointmentService;

/**
 * Customer appointment history and booking. A booking always originates from
 * one of the customer's own requests; ownership is re-checked in the service.
 */
final class CustomerAppointmentController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly AppointmentService $appointmentService
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->requireCustomer();
        $list = $this->appointmentService->listForCustomer($user);

        return $this->renderPage('pages/customer/appointments/index', [
            'pageTitle' => 'Мои встречи — ' . $this->config->get('app.name'),
            'activeNav' => 'requests',
            'appointmentList' => $list,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->requireCustomer();
        $appointmentId = $this->appointmentId($params);
        $detail = $this->appointmentService->detail($user, $appointmentId, $request->allQuery());

        return $this->renderPage('pages/customer/appointments/show', array_merge($detail, [
            'pageTitle' => 'Встреча — ' . $this->config->get('app.name'),
            'activeNav' => 'requests',
            'appointmentId' => $appointmentId,
        ]));
    }

    public function create(Request $request, array $params = []): Response
    {
        $user = $this->requireCustomer();
        $requestId = $this->requestId($params);
        $context = $this->appointmentService->bookingContext($user, $requestId, $request->allQuery());

        return $this->renderPage('pages/customer/appointments/book', array_merge($context, [
            'pageTitle' => 'Запись на встречу — ' . $this->config->get('app.name'),
            'activeNav' => 'requests',
            'requestId' => $requestId,
        ]));
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->requireCustomer();
        $requestId = $this->requestId($params);
        $date = $this->stringPost($request, 'date');
        $input = [
            'meeting_type' => $this->stringPost($request, 'meeting_type'),
            'start_utc' => $this->stringPost($request, 'start_utc'),
            'customer_comment' => $this->stringPost($request, 'customer_comment'),
            'date' => $date,
        ];

        try {
            $appointmentId = $this->appointmentService->book($user, $requestId, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld($input);

            $url = '/requests/' . $requestId . '/appointments/book';
            if ($date !== '') {
                $url .= '?date=' . rawurlencode($date);
            }

            return Response::redirect($url);
        }

        $this->session->flash('success', 'Время удержано за вами. Ожидайте подтверждения мастера.');

        return Response::redirect('/appointments/' . $appointmentId);
    }

    public function cancel(Request $request, array $params = []): Response
    {
        $user = $this->requireCustomer();
        $appointmentId = $this->appointmentId($params);
        $reason = $this->stringPost($request, 'reason');

        try {
            $this->appointmentService->cancel($user, $appointmentId, $reason);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);

            return Response::redirect('/appointments/' . $appointmentId);
        }

        $this->session->flash('success', 'Встреча отменена.');

        return Response::redirect('/appointments/' . $appointmentId);
    }

    public function reschedule(Request $request, array $params = []): Response
    {
        $user = $this->requireCustomer();
        $appointmentId = $this->appointmentId($params);
        $startUtc = $this->stringPost($request, 'start_utc');
        $note = $this->stringPost($request, 'note');
        $date = $this->stringPost($request, 'date');

        try {
            $this->appointmentService->proposeReschedule($user, $appointmentId, $startUtc, $note);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld(['start_utc' => $startUtc, 'note' => $note]);

            return Response::redirect($this->detailUrl($appointmentId, $date));
        }

        $this->session->flash('success', 'Запрос на перенос отправлен мастеру.');

        return Response::redirect('/appointments/' . $appointmentId);
    }

    public function proposal(Request $request, array $params = []): Response
    {
        $user = $this->requireCustomer();
        $appointmentId = $this->appointmentId($params);
        $decision = $this->stringPost($request, 'decision');
        $reason = $this->stringPost($request, 'reason');

        try {
            $this->appointmentService->respondToProposal($user, $appointmentId, $decision, $reason);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);

            return Response::redirect('/appointments/' . $appointmentId);
        }

        $this->session->flash(
            'success',
            $decision === 'accept' ? 'Новое время подтверждено.' : 'Перенос отклонён.'
        );

        return Response::redirect('/appointments/' . $appointmentId);
    }

    /** @return array<string, mixed> */
    private function requireCustomer(): array
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->abort(403, 'Authentication required');
        }
        if ((string) ($user['role'] ?? '') !== 'customer') {
            $this->abort(403, 'Customer access required');
        }

        return $user;
    }

    /** @param array<string, string> $params */
    private function requestId(array $params): int
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            $this->abort(404, 'Request not found');
        }

        return $id;
    }

    /** @param array<string, string> $params */
    private function appointmentId(array $params): int
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            $this->abort(404, 'Appointment not found');
        }

        return $id;
    }

    private function detailUrl(int $appointmentId, string $date): string
    {
        $url = '/appointments/' . $appointmentId;

        return $date === '' ? $url : $url . '?date=' . rawurlencode($date);
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
