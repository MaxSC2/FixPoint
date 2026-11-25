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
 * Appointment lifecycle and booking rules.
 *
 * Every mutation first locks the technician row (`users` FOR UPDATE). That row
 * is the per-technician mutex: booking, confirmation, reschedule, cancellation
 * and block creation are serialized even when two PHP processes run at once.
 * The technician timezone is only used for display and local-slot generation;
 * all persisted instants are UTC.
 */
final class AppointmentService
{
    public const EVENT_LABELS = [
        'created' => 'Заявка на встречу создана',
        'confirmed' => 'Встреча подтверждена',
        'declined' => 'Встреча отклонена',
        'reschedule_proposed' => 'Предложен перенос',
        'reschedule_accepted' => 'Перенос принят',
        'reschedule_declined' => 'Перенос отклонён',
        'cancelled' => 'Встреча отменена',
        'completed' => 'Встреча завершена',
        'no_show' => 'Клиент не пришёл',
        'expired' => 'Срок удержания истёк',
    ];

    private const BLOCKED_REQUEST_STATUSES = ['cancelled', 'rejected'];

    private const COMMENT_LIMIT = 2000;

    private const REASON_LIMIT = 1000;

    public function __construct(
        private readonly CalendarService $calendar,
        private readonly CalendarRepository $calendarRepository,
        private readonly AppointmentRepository $appointments,
        private readonly Config $config
    ) {
    }

