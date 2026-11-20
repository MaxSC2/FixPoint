<?php

declare(strict_types=1);

namespace FixPoint\Services;

use DateTimeImmutable;
use DateTimeZone;
use FixPoint\Core\Config;
use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\AppointmentRepository;
use FixPoint\Repositories\CalendarRepository;
use Throwable;

/**
 * Technician schedule rules and slot calculation. All instants are exchanged
 * with the database in UTC; the technician timezone is only used to render
 * local working hours and to turn a local date into the matching UTC slots.
 */
final class CalendarService
{
    public const MEETING_TYPES = ['device_dropoff', 'device_pickup', 'consultation'];

    public const STATUSES = [
        'pending',
        'confirmed',
        'declined',
        'reschedule_proposed',
        'cancelled',
        'completed',
        'no_show',
    ];

    public const BLOCK_KINDS = ['vacation', 'holiday', 'personal', 'other'];

    public const WEEKDAYS = [
        1 => 'Понедельник',
        2 => 'Вторник',
        3 => 'Среда',
        4 => 'Четверг',
        5 => 'Пятница',
        6 => 'Суббота',
        7 => 'Воскресенье',
    ];

    public const WEEKDAY_SHORT = [
        1 => 'Пн',
        2 => 'Вт',
        3 => 'Ср',
        4 => 'Чт',
        5 => 'Пт',
        6 => 'Сб',
        7 => 'Вс',
    ];

    public const MONTHS = [
        1 => 'января',
        2 => 'февраля',
        3 => 'марта',
        4 => 'апреля',
        5 => 'мая',
        6 => 'июня',
        7 => 'июля',
        8 => 'августа',
        9 => 'сентября',
        10 => 'октября',
        11 => 'ноября',
        12 => 'декабря',
    ];

    private const DEFAULT_SLOT_MINUTES = 60;

    private const DEFAULT_MIN_NOTICE_MINUTES = 120;

    private const DEFAULT_PENDING_HOLD_MINUTES = 1440;

    private const DEFAULT_CANCELLATION_CUTOFF_MINUTES = 120;

    /** @var list<array{weekday: int, start: string, end: string}> */
    private const DEFAULT_INTERVALS = [
        ['weekday' => 1, 'start' => '09:00', 'end' => '18:00'],
        ['weekday' => 2, 'start' => '09:00', 'end' => '18:00'],
        ['weekday' => 3, 'start' => '09:00', 'end' => '18:00'],
        ['weekday' => 4, 'start' => '09:00', 'end' => '18:00'],
        ['weekday' => 5, 'start' => '09:00', 'end' => '18:00'],
    ];

    public const STATUS_LABELS = [
        'pending' => 'Ожидает подтверждения',
        'confirmed' => 'Подтверждена',
        'declined' => 'Отклонена',
        'reschedule_proposed' => 'Предложен перенос',
        'cancelled' => 'Отменена',
        'completed' => 'Завершена',
        'no_show' => 'Неявка',
    ];

    public const STATUS_TONES = [
        'pending' => 'warning',
        'confirmed' => 'success',
        'declined' => 'danger',
        'reschedule_proposed' => 'info',
        'cancelled' => 'neutral',
        'completed' => 'success',
        'no_show' => 'danger',
    ];

    public const MEETING_TYPE_LABELS = [
        'device_dropoff' => 'Передача устройства',
        'device_pickup' => 'Получение устройства',
        'consultation' => 'Консультация',
    ];

    public const BLOCK_KIND_LABELS = [
        'vacation' => 'Отпуск',
        'holiday' => 'Выходной',
        'personal' => 'Личная занятость',
        'other' => 'Другое',
    ];

    public function __construct(
        private readonly CalendarRepository $calendar,
        private readonly AppointmentRepository $appointments,
        private readonly Config $config
    ) {
    }

