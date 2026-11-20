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
use FixPoint\Services\CalendarService;

/**
 * Technician availability workspace: calendar views, weekly schedule, private
 * blocks. Every action scopes data to the authenticated technician.
 */
final class TechnicianCalendarController extends Controller
{
    private const TIMEZONES = [
        'UTC',
        'Asia/Almaty',
        'Asia/Aqtobe',
        'Asia/Atyrau',
        'Asia/Oral',
        'Asia/Qostanay',
        'Asia/Qyzylorda',
        'Asia/Tashkent',
        'Asia/Bishkek',
        'Asia/Dushanbe',
        'Asia/Tbilisi',
        'Asia/Yerevan',
        'Asia/Dubai',
        'Europe/Moscow',
        'Asia/Yekaterinburg',
        'Asia/Novosibirsk',
        'Europe/Kyiv',
        'Europe/Berlin',
        'Europe/London',
        'America/New_York',
        'America/Los_Angeles',
    ];

    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly CalendarService $calendarService,
        private readonly AppointmentService $appointmentService
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $this->appointmentService->expirePendingHolds();
        $calendar = $this->calendarService->calendarData($user, $request->allQuery());

        return $this->renderPage('pages/technician/calendar/index', [
            'pageTitle' => 'Календарь мастера — ' . $this->config->get('app.name'),
            'activeNav' => 'technician',
            'calendar' => $calendar,
        ]);
    }

    public function settings(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $settings = $this->calendarService->settings((int) $user['id']);

        return $this->renderPage('pages/technician/calendar/settings', [
            'pageTitle' => 'Рабочее расписание — ' . $this->config->get('app.name'),
            'activeNav' => 'technician',
            'settings' => $settings,
            'timezones' => $this->timezoneOptions((string) $settings['timezone']),
            'weekdays' => CalendarService::WEEKDAYS,
            'weekdayShort' => CalendarService::WEEKDAY_SHORT,
        ]);
    }

    public function updateSettings(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $input = [
            'timezone' => $this->stringPost($request, 'timezone'),
            'slot_minutes' => $this->stringPost($request, 'slot_minutes'),
            'min_notice_minutes' => $this->stringPost($request, 'min_notice_minutes'),
            'pending_hold_minutes' => $this->stringPost($request, 'pending_hold_minutes'),
            'cancellation_cutoff_minutes' => $this->stringPost($request, 'cancellation_cutoff_minutes'),
            'intervals' => $request->post('intervals', []),
        ];
        if (!is_array($input['intervals'])) {
            $input['intervals'] = [];
        }

        try {
            $this->calendarService->saveSettings($user, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld($input);

            return Response::redirect('/technician/calendar/settings');
        }

        $this->session->flash('success', 'Рабочее расписание сохранено.');

        return Response::redirect('/technician/calendar');
    }

    public function blocks(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $settings = $this->calendarService->settings((int) $user['id']);

        return $this->renderPage('pages/technician/calendar/blocks', [
            'pageTitle' => 'Блокировки календаря — ' . $this->config->get('app.name'),
            'activeNav' => 'technician',
            'blocks' => $this->calendarService->blocksForTechnician($user),
            'blockKinds' => CalendarService::BLOCK_KIND_LABELS,
            'timezone' => (string) $settings['timezone'],
        ]);
    }

    public function storeBlock(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $input = [
            'kind' => $this->stringPost($request, 'kind'),
            'title' => $this->stringPost($request, 'title'),
            'notes' => $this->stringPost($request, 'notes'),
            'start_date' => $this->stringPost($request, 'start_date'),
            'end_date' => $this->stringPost($request, 'end_date'),
            'start_time' => $this->stringPost($request, 'start_time'),
            'end_time' => $this->stringPost($request, 'end_time'),
            'all_day' => $this->stringPost($request, 'all_day'),
        ];

        try {
            $this->calendarService->saveBlock($user, $input);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);
            $this->session->flashOld($input);

            return Response::redirect('/technician/calendar/blocks');
        }

        $this->session->flash('success', 'Блокировка добавлена. Клиенты увидят только занятое время.');

        return Response::redirect('/technician/calendar/blocks');
    }

    public function deleteBlock(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $blockId = (int) ($params['id'] ?? 0);

        try {
            $this->calendarService->deleteBlock($user, $blockId);
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);

            return Response::redirect('/technician/calendar/blocks');
        }

        $this->session->flash('success', 'Блокировка удалена.');

        return Response::redirect('/technician/calendar/blocks');
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

    /**
     * @return array<string, string>
     */
    private function timezoneOptions(string $current): array
    {
        $zones = self::TIMEZONES;
        if ($current !== '' && !in_array($current, $zones, true)) {
            $zones[] = $current;
        }
        $options = [];
        foreach ($zones as $zone) {
            $options[$zone] = $zone;
        }

        return $options;
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
