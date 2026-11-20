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
 * Technician appointment card and lifecycle actions. The assigned technician is
 * re-checked inside AppointmentService for every mutation.
 */
final class TechnicianAppointmentController extends Controller
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

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $appointmentId = $this->appointmentId($params);
        $detail = $this->appointmentService->detail($user, $appointmentId, $request->allQuery());

        return $this->renderPage('pages/technician/appointments/show', array_merge($detail, [
            'pageTitle' => 'Встреча ' . (string) ($detail['appointment']['request_number'] ?? '')
                . ' — ' . $this->config->get('app.name'),
            'activeNav' => 'technician',
            'appointmentId' => $appointmentId,
        ]));
    }

    public function confirm(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $appointmentId = $this->appointmentId($params);

        try {
            $this->appointmentService->confirm($user, $appointmentId);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);

            return Response::redirect('/technician/appointments/' . $appointmentId);
        }

        $this->session->flash('success', 'Встреча подтверждена.');

        return Response::redirect('/technician/appointments/' . $appointmentId);
    }

    public function decline(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $appointmentId = $this->appointmentId($params);
        $reason = $this->stringPost($request, 'reason');

        try {
            $this->appointmentService->decline($user, $appointmentId, $reason);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld(['reason' => $reason]);

            return Response::redirect('/technician/appointments/' . $appointmentId);
        }

        $this->session->flash('success', 'Встреча отклонена. Клиент увидит причину.');

        return Response::redirect('/technician/appointments/' . $appointmentId);
    }

    public function reschedule(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
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

        $this->session->flash('success', 'Перенос предложен клиенту.');

        return Response::redirect('/technician/appointments/' . $appointmentId);
    }

    public function complete(Request $request, array $params = []): Response
    {
        return $this->finish($params, 'completed', 'Встреча отмечена завершённой.');
    }

    public function noShow(Request $request, array $params = []): Response
    {
        return $this->finish($params, 'no_show', 'Отмечена неявка клиента.');
    }

    public function cancel(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $appointmentId = $this->appointmentId($params);
        $reason = $this->stringPost($request, 'reason');

        try {
            $this->appointmentService->cancel($user, $appointmentId, $reason);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);

            return Response::redirect('/technician/appointments/' . $appointmentId);
        }

        $this->session->flash('success', 'Встреча отменена.');

        return Response::redirect('/technician/appointments/' . $appointmentId);
    }

    /** @param array<string, string> $params */
    private function finish(array $params, string $outcome, string $message): Response
    {
        $user = $this->requireTechnician();
        $appointmentId = $this->appointmentId($params);

        try {
            $this->appointmentService->complete($user, $appointmentId, $outcome);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);

            return Response::redirect('/technician/appointments/' . $appointmentId);
        }

        $this->session->flash('success', $message);

        return Response::redirect('/technician/appointments/' . $appointmentId);
    }

    /** @return array<string, mixed> */
    private function requireTechnician(): array
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->abort(403, 'Authentication required');
        }
        if ((string) ($user['role'] ?? '') !== 'technician') {
            $this->abort(403, 'Technician access required');
        }

        return $user;
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
        $url = '/technician/appointments/' . $appointmentId;

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