    /** @return array<string, mixed> */
    public function defaultSettings(): array
    {
        return [
            'timezone' => (string) $this->config->get('app.timezone', 'UTC'),
            'slot_minutes' => self::DEFAULT_SLOT_MINUTES,
            'min_notice_minutes' => self::DEFAULT_MIN_NOTICE_MINUTES,
            'pending_hold_minutes' => self::DEFAULT_PENDING_HOLD_MINUTES,
            'cancellation_cutoff_minutes' => self::DEFAULT_CANCELLATION_CUTOFF_MINUTES,
            'is_configured' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function settings(int $technicianId): array
    {
        $row = $this->calendar->settings($technicianId);
        $settings = $this->defaultSettings();
        if ($row !== null) {
            $settings['timezone'] = (string) ($row['timezone'] ?? $settings['timezone']);
            $settings['slot_minutes'] = (int) ($row['slot_minutes'] ?? $settings['slot_minutes']);
            $settings['min_notice_minutes'] = (int) ($row['min_notice_minutes'] ?? $settings['min_notice_minutes']);
            $settings['pending_hold_minutes'] = (int) ($row['pending_hold_minutes'] ?? $settings['pending_hold_minutes']);
            $settings['cancellation_cutoff_minutes'] = (int) ($row['cancellation_cutoff_minutes']
                ?? $settings['cancellation_cutoff_minutes']);
            $settings['is_configured'] = (int) ($row['is_configured'] ?? 0) === 1;
        }

        if (!$this->isValidTimezone((string) $settings['timezone'])) {
            $settings['timezone'] = (string) $this->config->get('app.timezone', 'UTC');
        }
        $settings['slot_minutes'] = in_array((int) $settings['slot_minutes'], [30, 60, 90], true)
            ? (int) $settings['slot_minutes']
            : self::DEFAULT_SLOT_MINUTES;

        $weekly = $this->weeklyIntervals($technicianId, (bool) $settings['is_configured']);
        $settings['intervals'] = $weekly;

        return $settings;
    }

    /**
     * @return array<int, list<array{start: string, end: string}>>
     */
    public function weeklyIntervals(int $technicianId, ?bool $configured = null): array
    {
        $rows = $this->calendar->availability($technicianId);
        $settings = $this->calendar->settings($technicianId);
        $isConfigured = $configured ?? ((int) ($settings['is_configured'] ?? 0) === 1);

        $weekly = [];
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $weekly[$weekday] = [];
        }

        if ($rows === [] && !$isConfigured) {
            foreach (self::DEFAULT_INTERVALS as $interval) {
                $weekly[$interval['weekday']][] = [
                    'start' => $interval['start'],
                    'end' => $interval['end'],
                ];
            }

            return $weekly;
        }

        foreach ($rows as $row) {
            $weekday = (int) ($row['weekday'] ?? 0);
            if (!isset($weekly[$weekday])) {
                continue;
            }
            $weekly[$weekday][] = [
                'start' => substr((string) $row['start_time'], 0, 5),
                'end' => substr((string) $row['end_time'], 0, 5),
            ];
        }

        return $weekly;
    }

    /** @param array<string, mixed> $user */
    public function saveSettings(array $user, array $input): void
    {
        $technicianId = $this->requireTechnician($user);
        $errors = [];

        $timezone = trim((string) ($input['timezone'] ?? ''));
        if (!$this->isValidTimezone($timezone)) {
            $errors['timezone'] = 'Укажите корректный часовой пояс.';
            $timezone = (string) $this->config->get('app.timezone', 'UTC');
        }

        $slotMinutes = (int) ($input['slot_minutes'] ?? self::DEFAULT_SLOT_MINUTES);
        if (!in_array($slotMinutes, [30, 60, 90], true)) {
            $errors['slot_minutes'] = 'Длительность встречи должна быть 30, 60 или 90 минут.';
            $slotMinutes = self::DEFAULT_SLOT_MINUTES;
        }

        $minNotice = (int) ($input['min_notice_minutes'] ?? self::DEFAULT_MIN_NOTICE_MINUTES);
        if ($minNotice < 0 || $minNotice > 20160) {
            $errors['min_notice_minutes'] = 'Минимальное время до записи должно быть от 0 до 20160 минут.';
            $minNotice = self::DEFAULT_MIN_NOTICE_MINUTES;
        }

        $pendingHold = (int) ($input['pending_hold_minutes'] ?? self::DEFAULT_PENDING_HOLD_MINUTES);
        if ($pendingHold < 60 || $pendingHold > 43200) {
            $errors['pending_hold_minutes'] = 'Удержание слота должно быть от 1 часа до 30 дней.';
            $pendingHold = self::DEFAULT_PENDING_HOLD_MINUTES;
        }

        $cancellationCutoff = (int) ($input['cancellation_cutoff_minutes']
            ?? self::DEFAULT_CANCELLATION_CUTOFF_MINUTES);
        if ($cancellationCutoff < 0 || $cancellationCutoff > 10080) {
            $errors['cancellation_cutoff_minutes'] = 'Срок отмены должен быть от 0 до 10080 минут.';
            $cancellationCutoff = self::DEFAULT_CANCELLATION_CUTOFF_MINUTES;
        }

        $intervals = $this->normaliseIntervals($input['intervals'] ?? [], $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->calendar->saveSettings($technicianId, [
            'timezone' => $timezone,
            'slot_minutes' => $slotMinutes,
            'min_notice_minutes' => $minNotice,
            'pending_hold_minutes' => $pendingHold,
            'cancellation_cutoff_minutes' => $cancellationCutoff,
        ], $intervals);
    }

    /** @param array<string, mixed> $user
     *  @param array<string, mixed> $input */
    public function saveBlock(array $user, array $input): int
    {
        $technicianId = $this->requireTechnician($user);
        $errors = [];

        $kind = trim((string) ($input['kind'] ?? 'personal'));
        if (!in_array($kind, self::BLOCK_KINDS, true)) {
            $errors['kind'] = 'Выберите корректный тип блокировки.';
            $kind = 'personal';
        }

        $title = trim((string) ($input['title'] ?? ''));
        if (mb_strlen($title) < 3 || mb_strlen($title) > 160 || $this->hasControlCharacters($title)) {
            $errors['title'] = 'Название блокировки должно быть от 3 до 160 символов.';
        }

        $notes = trim((string) ($input['notes'] ?? ''));
        if (mb_strlen($notes) > 2000 || $this->hasControlCharacters($notes)) {
            $errors['notes'] = 'Заметка не должна превышать 2000 символов.';
        }

        $settings = $this->settings($technicianId);
        $timezone = $this->timezone((string) $settings['timezone']);
        $rawStartDate = trim((string) ($input['start_date'] ?? ''));
        $rawEndDate = trim((string) ($input['end_date'] ?? $rawStartDate));
        if (!$this->isValidDateString($rawStartDate)) {
            $errors['start_date'] = 'Укажите корректную дату начала.';
        }
        if (!$this->isValidDateString($rawEndDate)) {
            $errors['end_date'] = 'Укажите корректную дату окончания.';
        }
        $startDate = $this->normaliseDate($rawStartDate, $timezone);
        $endDate = $this->normaliseDate($rawEndDate, $timezone);

        $allDay = $this->truthy($input['all_day'] ?? false);
        $startTime = '00:00';
        $endTime = '00:00';

        if ($allDay) {
            try {
                $startLocal = new DateTimeImmutable($startDate . ' 00:00:00', $timezone);
                $endLocal = (new DateTimeImmutable($endDate . ' 00:00:00', $timezone))->modify('+1 day');
            } catch (Throwable) {
                $errors['start_date'] = 'Проверьте даты блокировки.';
                $startLocal = new DateTimeImmutable('now', $timezone);
                $endLocal = $startLocal->modify('+1 day');
            }
        } else {
            $startTime = $this->normaliseTime((string) ($input['start_time'] ?? ''));
            $endTime = $this->normaliseTime((string) ($input['end_time'] ?? ''));
            if ($startTime === '' || $endTime === '') {
                $errors['start_time'] = 'Укажите время начала и окончания блокировки.';
                $startTime = '09:00';
                $endTime = '10:00';
            }
            if ($endDate < $startDate) {
                $errors['end_date'] = 'Дата окончания не может быть раньше даты начала.';
                $endDate = $startDate;
            }
            if ($endDate === $startDate && $endTime <= $startTime) {
                $errors['end_time'] = 'Время окончания должно быть позже начала.';
            }

            try {
                $startLocal = new DateTimeImmutable($startDate . ' ' . $startTime . ':00', $timezone);
                $endLocal = new DateTimeImmutable($endDate . ' ' . $endTime . ':00', $timezone);
            } catch (Throwable) {
                $errors['start_date'] = 'Проверьте дату и время блокировки.';
                $startLocal = new DateTimeImmutable('now', $timezone);
                $endLocal = $startLocal->modify('+1 hour');
            }
        }

        if ($endLocal <= $startLocal) {
            $errors['end_date'] = 'Окончание блокировки должно быть позже начала.';
        }
        if ($startLocal->getTimestamp() > time() + 366 * 86400
            || $endLocal->getTimestamp() > time() + 367 * 86400
            || $startLocal->getTimestamp() < time() - 366 * 86400
        ) {
            $errors['start_date'] = 'Дата блокировки выходит за допустимый диапазон.';
        }
        if (($endLocal->getTimestamp() - $startLocal->getTimestamp()) > 90 * 86400) {
            $errors['end_date'] = 'Одна блокировка не может быть длиннее 90 дней.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $startsAtUtc = $this->toUtcString($startLocal);
        $endsAtUtc = $this->toUtcString($endLocal);

        $pdo = $this->calendar->connection();
        $pdo->beginTransaction();
        try {
            if (!$this->calendar->lockTechnician($technicianId)) {
                throw new HttpException(404, 'Technician not found');
            }

            $busy = $this->appointments->activeForTechnicianRange(
                $technicianId,
                $startsAtUtc,
                $endsAtUtc,
                $this->nowUtc(),
                null,
                true
            );
            foreach ($busy as $appointment) {
                $status = (string) ($appointment['status'] ?? '');
                $fromStatus = (string) ($appointment['reschedule_from_status'] ?? '');
                if ($status === 'confirmed'
                    || ($status === 'reschedule_proposed' && $fromStatus === 'confirmed')
                ) {
                    throw new ValidationException([
                        'block' => 'В выбранное время уже есть подтверждённая встреча. '
                            . 'Сначала отмените или перенесите её.',
                    ]);
                }
            }

            $blockId = $this->calendar->createBlock($technicianId, [
                'starts_at_utc' => $startsAtUtc,
                'ends_at_utc' => $endsAtUtc,
                'kind' => $kind,
                'title' => $title,
                'notes' => $notes === '' ? null : $notes,
                'is_all_day' => $allDay ? 1 : 0,
            ]);
            $pdo->commit();

            return $blockId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $user */
    public function deleteBlock(array $user, int $blockId): void
    {
        $technicianId = $this->requireTechnician($user);
        if ($blockId < 1) {
            throw new HttpException(404, 'Block not found');
        }

        $pdo = $this->calendar->connection();
        $pdo->beginTransaction();
        try {
            if (!$this->calendar->lockTechnician($technicianId)) {
                throw new HttpException(404, 'Technician not found');
            }
            if ($this->calendar->findBlock($blockId, $technicianId) === null) {
                throw new HttpException(404, 'Block not found');
            }
            $this->calendar->deleteBlock($blockId, $technicianId);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Technician-only projection. Block titles and notes are private and must
     * never be copied into a customer-facing payload.
     *
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function blocksForTechnician(array $user): array
    {
        $technicianId = $this->requireTechnician($user);
        $settings = $this->settings($technicianId);
        $timezone = $this->timezone((string) $settings['timezone']);

        return array_map(
            fn (array $block): array => $this->presentBlock($block, $timezone),
            $this->calendar->blocks($technicianId)
        );
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    public function presentBlock(array $block, DateTimeZone $timezone): array
    {
        $start = $this->utc((string) ($block['starts_at_utc'] ?? ''))->setTimezone($timezone);
        $end = $this->utc((string) ($block['ends_at_utc'] ?? ''))->setTimezone($timezone);
        $isAllDay = (int) ($block['is_all_day'] ?? 0) === 1;
        $kind = (string) ($block['kind'] ?? 'other');

        return array_merge($block, [
            'kind_label' => self::BLOCK_KIND_LABELS[$kind] ?? 'Блокировка',
            'local_start_date' => $start->format('d.m.Y'),
            'local_end_date' => $end->modify('-1 minute')->format('d.m.Y'),
            'local_time' => $isAllDay ? 'Весь день' : $start->format('H:i') . '–' . $end->format('H:i'),
            'starts_at_input' => $start->format('Y-m-d'),
            'ends_at_input' => $end->format('Y-m-d'),
        ]);
    }

    /**
     * Free slots for one local date. `$lockRows` is used inside booking and
     * confirmation transactions, after the technician row lock has serialized
     * competing operations.
     *
     * @return list<array<string, mixed>>
     */
    public function availableSlots(
        int $technicianId,
        string $date,
        ?int $excludeAppointmentId = null,
        bool $enforceMinNotice = true,
        bool $lockRows = false
    ): array {
        $settings = $this->settings($technicianId);
        $timezone = $this->timezone((string) $settings['timezone']);
        $date = $this->normaliseDate($date, $timezone);
        $weekday = (int) (new DateTimeImmutable($date . ' 12:00:00', $timezone))->format('N');

        $intervals = $settings['intervals'][$weekday] ?? [];
        if ($intervals === []) {
            return [];
        }

        $dayStart = new DateTimeImmutable($date . ' 00:00:00', $timezone);
        $dayEnd = $dayStart->modify('+1 day');
        $dayStartUtc = $this->toUtcString($dayStart);
        $dayEndUtc = $this->toUtcString($dayEnd);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowUtc = $this->toUtcString($now);
        $earliest = $enforceMinNotice
            ? $now->modify('+' . (int) $settings['min_notice_minutes'] . ' minutes')
            : $now;

        $busy = [];
        foreach ($this->appointments->activeForTechnicianRange(
            $technicianId,
            $dayStartUtc,
            $dayEndUtc,
            $nowUtc,
            $excludeAppointmentId,
            $lockRows
        ) as $appointment) {
            $busy[] = [
                (string) $appointment['starts_at_utc'],
                (string) $appointment['ends_at_utc'],
            ];
            if ((string) ($appointment['status'] ?? '') === 'reschedule_proposed'
                && ($appointment['proposed_starts_at_utc'] ?? null) !== null
            ) {
                $busy[] = [
                    (string) $appointment['proposed_starts_at_utc'],
                    (string) $appointment['proposed_ends_at_utc'],
                ];
            }
        }

        foreach ($this->calendar->blocks($technicianId, $dayStartUtc, $dayEndUtc, $lockRows) as $block) {
            $busy[] = [
                (string) $block['starts_at_utc'],
                (string) $block['ends_at_utc'],
            ];
        }

        $slotMinutes = (int) $settings['slot_minutes'];
        $slots = [];
        foreach ($intervals as $interval) {
            $intervalStart = new DateTimeImmutable($date . ' ' . $interval['start'] . ':00', $timezone);
            $intervalEnd = new DateTimeImmutable($date . ' ' . $interval['end'] . ':00', $timezone);

            for (
                $slotStart = $intervalStart;
                $slotStart->modify('+' . $slotMinutes . ' minutes') <= $intervalEnd;
                $slotStart = $slotStart->modify('+' . $slotMinutes . ' minutes')
            ) {
                $slotEnd = $slotStart->modify('+' . $slotMinutes . ' minutes');
                if ($slotStart < $earliest) {
                    continue;
                }

                $startUtc = $this->toUtcString($slotStart);
                $endUtc = $this->toUtcString($slotEnd);
                if ($this->overlapsAny($startUtc, $endUtc, $busy)) {
                    continue;
                }

                $key = $startUtc;
                if (isset($slots[$key])) {
                    continue;
                }
                $slots[$key] = [
                    'start_utc' => $startUtc,
                    'end_utc' => $endUtc,
                    'start_local' => $slotStart->format('H:i'),
                    'end_local' => $slotEnd->format('H:i'),
                    'value' => $startUtc,
                    'label' => $slotStart->format('H:i') . '–' . $slotEnd->format('H:i'),
                    'date' => $date,
                ];
            }
        }

        usort(
            $slots,
            static fn (array $left, array $right): int => strcmp(
                (string) $left['start_utc'],
                (string) $right['start_utc']
            )
        );

        return array_values($slots);
    }

    /**
     * Validate that a submitted slot is exactly one of the generated free
     * slots. This blocks forged end times, off-grid bookings and stale forms.
     *
     * @return array<string, mixed>|null
     */
    public function slotByStart(
        int $technicianId,
        string $startUtc,
        ?int $excludeAppointmentId = null,
        bool $enforceMinNotice = true,
        bool $lockRows = false
    ): ?array {
        $startUtc = trim($startUtc);
        $parsed = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $startUtc,
            new DateTimeZone('UTC')
        );
        if ($parsed === false || $parsed->format('Y-m-d H:i:s') !== $startUtc) {
            return null;
        }

        $settings = $this->settings($technicianId);
        $timezone = $this->timezone((string) $settings['timezone']);
        $date = $parsed->setTimezone($timezone)->format('Y-m-d');

        foreach ($this->availableSlots(
            $technicianId,
            $date,
            $excludeAppointmentId,
            $enforceMinNotice,
            $lockRows
        ) as $slot) {
            if ((string) $slot['start_utc'] === $startUtc) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Calendar page model for the technician workspace.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function calendarData(array $user, array $query): array
    {
        $technicianId = $this->requireTechnician($user);
        $settings = $this->settings($technicianId);
        $timezone = $this->timezone((string) $settings['timezone']);

        $view = (string) ($query['view'] ?? 'month');
        if (!in_array($view, ['day', 'week', 'month'], true)) {
            $view = 'month';
        }
        $date = $this->normaliseDate((string) ($query['date'] ?? ''), $timezone);
        $anchor = new DateTimeImmutable($date . ' 12:00:00', $timezone);

        if ($view === 'day') {
            $gridStart = $anchor;
            $gridEnd = $anchor;
        } elseif ($view === 'week') {
            $gridStart = $anchor->modify('-' . ((int) $anchor->format('N') - 1) . ' days');
            $gridEnd = $gridStart->modify('+6 days');
        } else {
            $first = $anchor->modify('first day of this month');
            $last = $anchor->modify('last day of this month');
            $gridStart = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
            $gridEnd = $last->modify('+' . (7 - (int) $last->format('N')) . ' days');
        }

        $rangeStart = new DateTimeImmutable($gridStart->format('Y-m-d') . ' 00:00:00', $timezone);
        $rangeEnd = (new DateTimeImmutable($gridEnd->format('Y-m-d') . ' 00:00:00', $timezone))->modify('+1 day');
        $rangeStartUtc = $this->toUtcString($rangeStart);
        $rangeEndUtc = $this->toUtcString($rangeEnd);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowUtc = $this->toUtcString($now);

        $appointments = $this->appointments->forTechnicianRange(
            $technicianId,
            $rangeStartUtc,
            $rangeEndUtc
        );
        $blocks = $this->calendar->blocks($technicianId, $rangeStartUtc, $rangeEndUtc);

        $eventsByDate = [];
        foreach ($appointments as $appointment) {
            $presented = $this->presentAppointment($appointment);
            $localStart = $this->utc($presented['starts_at_utc'])->setTimezone($timezone);
            $dateKey = $localStart->format('Y-m-d');
            $eventsByDate[$dateKey][] = $this->appointmentEvent($presented, false, $timezone);

            if ((string) $presented['status'] === 'reschedule_proposed'
                && $presented['proposed_starts_at_utc'] !== null
            ) {
                $proposedLocal = $this->utc((string) $presented['proposed_starts_at_utc'])
                    ->setTimezone($timezone);
                $eventsByDate[$proposedLocal->format('Y-m-d')][] = $this->appointmentEvent(
                    $presented,
                    true,
                    $timezone
                );
            }
        }

        foreach ($blocks as $block) {
            $blockStart = $this->utc((string) $block['starts_at_utc'])->setTimezone($timezone);
            $blockEnd = $this->utc((string) $block['ends_at_utc'])->setTimezone($timezone);
            $cursor = $blockStart->setTime(0, 0);
            $lastDate = $blockEnd->modify('-1 second')->format('Y-m-d');
            $safety = 0;
            while ($cursor->format('Y-m-d') <= $lastDate && $safety < 120) {
                $dateKey = $cursor->format('Y-m-d');
                if ($dateKey >= $gridStart->format('Y-m-d') && $dateKey <= $gridEnd->format('Y-m-d')) {
                    $eventsByDate[$dateKey][] = $this->blockEvent($block, $timezone);
                }
                $cursor = $cursor->modify('+1 day');
                $safety++;
            }
        }

        $days = [];
        $cursor = $gridStart;
        while ($cursor <= $gridEnd) {
            $dateKey = $cursor->format('Y-m-d');
            $weekday = (int) $cursor->format('N');
            $intervals = $settings['intervals'][$weekday] ?? [];
            $dayEvents = $eventsByDate[$dateKey] ?? [];
            usort($dayEvents, static function (array $left, array $right): int {
                if ($left['type'] !== $right['type']) {
                    return $left['type'] === 'block' ? -1 : 1;
                }

                return strcmp((string) $left['sort_time'], (string) $right['sort_time']);
            });

            $pending = 0;
            $confirmed = 0;
            foreach ($dayEvents as $event) {
                if ($event['type'] !== 'appointment' || $event['is_proposal']) {
                    continue;
                }
                if ($event['status'] === 'pending' && !$event['is_expired']) {
                    $pending++;
                }
                if ($event['status'] === 'confirmed') {
                    $confirmed++;
                }
            }

            $days[$dateKey] = [
                'date' => $dateKey,
                'day' => (int) $cursor->format('j'),
                'month' => (int) $cursor->format('n'),
                'weekday' => $weekday,
                'weekday_short' => self::WEEKDAY_SHORT[$weekday],
                'label' => (int) $cursor->format('j') . ' ' . self::MONTHS[(int) $cursor->format('n')],
                'full_label' => self::WEEKDAYS[$weekday] . ', ' . $cursor->format('d.m.Y'),
                'is_today' => $cursor->format('Y-m-d') === (new DateTimeImmutable('now', $timezone))->format('Y-m-d'),
                'is_current_month' => $cursor->format('Y-m') === $anchor->format('Y-m'),
                'is_working' => $intervals !== [],
                'intervals' => $intervals,
                'events' => array_values($dayEvents),
                'pending_count' => $pending,
                'confirmed_count' => $confirmed,
            ];
            $cursor = $cursor->modify('+1 day');
        }

        $weeks = [];
        if ($view === 'month') {
            $chunk = [];
            foreach ($days as $day) {
                $chunk[] = $day;
                if (count($chunk) === 7) {
                    $weeks[] = $chunk;
                    $chunk = [];
                }
            }
            if ($chunk !== []) {
                $weeks[] = $chunk;
            }
        }

        $activeRows = array_values(array_filter(
            $this->appointments->listForTechnician($technicianId),
            static fn (array $row): bool => in_array(
                (string) ($row['status'] ?? ''),
                ['pending', 'confirmed', 'reschedule_proposed'],
                true
            )
        ));
        $upcoming = [];
        foreach ($activeRows as $row) {
            $presented = $this->presentAppointment($row);
            if ($presented['is_expired']) {
                continue;
            }
            $starts = $this->utc((string) $presented['starts_at_utc']);
            if ($starts < $now) {
                continue;
            }
            $presented['permissions'] = [];
            $upcoming[] = $presented;
        }
        usort($upcoming, static fn (array $left, array $right): int => strcmp(
            (string) $left['starts_at_utc'],
            (string) $right['starts_at_utc']
        ));

        $rangeLabel = match ($view) {
            'day' => self::WEEKDAYS[(int) $anchor->format('N')] . ', ' . $anchor->format('d.m.Y'),
            'week' => $gridStart->format('d.m') . ' — ' . $gridEnd->format('d.m.Y'),
            default => self::MONTHS[(int) $anchor->format('n')] . ' ' . $anchor->format('Y'),
        };

        return [
            'settings' => $settings,
            'timezone' => $timezone->getName(),
            'view' => $view,
            'date' => $date,
            'anchor_date' => $anchor,
            'range_label' => $rangeLabel,
            'grid_start' => $gridStart->format('Y-m-d'),
            'grid_end' => $gridEnd->format('Y-m-d'),
            'days' => $days,
            'weeks' => $weeks,
            'upcoming' => array_slice($upcoming, 0, 30),
            'blocks' => $blocks,
            'pending_count' => count(array_filter(
                $upcoming,
                static fn (array $row): bool => $row['status'] === 'pending'
            )),
            'confirmed_count' => count(array_filter(
                $upcoming,
                static fn (array $row): bool => $row['status'] === 'confirmed'
            )),
            'prev_date' => $this->shiftDate($anchor, $view, -1)->format('Y-m-d'),
            'next_date' => $this->shiftDate($anchor, $view, 1)->format('Y-m-d'),
            'today_date' => (new DateTimeImmutable('now', $timezone))->format('Y-m-d'),
        ];
    }

    /** @param array<string, mixed> $user */
    public function upcomingForTechnician(array $user): array
    {
        $technicianId = $this->requireTechnician($user);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $rows = [];
        foreach ($this->appointments->listForTechnician($technicianId) as $row) {
            $presented = $this->presentAppointment($row);
            if (in_array($presented['status'], ['pending', 'confirmed', 'reschedule_proposed'], true)
                && !$presented['is_expired']
                && $this->utc((string) $presented['starts_at_utc']) >= $now
            ) {
                $rows[] = $presented;
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed> */
    public function presentAppointment(array $row): array
    {
        $timezoneName = (string) ($row['timezone'] ?? '');
        if (!$this->isValidTimezone($timezoneName)) {
            $timezoneName = (string) $this->config->get('app.timezone', 'UTC');
        }
        $timezone = new DateTimeZone($timezoneName);

        $startsUtc = (string) ($row['starts_at_utc'] ?? '');
        $endsUtc = (string) ($row['ends_at_utc'] ?? '');
        $startLocal = $this->utc($startsUtc)->setTimezone($timezone);
        $endLocal = $this->utc($endsUtc)->setTimezone($timezone);

        $proposedStartUtc = isset($row['proposed_starts_at_utc']) && $row['proposed_starts_at_utc'] !== null
            ? (string) $row['proposed_starts_at_utc']
            : null;
        $proposedEndUtc = isset($row['proposed_ends_at_utc']) && $row['proposed_ends_at_utc'] !== null
            ? (string) $row['proposed_ends_at_utc']
            : null;
        $proposedLocalStart = $proposedStartUtc !== null
            ? $this->utc($proposedStartUtc)->setTimezone($timezone)
            : null;
        $proposedLocalEnd = $proposedEndUtc !== null
            ? $this->utc($proposedEndUtc)->setTimezone($timezone)
            : null;

        $status = (string) ($row['status'] ?? 'pending');
        $expiresAt = isset($row['pending_expires_at_utc']) && $row['pending_expires_at_utc'] !== null
            ? (string) $row['pending_expires_at_utc']
            : null;
        $isExpired = in_array($status, ['pending', 'reschedule_proposed'], true)
            && (string) ($row['reschedule_from_status'] ?? '') !== 'confirmed'
            && $expiresAt !== null
            && $this->utc($expiresAt) <= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $deviceTitle = trim(implode(' ', array_filter([
            (string) ($row['device_brand'] ?? ''),
            (string) ($row['device_model'] ?? ''),
        ])));
        if ($deviceTitle === '') {
            $deviceTitle = (string) ($row['device_type'] ?? 'Устройство');
        }

        return array_merge($row, [
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? $status,
            'status_tone' => self::STATUS_TONES[$status] ?? 'neutral',
            'status_marker' => $status,
            'meeting_type' => (string) ($row['meeting_type'] ?? 'consultation'),
            'meeting_type_label' => self::MEETING_TYPE_LABELS[(string) ($row['meeting_type'] ?? '')]
                ?? 'Встреча',
            'starts_at_utc' => $startsUtc,
            'ends_at_utc' => $endsUtc,
            'local_date' => $startLocal->format('d.m.Y'),
            'local_date_iso' => $startLocal->format('Y-m-d'),
            'local_time' => $startLocal->format('H:i') . '–' . $endLocal->format('H:i'),
            'local_starts_at' => $startLocal->format('d.m.Y H:i'),
            'local_ends_at' => $endLocal->format('d.m.Y H:i'),
            'proposed_starts_at_utc' => $proposedStartUtc,
            'proposed_ends_at_utc' => $proposedEndUtc,
            'proposed_local_date' => $proposedLocalStart?->format('d.m.Y'),
            'proposed_local_time' => $proposedLocalStart !== null && $proposedLocalEnd !== null
                ? $proposedLocalStart->format('H:i') . '–' . $proposedLocalEnd->format('H:i')
                : null,
            'proposed_local_starts_at' => $proposedLocalStart?->format('d.m.Y H:i'),
            'is_expired' => $isExpired,
            'device_title' => $deviceTitle,
            'request_number' => (string) ($row['public_number'] ?? ''),
            'customer_name' => (string) ($row['customer_name'] ?? ''),
            'technician_name' => (string) ($row['technician_name'] ?? ''),
            'proposed_by_name' => (string) ($row['proposed_by_name'] ?? ''),
        ]);
    }

    public function timezone(string $name): DateTimeZone
    {
        try {
            return new DateTimeZone($name);
        } catch (Throwable) {
            return new DateTimeZone((string) $this->config->get('app.timezone', 'UTC'));
        }
    }

    public function normaliseDate(string $date, DateTimeZone $timezone): string
    {
        $date = trim($date);
        if ($this->isValidDateString($date)) {
            return $date;
        }

        return (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
    }

    public function nowUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    public function isValidDateString(string $date): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    public function toUtcString(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** @param array<string, mixed> $user */
    public function requireTechnician(array $user): int
    {
        $id = (int) ($user['id'] ?? 0);
        if (($user['role'] ?? '') !== 'technician' || $id < 1) {
            throw new HttpException(403, 'Technician access required');
        }

        return $id;
    }

    public function isValidTimezone(string $name): bool
    {
        if ($name === '' || strlen($name) > 64) {
            return false;
        }

        try {
            new DateTimeZone($name);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $presented
     *  @return array<string, mixed> */
    private function appointmentEvent(array $presented, bool $proposal, DateTimeZone $timezone): array
    {
        $startValue = $proposal
            ? (string) ($presented['proposed_starts_at_utc'] ?? '')
            : (string) $presented['starts_at_utc'];
        $endValue = $proposal
            ? (string) ($presented['proposed_ends_at_utc'] ?? '')
            : (string) $presented['ends_at_utc'];
        $start = $this->utc($startValue)->setTimezone($timezone);
        $end = $this->utc($endValue)->setTimezone($timezone);

        return [
            'type' => 'appointment',
            'id' => (int) $presented['id'],
            'status' => $proposal ? 'reschedule_proposed' : (string) $presented['status'],
            'status_label' => $proposal ? 'Предложенный перенос' : (string) $presented['status_label'],
            'marker' => $proposal ? 'reschedule_proposed' : (string) $presented['status_marker'],
            'title' => $proposal
                ? 'Перенос: ' . (string) $presented['meeting_type_label']
                : (string) $presented['meeting_type_label'],
            'subtitle' => (string) $presented['request_number'],
            'time_label' => $start->format('H:i') . '–' . $end->format('H:i'),
            'sort_time' => $start->format('H:i'),
            'href' => '/technician/appointments/' . (int) $presented['id'],
            'is_proposal' => $proposal,
            'is_expired' => (bool) $presented['is_expired'],
            'customer_name' => (string) $presented['customer_name'],
        ];
    }

    /** @param array<string, mixed> $block
     *  @return array<string, mixed> */
    private function blockEvent(array $block, DateTimeZone $timezone): array
    {
        $start = $this->utc((string) $block['starts_at_utc'])->setTimezone($timezone);
        $end = $this->utc((string) $block['ends_at_utc'])->setTimezone($timezone);
        $allDay = (int) ($block['is_all_day'] ?? 0) === 1;
        $kind = (string) ($block['kind'] ?? 'personal');

        return [
            'type' => 'block',
            'id' => (int) $block['id'],
            'kind' => $kind,
            'marker' => $kind,
            'title' => self::BLOCK_KIND_LABELS[$kind] ?? 'Блокировка',
            'subtitle' => (string) ($block['title'] ?? ''),
            'time_label' => $allDay
                ? 'Весь день'
                : $start->format('H:i') . '–' . $end->format('H:i'),
            'sort_time' => $allDay ? '00:00' : $start->format('H:i'),
            'href' => null,
            'is_proposal' => false,
            'is_expired' => false,
            'customer_name' => '',
        ];
    }

    /** @param list<array{0: string, 1: string}> $busy */
    private function overlapsAny(string $startUtc, string $endUtc, array $busy): bool
    {
        foreach ($busy as $range) {
            if ($startUtc < $range[1] && $endUtc > $range[0]) {
                return true;
            }
        }

        return false;
    }

    public function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function shiftDate(DateTimeImmutable $anchor, string $view, int $direction): DateTimeImmutable
    {
        return match ($view) {
            'day' => $anchor->modify(($direction > 0 ? '+' : '-') . '1 day'),
            'week' => $anchor->modify(($direction > 0 ? '+' : '-') . '7 days'),
            default => $anchor->modify(($direction > 0 ? '+' : '-') . '1 month'),
        };
    }

    /**
     * @param mixed $raw
     * @param array<string, string> $errors
     * @return list<array{weekday: int, start_time: string, end_time: string}>
     */
    private function normaliseIntervals(mixed $raw, array &$errors): array
    {
        if (!is_array($raw)) {
            $errors['intervals'] = 'Не удалось прочитать расписание.';

            return [];
        }

        $intervals = [];
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $rows = $raw[$weekday] ?? $raw[(string) $weekday] ?? [];
            if (!is_array($rows)) {
                continue;
            }
            if (isset($rows['start']) || isset($rows['end']) || isset($rows['start_time'])) {
                $rows = [$rows];
            }

            $normalised = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $start = $this->normaliseTime((string) ($row['start'] ?? $row['start_time'] ?? ''));
                $end = $this->normaliseTime((string) ($row['end'] ?? $row['end_time'] ?? ''));
                if ($start === '' && $end === '') {
                    continue;
                }
                if ($start === '' || $end === '') {
                    $errors['intervals'] = 'В каждом интервале нужно указать начало и окончание.';
                    continue;
                }
                if ($start >= $end) {
                    $errors['intervals'] = 'Начало интервала должно быть раньше окончания.';
                    continue;
                }
                $normalised[] = ['start' => $start, 'end' => $end];
            }

            usort($normalised, static fn (array $left, array $right): int => strcmp(
                (string) $left['start'],
                (string) $right['start']
            ));
            $previousEnd = '';
            foreach ($normalised as $row) {
                if ($previousEnd !== '' && $row['start'] < $previousEnd) {
                    $errors['intervals'] = 'Интервалы одного дня не должны пересекаться.';
                }
                $previousEnd = $row['end'];
                $intervals[] = [
                    'weekday' => $weekday,
                    'start_time' => $row['start'],
                    'end_time' => $row['end'],
                ];
            }
            if (count($normalised) > 6) {
                $errors['intervals'] = 'На один день можно задать не больше шести интервалов.';
            }
        }

        return $intervals;
    }

    private function normaliseTime(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/', $value) !== 1) {
            return '';
        }

        return substr($value, 0, 5);
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }

    private function hasControlCharacters(string $value): bool
    {
        return preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', $value) === 1;
    }
}