    /**
     * Everything the customer booking page needs.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function bookingContext(array $user, int $requestId, array $query = []): array
    {
        $customerId = $this->requireCustomer($user);
        $this->expirePendingHolds();

        $request = $this->appointments->findRequest($requestId);
        if ($request === null || (int) $request['customer_id'] !== $customerId) {
            throw new HttpException(404, 'Request not found');
        }

        $technicianId = (int) ($request['technician_id'] ?? 0);
        $technician = $technicianId > 0 ? $this->calendarRepository->findTechnician($technicianId) : null;
        $settings = $technicianId > 0
            ? $this->calendar->settings($technicianId)
            : $this->calendar->defaultSettings();
        $timezone = $this->calendar->timezone((string) $settings['timezone']);

        $blockedReason = null;
        if (in_array((string) $request['status'], self::BLOCKED_REQUEST_STATUSES, true)) {
            $blockedReason = 'По этой заявке нельзя записаться: обращение закрыто.';
        } elseif ($technician === null || (int) ($technician['is_active'] ?? 0) !== 1) {
            $blockedReason = 'Мастер пока не назначен или недоступен. Запись появится после назначения.';
        }

        $today = new DateTimeImmutable('now', $timezone);
        $requestedDate = trim((string) ($query['date'] ?? ''));
        $requestedDate = $this->calendar->isValidDateString($requestedDate) ? $requestedDate : '';
        $todayDate = $today->format('Y-m-d');

        $days = [];
        $slotsByDate = [];
        if ($blockedReason === null) {
            for ($offset = 0; $offset < 14; $offset++) {
                $day = $today->modify('+' . $offset . ' days');
                $date = $day->format('Y-m-d');
                $daySlots = $this->calendar->availableSlots($technicianId, $date);
                $slotsByDate[$date] = $daySlots;
                $days[] = [
                    'date' => $date,
                    'day' => (int) $day->format('j'),
                    'month' => (int) $day->format('n'),
                    'month_label' => CalendarService::MONTHS[(int) $day->format('n')],
                    'weekday_short' => CalendarService::WEEKDAY_SHORT[(int) $day->format('N')],
                    'weekday' => (int) $day->format('N'),
                    'slots_count' => count($daySlots),
                    'is_today' => $date === $todayDate,
                    'is_selected' => false,
                ];
            }
        }

        $selectedDate = $requestedDate;
        if ($selectedDate === '') {
            foreach ($days as $day) {
                if ($day['slots_count'] > 0) {
                    $selectedDate = (string) $day['date'];
                    break;
                }
            }
        }
        if ($selectedDate === '') {
            $selectedDate = $todayDate;
        }
        foreach ($days as $index => $day) {
            $days[$index]['is_selected'] = (string) $day['date'] === $selectedDate;
        }

        $slots = $slotsByDate[$selectedDate]
            ?? ($blockedReason === null
                ? $this->calendar->availableSlots($technicianId, $selectedDate)
                : []);

        $existing = [];
        foreach ($this->appointments->listForRequest($requestId) as $row) {
            $existing[] = $this->presentForCustomer($row);
        }

        return [
            'request' => $request,
            'technician' => $technician,
            'settings' => $settings,
            'timezone' => $timezone->getName(),
            'meeting_types' => CalendarService::MEETING_TYPE_LABELS,
            'days' => $days,
            'date' => $selectedDate,
            'slots' => $slots,
            'appointments' => $existing,
            'blocked_reason' => $blockedReason,
            'can_book' => $blockedReason === null,
        ];
    }

    /**
     * Create a pending hold. The start must be an exact generated free slot;
     * client-supplied end times are never trusted.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     */
    public function book(array $user, int $requestId, array $input): int
    {
        $customerId = $this->requireCustomer($user);
        $meetingType = trim((string) ($input['meeting_type'] ?? ''));
        $startUtc = trim((string) ($input['start_utc'] ?? ''));
        $comment = trim((string) ($input['customer_comment'] ?? ''));
        $errors = [];

        if (!in_array($meetingType, CalendarService::MEETING_TYPES, true)) {
            $errors['meeting_type'] = 'Выберите цель встречи.';
        }
        if ($startUtc === '') {
            $errors['start_utc'] = 'Выберите свободное время.';
        }
        if (mb_strlen($comment) > self::COMMENT_LIMIT || $this->hasControlCharacters($comment)) {
            $errors['customer_comment'] = 'Комментарий не должен превышать 2000 символов.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $request = $this->appointments->findRequest($requestId);
        if ($request === null || (int) $request['customer_id'] !== $customerId) {
            throw new HttpException(404, 'Request not found');
        }
        $technicianId = (int) ($request['technician_id'] ?? 0);
        if ($technicianId < 1) {
            throw new ValidationException(['general' => 'К заявке ещё не назначен мастер.']);
        }

        return $this->transactional(function () use (
            $requestId,
            $customerId,
            $technicianId,
            $meetingType,
            $startUtc,
            $comment
        ): int {
            if (!$this->calendarRepository->lockTechnician($technicianId)) {
                throw new ValidationException(['general' => 'Мастер недоступен для записи.']);
            }

            $locked = $this->appointments->lockRequest($requestId);
            if ($locked === null || (int) $locked['customer_id'] !== $customerId) {
                throw new HttpException(404, 'Request not found');
            }
            if ((int) ($locked['technician_id'] ?? 0) !== $technicianId) {
                throw new ValidationException([
                    'general' => 'Мастер был переназначен. Обновите страницу и выберите время снова.',
                ]);
            }
            if (in_array((string) $locked['status'], self::BLOCKED_REQUEST_STATUSES, true)) {
                throw new ValidationException(['general' => 'По этой заявке нельзя записаться: обращение закрыто.']);
            }

            $nowUtc = $this->calendar->nowUtc();
            $slot = $this->calendar->slotByStart($technicianId, $startUtc, null, true, true);
            if ($slot === null) {
                throw new ValidationException([
                    'start_utc' => 'Это время уже занято или недоступно. Выберите другой слот.',
                ]);
            }

            if ($this->appointments->activeCountForRequestType($requestId, $meetingType, $nowUtc) > 0) {
                throw new ValidationException([
                    'meeting_type' => 'По этой заявке уже есть активная встреча с такой целью.',
                ]);
            }

            $settings = $this->calendar->settings($technicianId);
            $expiresAt = (new DateTimeImmutable($nowUtc, new DateTimeZone('UTC')))
                ->modify('+' . (int) $settings['pending_hold_minutes'] . ' minutes')
                ->format('Y-m-d H:i:s');

            $appointmentId = $this->appointments->create([
                'request_id' => $requestId,
                'customer_id' => $customerId,
                'technician_id' => $technicianId,
                'meeting_type' => $meetingType,
                'status' => 'pending',
                'starts_at_utc' => (string) $slot['start_utc'],
                'ends_at_utc' => (string) $slot['end_utc'],
                'timezone' => (string) $settings['timezone'],
                'purpose' => CalendarService::MEETING_TYPE_LABELS[$meetingType],
                'customer_comment' => $comment === '' ? null : $comment,
                'pending_expires_at_utc' => $expiresAt,
                'created_by' => $customerId,
            ]);

            $this->recordEvent($appointmentId, $customerId, 'created', null, 'pending', null, null, (string) $slot['start_utc'], (string) $slot['end_utc'], null);

            return $appointmentId;
        });
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function detail(array $user, int $appointmentId, array $query = []): array
    {
        $this->requireUser($user);
        $this->expirePendingHolds();

        $row = $this->appointments->findById($appointmentId);
        if ($row === null) {
            throw new HttpException(404, 'Appointment not found');
        }
        $this->assertCanView($row, $user);

        $isCustomer = (string) ($row['customer_id'] ?? '') === (string) ($user['id'] ?? '');
        $settings = $this->calendar->settings((int) $row['technician_id']);
        $presented = $isCustomer
            ? $this->presentForCustomer($row)
            : $this->calendar->presentAppointment($row);
        $presented['permissions'] = $this->permissions($row, $user, $settings);

        $timezone = $this->calendar->timezone((string) $row['timezone']);
        $events = [];
        foreach ($this->appointments->events($appointmentId) as $event) {
            $events[] = $this->presentEvent($event, $timezone, $isCustomer);
        }

        $rescheduleSlots = [];
        $rescheduleDate = '';
        if (!empty($presented['permissions']['can_propose'])) {
            $rescheduleDate = $this->calendar->normaliseDate(
                (string) ($query['date'] ?? ''),
                $timezone
            );
            $rescheduleSlots = $this->calendar->availableSlots(
                (int) $row['technician_id'],
                $rescheduleDate,
                (int) $row['id']
            );
        }

        return [
            'appointment' => $presented,
            'events' => $events,
            'permissions' => $presented['permissions'],
            'is_customer' => $isCustomer,
            'reschedule_date' => $rescheduleDate,
            'reschedule_slots' => $rescheduleSlots,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{upcoming: list<array<string, mixed>>, history: list<array<string, mixed>>}
     */
    public function listForCustomer(array $user): array
    {
        $customerId = $this->requireCustomer($user);
        $this->expirePendingHolds();

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $upcoming = [];
        $history = [];
        foreach ($this->appointments->listForCustomer($customerId) as $row) {
            $presented = $this->presentForCustomer($row);
            $isActive = in_array($presented['status'], ['pending', 'confirmed', 'reschedule_proposed'], true)
                && !$presented['is_expired'];
            if ($isActive && $this->calendar->utc((string) $presented['starts_at_utc']) >= $now) {
                $upcoming[] = $presented;
            } else {
                $history[] = $presented;
            }
        }

        return ['upcoming' => $upcoming, 'history' => $history];
    }

    /** @param array<string, mixed> $user */
    public function confirm(array $user, int $appointmentId): void
    {
        $technicianId = $this->requireTechnician($user);

        $this->transactional(function () use ($technicianId, $appointmentId): void {
            if (!$this->calendarRepository->lockTechnician($technicianId)) {
                throw new HttpException(404, 'Technician not found');
            }
            $row = $this->appointments->findRawForUpdate($appointmentId);
            if ($row === null || (int) $row['technician_id'] !== $technicianId) {
                throw new HttpException(404, 'Appointment not found');
            }

            $status = (string) $row['status'];
            $nowUtc = $this->calendar->nowUtc();
            if ($status === 'pending') {
                $this->assertHoldActive($row, $nowUtc);
                $startUtc = (string) $row['starts_at_utc'];
            } elseif ($status === 'reschedule_proposed') {
                if ((int) ($row['proposed_by'] ?? 0) === $technicianId) {
                    throw new ValidationException([
                        'general' => 'Вы уже предложили перенос. Дождитесь ответа клиента.',
                    ]);
                }
                if ((string) ($row['reschedule_from_status'] ?? 'confirmed') === 'pending') {
                    $this->assertHoldActive($row, $nowUtc);
                }
                $startUtc = (string) ($row['proposed_starts_at_utc'] ?? '');
            } else {
                throw new ValidationException(['general' => 'Эту встречу нельзя подтвердить в текущем статусе.']);
            }

            $slot = $this->calendar->slotByStart($technicianId, $startUtc, $appointmentId, true, true);
            if ($slot === null) {
                throw new ValidationException([
                    'general' => 'Время больше не свободно или вышло за пределы расписания. '
                        . 'Предложите клиенту другой слот.',
                ]);
            }

            $this->appointments->update($appointmentId, [
                'status' => 'confirmed',
                'starts_at_utc' => (string) $slot['start_utc'],
                'ends_at_utc' => (string) $slot['end_utc'],
                'proposed_starts_at_utc' => null,
                'proposed_ends_at_utc' => null,
                'proposed_by' => null,
                'reschedule_from_status' => null,
                'pending_expires_at_utc' => null,
                'confirmed_at_utc' => $nowUtc,
            ]);

            $this->recordEvent(
                $appointmentId,
                $technicianId,
                'confirmed',
                $status,
                'confirmed',
                (string) $row['starts_at_utc'],
                (string) $row['ends_at_utc'],
                (string) $slot['start_utc'],
                (string) $slot['end_utc'],
                null
            );
        });
    }

    /** @param array<string, mixed> $user */
    public function decline(array $user, int $appointmentId, string $reason): void
    {
        $technicianId = $this->requireTechnician($user);
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > self::REASON_LIMIT || $this->hasControlCharacters($reason)) {
            throw new ValidationException(['reason' => 'Укажите причину отказа (от 3 до 1000 символов).']);
        }

        $this->transactional(function () use ($technicianId, $appointmentId, $reason): void {
            if (!$this->calendarRepository->lockTechnician($technicianId)) {
                throw new HttpException(404, 'Technician not found');
            }
            $row = $this->appointments->findRawForUpdate($appointmentId);
            if ($row === null || (int) $row['technician_id'] !== $technicianId) {
                throw new HttpException(404, 'Appointment not found');
            }
            if (!in_array((string) $row['status'], ['pending', 'reschedule_proposed'], true)) {
                throw new ValidationException(['general' => 'Эту встречу нельзя отклонить в текущем статусе.']);
            }
            if ((string) $row['status'] === 'reschedule_proposed'
                && (int) ($row['proposed_by'] ?? 0) === $technicianId
            ) {
                throw new ValidationException([
                    'general' => 'Перенос предложили вы. Дождитесь ответа клиента.',
                ]);
            }

            $nowUtc = $this->calendar->nowUtc();
            $this->appointments->update($appointmentId, [
                'status' => 'declined',
                'declined_at_utc' => $nowUtc,
                'proposed_starts_at_utc' => null,
                'proposed_ends_at_utc' => null,
                'proposed_by' => null,
                'pending_expires_at_utc' => null,
            ]);
            $this->recordEvent(
                $appointmentId,
                $technicianId,
                'declined',
                (string) $row['status'],
                'declined',
                (string) $row['starts_at_utc'],
                (string) $row['ends_at_utc'],
                null,
                null,
                $reason
            );
        });
    }

    /** @param array<string, mixed> $user */
    public function proposeReschedule(array $user, int $appointmentId, string $startUtc, string $note = ''): void
    {
        $this->requireUser($user);
        $actorId = (int) $user['id'];
        $startUtc = trim($startUtc);
        $note = trim($note);
        if ($startUtc === '') {
            throw new ValidationException(['start_utc' => 'Выберите новый свободный слот.']);
        }
        if (mb_strlen($note) > self::REASON_LIMIT || $this->hasControlCharacters($note)) {
            throw new ValidationException(['note' => 'Комментарий не должен превышать 1000 символов.']);
        }

        $this->transactional(function () use ($appointmentId, $actorId, $startUtc, $note): void {
            $initial = $this->appointments->findById($appointmentId);
            if ($initial === null) {
                throw new HttpException(404, 'Appointment not found');
            }
            $technicianId = (int) $initial['technician_id'];
            if (!$this->calendarRepository->lockTechnician($technicianId)) {
                throw new HttpException(404, 'Technician not found');
            }
            $row = $this->appointments->findRawForUpdate($appointmentId);
            if ($row === null) {
                throw new HttpException(404, 'Appointment not found');
            }
            $this->assertParticipant($row, $actorId);

            $status = (string) $row['status'];
            if (!in_array($status, ['pending', 'confirmed'], true)) {
                throw new ValidationException([
                    'general' => 'Перенос можно предложить только для ожидающей или подтверждённой встречи.',
                ]);
            }
            if ($this->calendar->utc((string) $row['starts_at_utc']) <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
                throw new ValidationException(['general' => 'Встреча уже началась или прошла.']);
            }

            $slot = $this->calendar->slotByStart($technicianId, $startUtc, $appointmentId, true, true);
            if ($slot === null) {
                throw new ValidationException(['start_utc' => 'Это время недоступно. Выберите другой слот.']);
            }

            $this->appointments->update($appointmentId, [
                'status' => 'reschedule_proposed',
                'reschedule_from_status' => $status,
                'proposed_starts_at_utc' => (string) $slot['start_utc'],
                'proposed_ends_at_utc' => (string) $slot['end_utc'],
                'proposed_by' => $actorId,
            ]);
            $this->recordEvent(
                $appointmentId,
                $actorId,
                'reschedule_proposed',
                $status,
                'reschedule_proposed',
                (string) $row['starts_at_utc'],
                (string) $row['ends_at_utc'],
                (string) $slot['start_utc'],
                (string) $slot['end_utc'],
                $note === '' ? null : $note
            );
        });
    }

    /**
     * Customer answer to a technician proposal. "accept" is the only way a
     * customer moves an appointment to confirmed; the slot is revalidated.
     *
     * @param array<string, mixed> $user
     */
    public function respondToProposal(array $user, int $appointmentId, string $decision, string $reason = ''): void
    {
        $customerId = $this->requireCustomer($user);
        if (!in_array($decision, ['accept', 'decline'], true)) {
            throw new ValidationException(['general' => 'Неизвестное решение по переносу.']);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) > self::REASON_LIMIT || $this->hasControlCharacters($reason)) {
            throw new ValidationException(['reason' => 'Комментарий не должен превышать 1000 символов.']);
        }

        $this->transactional(function () use ($appointmentId, $customerId, $decision, $reason): void {
            $initial = $this->appointments->findById($appointmentId);
            if ($initial === null || (int) $initial['customer_id'] !== $customerId) {
                throw new HttpException(404, 'Appointment not found');
            }
            $technicianId = (int) $initial['technician_id'];
            if (!$this->calendarRepository->lockTechnician($technicianId)) {
                throw new HttpException(404, 'Technician not found');
            }
            $row = $this->appointments->findRawForUpdate($appointmentId);
            if ($row === null || (int) $row['customer_id'] !== $customerId) {
                throw new HttpException(404, 'Appointment not found');
            }
            if ((string) $row['status'] !== 'reschedule_proposed') {
                throw new ValidationException(['general' => 'По этой встрече нет предложенного переноса.']);
            }
            if ((int) ($row['proposed_by'] ?? 0) === $customerId) {
                throw new ValidationException(['general' => 'Перенос предложен вами. Ответ должен дать мастер.']);
            }

            $previousStatus = (string) ($row['reschedule_from_status'] ?? 'confirmed');
            $nowUtc = $this->calendar->nowUtc();

            if ($decision === 'accept') {
                $startUtc = (string) ($row['proposed_starts_at_utc'] ?? '');
                if ($previousStatus === 'pending') {
                    $this->assertHoldActive($row, $nowUtc);
                }
                $slot = $this->calendar->slotByStart($technicianId, $startUtc, $appointmentId, true, true);
                if ($slot === null) {
                    throw new ValidationException([
                        'general' => 'Предложенное время уже занято. Попросите мастера предложить другой слот.',
                    ]);
                }
                $this->appointments->update($appointmentId, [
                    'status' => 'confirmed',
                    'starts_at_utc' => (string) $slot['start_utc'],
                    'ends_at_utc' => (string) $slot['end_utc'],
                    'proposed_starts_at_utc' => null,
                    'proposed_ends_at_utc' => null,
                    'proposed_by' => null,
                    'reschedule_from_status' => null,
                    'pending_expires_at_utc' => null,
                    'confirmed_at_utc' => $nowUtc,
                ]);
                $this->recordEvent(
                    $appointmentId,
                    $customerId,
                    'reschedule_accepted',
                    'reschedule_proposed',
                    'confirmed',
                    (string) $row['starts_at_utc'],
                    (string) $row['ends_at_utc'],
                    (string) $slot['start_utc'],
                    (string) $slot['end_utc'],
                    $reason === '' ? null : $reason
                );

                return;
            }

            $expiry = $row['pending_expires_at_utc'] !== null ? (string) $row['pending_expires_at_utc'] : null;
            $revertedStatus = $previousStatus;
            if ($previousStatus === 'pending' && ($expiry === null || $expiry <= $nowUtc)) {
                $revertedStatus = 'declined';
            }

            $fields = [
                'status' => $revertedStatus,
                'proposed_starts_at_utc' => null,
                'proposed_ends_at_utc' => null,
                'proposed_by' => null,
                'reschedule_from_status' => null,
            ];
            if ($revertedStatus === 'declined') {
                $fields['declined_at_utc'] = $nowUtc;
                $fields['pending_expires_at_utc'] = null;
            }
            $this->appointments->update($appointmentId, $fields);
            $this->recordEvent(
                $appointmentId,
                $customerId,
                'reschedule_declined',
                'reschedule_proposed',
                $revertedStatus,
                (string) $row['starts_at_utc'],
                (string) $row['ends_at_utc'],
                (string) $row['starts_at_utc'],
                (string) $row['ends_at_utc'],
                $reason === '' ? null : $reason
            );
        });
    }

    /** @param array<string, mixed> $user */
    public function cancel(array $user, int $appointmentId, string $reason = ''): void
    {
        $this->requireUser($user);
        $actorId = (int) $user['id'];
        $role = (string) $user['role'];
        $reason = trim($reason);
        if (mb_strlen($reason) > self::REASON_LIMIT || $this->hasControlCharacters($reason)) {
            throw new ValidationException(['reason' => 'Комментарий не должен превышать 1000 символов.']);
        }

        $this->transactional(function () use ($appointmentId, $actorId, $role, $reason): void {
            $initial = $this->appointments->findById($appointmentId);
            if ($initial === null) {
                throw new HttpException(404, 'Appointment not found');
            }
            $technicianId = (int) $initial['technician_id'];
            if (!$this->calendarRepository->lockTechnician($technicianId)) {
                throw new HttpException(404, 'Technician not found');
            }
            $row = $this->appointments->findRawForUpdate($appointmentId);
            if ($row === null) {
                throw new HttpException(404, 'Appointment not found');
            }
            $this->assertParticipant($row, $actorId);

            $status = (string) $row['status'];
            if (!in_array($status, ['pending', 'confirmed', 'reschedule_proposed'], true)) {
                throw new ValidationException(['general' => 'Эту встречу нельзя отменить в текущем статусе.']);
            }

            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $starts = $this->calendar->utc((string) $row['starts_at_utc']);
            if ($starts <= $now) {
                throw new ValidationException(['general' => 'Встреча уже началась или прошла.']);
            }

            if ($role === 'customer') {
                $settings = $this->calendar->settings($technicianId);
                $wasConfirmed = $status === 'confirmed'
                    || ($status === 'reschedule_proposed'
                        && (string) ($row['reschedule_from_status'] ?? '') === 'confirmed');
                if ($wasConfirmed) {
                    $cutoff = (int) $settings['cancellation_cutoff_minutes'];
                    if ($now > $starts->modify('-' . $cutoff . ' minutes')) {
                        throw new ValidationException([
                            'general' => 'До встречи осталось меньше допустимого срока отмены. '
                                . 'Свяжитесь с мастером в чате заявки.',
                        ]);
                    }
                }
            }

            $nowUtc = $this->calendar->nowUtc();
            $this->appointments->update($appointmentId, [
                'status' => 'cancelled',
                'cancelled_at_utc' => $nowUtc,
                'proposed_starts_at_utc' => null,
                'proposed_ends_at_utc' => null,
                'proposed_by' => null,
                'reschedule_from_status' => null,
                'pending_expires_at_utc' => null,
            ]);
            $this->recordEvent(
                $appointmentId,
                $actorId,
                'cancelled',
                $status,
                'cancelled',
                (string) $row['starts_at_utc'],
                (string) $row['ends_at_utc'],
                null,
                null,
                $reason === '' ? null : $reason
            );
        });
    }

    /** @param array<string, mixed> $user */
    public function complete(array $user, int $appointmentId, string $outcome): void
    {
        $technicianId = $this->requireTechnician($user);
        if (!in_array($outcome, ['completed', 'no_show'], true)) {
            throw new ValidationException(['general' => 'Неизвестный итог встречи.']);
        }

        $this->transactional(function () use ($technicianId, $appointmentId, $outcome): void {
            if (!$this->calendarRepository->lockTechnician($technicianId)) {
                throw new HttpException(404, 'Technician not found');
            }
            $row = $this->appointments->findRawForUpdate($appointmentId);
            if ($row === null || (int) $row['technician_id'] !== $technicianId) {
                throw new HttpException(404, 'Appointment not found');
            }
            if ((string) $row['status'] !== 'confirmed') {
                throw new ValidationException(['general' => 'Завершить можно только подтверждённую встречу.']);
            }
            if ($this->calendar->utc((string) $row['starts_at_utc']) > new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
                throw new ValidationException(['general' => 'Встреча ещё не началась.']);
            }

            $nowUtc = $this->calendar->nowUtc();
            $this->appointments->update($appointmentId, [
                'status' => $outcome,
                'completed_at_utc' => $nowUtc,
            ]);
            $this->recordEvent(
                $appointmentId,
                $technicianId,
                $outcome,
                'confirmed',
                $outcome,
                (string) $row['starts_at_utc'],
                (string) $row['ends_at_utc'],
                (string) $row['starts_at_utc'],
                (string) $row['ends_at_utc'],
                null
            );
        });
    }

    /**
     * Close lapsed 24-hour holds. This never deletes an appointment: the row
     * becomes `declined`, keeps its event trail and stops occupying the slot.
     */
    public function expirePendingHolds(): int
    {
        return $this->transactional(function (): int {
            $nowUtc = $this->calendar->nowUtc();
            $rows = $this->appointments->expiredHolds($nowUtc);
            foreach ($rows as $row) {
                $appointmentId = (int) $row['id'];
                $fromStatus = (string) $row['status'];
                $this->appointments->update($appointmentId, [
                    'status' => 'declined',
                    'declined_at_utc' => $nowUtc,
                    'proposed_starts_at_utc' => null,
                    'proposed_ends_at_utc' => null,
                    'proposed_by' => null,
                    'reschedule_from_status' => null,
                ]);
                $this->recordEvent(
                    $appointmentId,
                    null,
                    'expired',
                    $fromStatus,
                    'declined',
                    (string) $row['starts_at_utc'],
                    (string) $row['ends_at_utc'],
                    null,
                    null,
                    'Срок удержания слота истёк.'
                );
            }

            return count($rows);
        });
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentForCustomer(array $row): array
    {
        $presented = $this->calendar->presentAppointment($row);
        unset(
            $presented['technician_note'],
            $presented['proposed_by'],
            $presented['created_by'],
            $presented['reschedule_from_status']
        );
        $presented['permissions'] = [];

        return $presented;
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function presentEvent(array $event, DateTimeZone $timezone, bool $forCustomer): array
    {
        $action = (string) ($event['action'] ?? '');
        $previousStart = $event['previous_starts_at_utc'] ?? null;
        $previousEnd = $event['previous_ends_at_utc'] ?? null;
        $newStart = $event['new_starts_at_utc'] ?? null;
        $newEnd = $event['new_ends_at_utc'] ?? null;
        $createdAt = (string) ($event['created_at'] ?? '');
        // technician_appointment_events.created_at is written by MySQL in the
        // application timezone (the connection sets SET time_zone to the
        // APP_TIMEZONE offset). It is not a UTC column like *_at_utc.
        $appTimezone = new DateTimeZone((string) $this->config->get('app.timezone', 'UTC'));
        $createdLocal = $createdAt === ''
            ? ''
            : (new DateTimeImmutable($createdAt, $appTimezone))->setTimezone($timezone)->format('d.m.Y H:i');

        $presented = [
            'action' => $action,
            'action_label' => self::EVENT_LABELS[$action] ?? $action,
            'from_status' => $event['from_status'] ?? null,
            'to_status' => $event['to_status'] ?? null,
            'created_local' => $createdLocal,
            'previous_label' => $previousStart !== null
                ? $this->calendar->utc((string) $previousStart)->setTimezone($timezone)->format('d.m.Y H:i')
                    . '–' . $this->calendar->utc((string) $previousEnd)->setTimezone($timezone)->format('H:i')
                : null,
            'new_label' => $newStart !== null
                ? $this->calendar->utc((string) $newStart)->setTimezone($timezone)->format('d.m.Y H:i')
                    . '–' . $this->calendar->utc((string) $newEnd)->setTimezone($timezone)->format('H:i')
                : null,
            'actor_name' => (string) ($event['actor_name'] ?? ''),
        ];

        $presented['note'] = $forCustomer ? null : ($event['note'] ?? null);

        return $presented;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $user
     * @return array<string, bool>
     */
    private function permissions(array $row, array $user, array $settings): array
    {
        $role = (string) ($user['role'] ?? '');
        $userId = (int) ($user['id'] ?? 0);
        $isCustomer = $role === 'customer' && (int) $row['customer_id'] === $userId;
        $isTechnician = $role === 'technician' && (int) $row['technician_id'] === $userId;
        $status = (string) $row['status'];
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $starts = $this->calendar->utc((string) $row['starts_at_utc']);
        $future = $starts > $now;

        $canCancel = ($isCustomer || $isTechnician)
            && $future
            && in_array($status, ['pending', 'confirmed', 'reschedule_proposed'], true);
        if ($canCancel && $isCustomer) {
            $wasConfirmed = $status === 'confirmed'
                || ($status === 'reschedule_proposed'
                    && (string) ($row['reschedule_from_status'] ?? '') === 'confirmed');
            if ($wasConfirmed) {
                $cutoff = (int) $settings['cancellation_cutoff_minutes'];
                $canCancel = $now <= $starts->modify('-' . $cutoff . ' minutes');
            }
        }

        return [
            'can_confirm' => $isTechnician
                && $future
                && ($status === 'pending'
                    || ($status === 'reschedule_proposed'
                        && (int) ($row['proposed_by'] ?? 0) !== $userId)),
            'can_decline' => $isTechnician
                && in_array($status, ['pending', 'reschedule_proposed'], true)
                && !($status === 'reschedule_proposed'
                    && (int) ($row['proposed_by'] ?? 0) === $userId),
            'can_propose' => ($isCustomer || $isTechnician) && $future
                && in_array($status, ['pending', 'confirmed'], true),
            'can_respond' => $isCustomer
                && $status === 'reschedule_proposed'
                && (int) ($row['proposed_by'] ?? 0) !== $userId,
            'can_cancel' => $canCancel,
            'can_complete' => $isTechnician && $status === 'confirmed' && !$future,
            'can_no_show' => $isTechnician && $status === 'confirmed' && !$future,
        ];
    }

    /** @param array<string, mixed> $row */
    private function assertCanView(array $row, array $user): void
    {
        $role = (string) ($user['role'] ?? '');
        $userId = (int) ($user['id'] ?? 0);
        if ($role === 'admin') {
            return;
        }
        if (($role === 'customer' && (int) $row['customer_id'] === $userId)
            || ($role === 'technician' && (int) $row['technician_id'] === $userId)
        ) {
            return;
        }

        throw new HttpException(404, 'Appointment not found');
    }

    /** @param array<string, mixed> $row */
    private function assertParticipant(array $row, int $userId): void
    {
        if ((int) $row['customer_id'] !== $userId && (int) $row['technician_id'] !== $userId) {
            throw new HttpException(404, 'Appointment not found');
        }
    }

    /** @param array<string, mixed> $user */
    private function requireUser(array $user): int
    {
        $id = (int) ($user['id'] ?? 0);
        if ($id < 1) {
            throw new HttpException(403, 'Authentication required');
        }

        return $id;
    }

    /** @param array<string, mixed> $user */
    private function requireCustomer(array $user): int
    {
        $id = $this->requireUser($user);
        if ((string) ($user['role'] ?? '') !== 'customer') {
            throw new HttpException(403, 'Customer access required');
        }

        return $id;
    }

    /** @param array<string, mixed> $user */
    private function requireTechnician(array $user): int
    {
        $id = $this->requireUser($user);
        if ((string) ($user['role'] ?? '') !== 'technician') {
            throw new HttpException(403, 'Technician access required');
        }

        return $id;
    }

    private function recordEvent(
        int $appointmentId,
        ?int $actorId,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        ?string $previousStart,
        ?string $previousEnd,
        ?string $newStart,
        ?string $newEnd,
        ?string $note
    ): void {
        $this->appointments->addEvent($appointmentId, [
            'actor_id' => $actorId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'previous_starts_at_utc' => $previousStart,
            'previous_ends_at_utc' => $previousEnd,
            'new_starts_at_utc' => $newStart,
            'new_ends_at_utc' => $newEnd,
            'note' => $note,
        ]);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function transactional(callable $callback): mixed
    {
        $pdo = $this->appointments->connection();
        $pdo->beginTransaction();

        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function hasControlCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1;
    }

    /**
     * A pending slot is only valid until its short hold expires. The sweep is
     * lazy, so a direct POST can reach this check before the row was marked
     * declined; such a stale hold must never be resurrected.
     *
     * @param array<string, mixed> $row
     */
    private function assertHoldActive(array $row, string $nowUtc): void
    {
        $expiresAt = $row['pending_expires_at_utc'] ?? null;
        if ($expiresAt === null || (string) $expiresAt > $nowUtc) {
            return;
        }

        throw new ValidationException([
            'general' => 'Срок удержания этого времени истёк. Выберите новый свободный слот.',
        ]);
    }
}
