<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use FixPoint\Core\Config;
use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\AppointmentRepository;
use FixPoint\Repositories\CalendarRepository;
use FixPoint\Services\AppointmentService;
use FixPoint\Services\CalendarService;
use FixPoint\Tests\IntegrationTestCase;
use PDO;
use Throwable;

/**
 * CAL-01 integration coverage against the real MySQL test schema. Every
 * fixture user/request is deleted in tearDown; seeded data is never modified.
 */
final class CalendarAppointmentTest extends IntegrationTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $requestIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->userIds = [];
        $this->requestIds = [];
    }

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        if ($this->requestIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($this->requestIds), '?'));
            $pdo->prepare('DELETE FROM repair_requests WHERE id IN (' . $placeholders . ')')
                ->execute($this->requestIds);
        }
        if ($this->userIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($this->userIds), '?'));
            $pdo->prepare('DELETE FROM users WHERE id IN (' . $placeholders . ')')
                ->execute($this->userIds);
        }
        parent::tearDown();
    }

    public function testScheduleIsPersistedAndOverlappingIntervalsAreRejected(): void
    {
        $technicianId = $this->createUser('technician');

        $this->calendar()->saveSettings($this->technician($technicianId), [
            'timezone' => 'UTC',
            'slot_minutes' => '30',
            'min_notice_minutes' => '0',
            'pending_hold_minutes' => '1440',
            'cancellation_cutoff_minutes' => '120',
            'intervals' => [
                1 => [
                    ['start' => '09:00', 'end' => '13:00'],
                    ['start' => '14:00', 'end' => '18:00'],
                ],
                3 => [
                    ['start' => '10:00', 'end' => '12:00'],
                ],
            ],
        ]);

        $settings = $this->calendar()->settings($technicianId);
        $this->assertSame('UTC', $settings['timezone']);
        $this->assertSame(30, $settings['slot_minutes']);
        $this->assertSame(
            [['start' => '09:00', 'end' => '13:00'], ['start' => '14:00', 'end' => '18:00']],
            $settings['intervals'][1]
        );
        $this->assertSame([], $settings['intervals'][2]);
        $this->assertSame([['start' => '10:00', 'end' => '12:00']], $settings['intervals'][3]);

        $error = $this->capture(fn () => $this->calendar()->saveSettings(
            $this->technician($technicianId),
            [
                'timezone' => 'UTC',
                'slot_minutes' => '60',
                'intervals' => [
                    2 => [
                        ['start' => '09:00', 'end' => '12:00'],
                        ['start' => '11:00', 'end' => '15:00'],
                    ],
                ],
            ]
        ));
        $this->assertInstanceOf(ValidationException::class, $error);
        $this->assertArrayHasKey('intervals', $error->errors());

        $invalidSlot = $this->capture(fn () => $this->calendar()->saveSettings(
            $this->technician($technicianId),
            ['timezone' => 'UTC', 'slot_minutes' => '45', 'intervals' => []]
        ));
        $this->assertInstanceOf(ValidationException::class, $invalidSlot);
        $this->assertArrayHasKey('slot_minutes', $invalidSlot->errors());

        $this->assertSame(30, $this->calendar()->settings($technicianId)['slot_minutes']);
    }

    public function testAppointmentEventCreatedTimeIsParsedInApplicationTimezone(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);
        $requestId = $this->createRequest($customerId, $technicianId);
        $date = $this->futureDate(2);
        $appointmentId = $this->insertAppointment(
            $requestId,
            $customerId,
            $technicianId,
            'pending',
            $date . ' 09:00:00',
            $date . ' 10:00:00'
        );

        $statement = $this->pdo()->prepare(
            'INSERT INTO technician_appointment_events
                (appointment_id, actor_id, action, from_status, to_status, created_at)
             VALUES (:appointment_id, :actor_id, "created", NULL, "pending", :created_at)'
        );
        $statement->execute([
            'appointment_id' => $appointmentId,
            'actor_id' => $customerId,
            'created_at' => '2026-01-15 10:00:00',
        ]);

        $service = new AppointmentService(
            $this->calendar(),
            app()->make(CalendarRepository::class),
            app()->make(AppointmentRepository::class),
            new Config(['app' => ['timezone' => 'Asia/Almaty']])
        );
        $detail = $service->detail($this->technician($technicianId), $appointmentId);
        $created = null;
        foreach ($detail['events'] as $event) {
            if (($event['action'] ?? '') === 'created') {
                $created = $event;
                break;
            }
        }

        $this->assertIsArray($created);
        // The event timestamp is local Asia/Almaty time; the technician profile is UTC.
        $this->assertSame('15.01.2026 05:00', $created['created_local']);
    }

    public function testSlotsRespectBlocksAppointmentsAndCancellation(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);

        $date = $this->futureDate(2);
        $start9 = $date . ' 09:00:00';
        $start10 = $date . ' 10:00:00';
        $start11 = $date . ' 11:00:00';

        $this->assertTrue($this->hasSlot($technicianId, $date, $start9));

        $this->calendar()->saveBlock($this->technician($technicianId), [
            'kind' => 'personal',
            'title' => 'Личный приём',
            'notes' => 'Приватная заметка',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $this->assertFalse($this->hasSlot($technicianId, $date, $start9));
        $this->assertTrue($this->hasSlot($technicianId, $date, $start10));

        $first = $this->createRequest($customerId, $technicianId);
        $appointmentId = $this->appointments()->book($this->customer($customerId), $first, [
            'meeting_type' => 'consultation',
            'start_utc' => $start10,
        ]);
        $this->assertFalse($this->hasSlot($technicianId, $date, $start10));
        $this->assertTrue($this->hasSlot($technicianId, $date, $start11));

        $second = $this->createRequest($customerId, $technicianId);
        $cancelId = $this->appointments()->book($this->customer($customerId), $second, [
            'meeting_type' => 'device_dropoff',
            'start_utc' => $start11,
        ]);
        $this->appointments()->cancel($this->customer($customerId), $cancelId, 'Планы изменились');
        $this->assertTrue($this->hasSlot($technicianId, $date, $start11));

        $visible = $this->appointments()->detail($this->customer($customerId), $appointmentId);
        $this->assertSame('pending', $visible['appointment']['status']);
    }

    public function testBookingRejectsForeignRequestOutsideSchedulePastTimeAndForgedSlot(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $otherCustomerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);
        $requestId = $this->createRequest($customerId, $technicianId);

        $foreign = $this->capture(fn () => $this->appointments()->book(
            $this->customer($otherCustomerId),
            $requestId,
            ['meeting_type' => 'consultation', 'start_utc' => $this->futureDate(2) . ' 09:00:00']
        ));
        $this->assertInstanceOf(HttpException::class, $foreign);
        $this->assertSame(404, $foreign->statusCode);

        $outside = $this->capture(fn () => $this->appointments()->book(
            $this->customer($customerId),
            $requestId,
            ['meeting_type' => 'consultation', 'start_utc' => $this->futureDate(2) . ' 20:00:00']
        ));
        $this->assertInstanceOf(ValidationException::class, $outside);

        $past = $this->capture(fn () => $this->appointments()->book(
            $this->customer($customerId),
            $requestId,
            ['meeting_type' => 'consultation', 'start_utc' => $this->pastDate(1) . ' 09:00:00']
        ));
        $this->assertInstanceOf(ValidationException::class, $past);

        $forged = $this->capture(fn () => $this->appointments()->book(
            $this->customer($customerId),
            $requestId,
            ['meeting_type' => 'consultation', 'start_utc' => $this->futureDate(2) . ' 09:17:00']
        ));
        $this->assertInstanceOf(ValidationException::class, $forged);

        $this->assertSame(0, $this->appointmentCount($requestId));
    }

    public function testConfirmDeclineAndAssignedTechnicianAreEnforced(): void
    {
        $technicianId = $this->createUser('technician');
        $otherTechnicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);
        $this->configureTechnician($otherTechnicianId);

        $requestId = $this->createRequest($customerId, $technicianId);
        $appointmentId = $this->appointments()->book($this->customer($customerId), $requestId, [
            'meeting_type' => 'device_dropoff',
            'start_utc' => $this->futureDate(2) . ' 09:00:00',
        ]);

        $foreignConfirm = $this->capture(
            fn () => $this->appointments()->confirm($this->technician($otherTechnicianId), $appointmentId)
        );
        $this->assertInstanceOf(HttpException::class, $foreignConfirm);
        $this->assertSame(404, $foreignConfirm->statusCode);

        $this->appointments()->confirm($this->technician($technicianId), $appointmentId);
        $this->assertSame('confirmed', $this->appointmentStatus($appointmentId));
        $this->assertSame(
            ['created', 'confirmed'],
            $this->eventActions($appointmentId)
        );

        $secondRequest = $this->createRequest($customerId, $technicianId);
        $declinedId = $this->appointments()->book($this->customer($customerId), $secondRequest, [
            'meeting_type' => 'consultation',
            'start_utc' => $this->futureDate(2) . ' 10:00:00',
        ]);
        $this->appointments()->decline($this->technician($technicianId), $declinedId, 'Время больше недоступно');
        $this->assertSame('declined', $this->appointmentStatus($declinedId));
        $this->assertTrue($this->hasSlot($technicianId, $this->futureDate(2), $this->futureDate(2) . ' 10:00:00'));

        $repeatConfirm = $this->capture(
            fn () => $this->appointments()->confirm($this->technician($technicianId), $declinedId)
        );
        $this->assertInstanceOf(ValidationException::class, $repeatConfirm);
    }

    public function testRescheduleCanBeProposedAcceptedAndDeclined(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);
        $date = $this->futureDate(2);

        $requestId = $this->createRequest($customerId, $technicianId);
        $appointmentId = $this->appointments()->book($this->customer($customerId), $requestId, [
            'meeting_type' => 'consultation',
            'start_utc' => $date . ' 09:00:00',
        ]);
        $this->appointments()->confirm($this->technician($technicianId), $appointmentId);
        $this->appointments()->proposeReschedule(
            $this->technician($technicianId),
            $appointmentId,
            $date . ' 10:00:00',
            'Удобнее на час позже'
        );
        $this->assertSame('reschedule_proposed', $this->appointmentStatus($appointmentId));
        $this->assertFalse($this->hasSlot($technicianId, $date, $date . ' 10:00:00'));
        $this->assertFalse($this->hasSlot($technicianId, $date, $date . ' 09:00:00'));

        $this->appointments()->respondToProposal(
            $this->customer($customerId),
            $appointmentId,
            'accept',
            'Подходит'
        );
        $this->assertSame('confirmed', $this->appointmentStatus($appointmentId));
        $this->assertSame($date . ' 10:00:00', $this->appointmentStart($appointmentId));
        $this->assertTrue($this->hasSlot($technicianId, $date, $date . ' 09:00:00'));
        $this->assertFalse($this->hasSlot($technicianId, $date, $date . ' 10:00:00'));

        $secondRequest = $this->createRequest($customerId, $technicianId);
        $pendingId = $this->appointments()->book($this->customer($customerId), $secondRequest, [
            'meeting_type' => 'device_pickup',
            'start_utc' => $date . ' 11:00:00',
        ]);
        $this->appointments()->proposeReschedule(
            $this->technician($technicianId),
            $pendingId,
            $date . ' 12:00:00'
        );
        $this->appointments()->respondToProposal($this->customer($customerId), $pendingId, 'decline');
        $this->assertSame('pending', $this->appointmentStatus($pendingId));
        $this->assertTrue($this->hasSlot($technicianId, $date, $date . ' 12:00:00'));
    }

    public function testCustomerCancellationCutoffIsServerSide(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);

        $requestId = $this->createRequest($customerId, $technicianId);
        $futureId = $this->appointments()->book($this->customer($customerId), $requestId, [
            'meeting_type' => 'consultation',
            'start_utc' => $this->futureDate(2) . ' 09:00:00',
        ]);
        $this->appointments()->confirm($this->technician($technicianId), $futureId);
        $this->appointments()->cancel($this->customer($customerId), $futureId, 'Не смогу прийти');
        $this->assertSame('cancelled', $this->appointmentStatus($futureId));

        $soonRequest = $this->createRequest($customerId, $technicianId);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $soonId = $this->insertAppointment(
            $soonRequest,
            $customerId,
            $technicianId,
            'confirmed',
            $now->modify('+30 minutes')->format('Y-m-d H:i:s'),
            $now->modify('+90 minutes')->format('Y-m-d H:i:s')
        );
        $late = $this->capture(
            fn () => $this->appointments()->cancel($this->customer($customerId), $soonId, 'Поздняя отмена')
        );
        $this->assertInstanceOf(ValidationException::class, $late);
        $this->assertSame('confirmed', $this->appointmentStatus($soonId));
    }

    public function testPendingHoldExpiresFreesSlotAndCannotBeConfirmed(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);
        $requestId = $this->createRequest($customerId, $technicianId);
        $date = $this->futureDate(2);
        $start = $date . ' 09:00:00';

        $appointmentId = $this->appointments()->book($this->customer($customerId), $requestId, [
            'meeting_type' => 'consultation',
            'start_utc' => $start,
        ]);
        $this->assertFalse($this->hasSlot($technicianId, $date, $start));

        $this->pdo()->prepare(
            'UPDATE technician_appointments SET pending_expires_at_utc = :expired WHERE id = :id'
        )->execute([
            'expired' => (new DateTimeImmutable('-5 minutes', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'id' => $appointmentId,
        ]);

        $this->assertTrue($this->hasSlot($technicianId, $date, $start));
        $staleConfirm = $this->capture(
            fn () => $this->appointments()->confirm($this->technician($technicianId), $appointmentId)
        );
        $this->assertInstanceOf(ValidationException::class, $staleConfirm);

        $expired = $this->appointments()->expirePendingHolds();
        $this->assertGreaterThan(0, $expired);
        $this->assertSame('declined', $this->appointmentStatus($appointmentId));
        $this->assertTrue(in_array('expired', $this->eventActions($appointmentId), true));
        $actor = $this->pdo()->prepare(
            'SELECT actor_id FROM technician_appointment_events
             WHERE appointment_id = :id AND action = "expired" LIMIT 1'
        );
        $actor->execute(['id' => $appointmentId]);
        $this->assertNull($actor->fetchColumn());
    }

    public function testDuplicateActiveMeetingTypeIsRejected(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);
        $requestId = $this->createRequest($customerId, $technicianId);
        $date = $this->futureDate(2);

        $this->appointments()->book($this->customer($customerId), $requestId, [
            'meeting_type' => 'consultation',
            'start_utc' => $date . ' 09:00:00',
        ]);

        $duplicate = $this->capture(fn () => $this->appointments()->book(
            $this->customer($customerId),
            $requestId,
            ['meeting_type' => 'consultation', 'start_utc' => $date . ' 10:00:00']
        ));
        $this->assertInstanceOf(ValidationException::class, $duplicate);
        $this->assertArrayHasKey('meeting_type', $duplicate->errors());
        $this->assertSame(1, $this->appointmentCount($requestId));
    }

    public function testConfirmedAppointmentBlocksOverlappingPrivateBlock(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);
        $date = $this->futureDate(2);

        $requestId = $this->createRequest($customerId, $technicianId);
        $appointmentId = $this->appointments()->book($this->customer($customerId), $requestId, [
            'meeting_type' => 'device_pickup',
            'start_utc' => $date . ' 09:00:00',
        ]);
        $this->appointments()->confirm($this->technician($technicianId), $appointmentId);

        $overlap = $this->capture(fn () => $this->calendar()->saveBlock(
            $this->technician($technicianId),
            [
                'kind' => 'personal',
                'title' => 'Пересечение с встречей',
                'start_date' => $date,
                'end_date' => $date,
                'start_time' => '09:30',
                'end_time' => '10:30',
            ]
        ));
        $this->assertInstanceOf(ValidationException::class, $overlap);
        $this->assertArrayHasKey('block', $overlap->errors());
        $this->assertSame(0, $this->blockCount($technicianId));

        $this->calendar()->saveBlock($this->technician($technicianId), [
            'kind' => 'vacation',
            'title' => 'Отпуск после встречи',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
        $this->assertSame(1, $this->blockCount($technicianId));
    }

    public function testPrivateBlockAndTechnicianNoteNeverReachCustomerPages(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);
        $date = $this->futureDate(2);
        $requestId = $this->createRequest($customerId, $technicianId);

        $this->calendar()->saveBlock($this->technician($technicianId), [
            'kind' => 'vacation',
            'title' => 'СЕКРЕТНЫЙ ОТПУСК',
            'notes' => 'Приватная заметка мастера',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        app()->auth()->login($customerId);
        $bookPage = app()->handle(\FixPoint\Core\Request::create(
            'GET',
            '/requests/' . $requestId . '/appointments/book?date=' . $date
        ));
        $this->assertSame(200, $bookPage->status());
        $this->assertNotContains('СЕКРЕТНЫЙ ОТПУСК', $bookPage->body());
        $this->assertNotContains('Приватная заметка мастера', $bookPage->body());

        $appointmentId = $this->insertAppointment(
            $requestId,
            $customerId,
            $technicianId,
            'pending',
            $date . ' 10:00:00',
            $date . ' 11:00:00'
        );
        $this->pdo()->prepare(
            'UPDATE technician_appointments
             SET technician_note = :note, pending_expires_at_utc = :expires
             WHERE id = :id'
        )->execute([
            'note' => 'Внутренняя заметка о клиенте',
            'expires' => (new DateTimeImmutable('+1 day', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'id' => $appointmentId,
        ]);

        $card = app()->handle(\FixPoint\Core\Request::create('GET', '/appointments/' . $appointmentId));
        $this->assertSame(200, $card->status());
        $this->assertNotContains('Внутренняя заметка о клиенте', $card->body());

        app()->auth()->logout();
        app()->auth()->login($technicianId);
        $technicianCard = app()->handle(\FixPoint\Core\Request::create(
            'GET',
            '/technician/appointments/' . $appointmentId
        ));
        $this->assertSame(200, $technicianCard->status());
        $this->assertContains('Внутренняя заметка о клиенте', $technicianCard->body());
    }

    public function testCustomerAndTechnicianHttpRoutesRenderAndCheckCsrfAndOwnership(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $otherCustomerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);
        $requestId = $this->createRequest($customerId, $technicianId);

        app()->auth()->login($customerId);
        $bookPage = app()->handle(\FixPoint\Core\Request::create(
            'GET',
            '/requests/' . $requestId . '/appointments/book'
        ));
        $this->assertSame(200, $bookPage->status());
        $this->assertContains('Выберите время встречи', $bookPage->body());

        $noToken = app()->handle(\FixPoint\Core\Request::create(
            'POST',
            '/requests/' . $requestId . '/appointments/book',
            ['meeting_type' => 'consultation', 'start_utc' => $this->futureDate(2) . ' 09:00:00']
        ));
        $this->assertSame(419, $noToken->status());
        $this->assertSame(0, $this->appointmentCount($requestId));

        $created = $this->post('/requests/' . $requestId . '/appointments/book', [
            'meeting_type' => 'consultation',
            'start_utc' => $this->futureDate(2) . ' 09:00:00',
            'customer_comment' => 'Нужна консультация',
        ]);
        $this->assertSame(302, $created->status());
        $this->assertSame(1, $this->appointmentCount($requestId));

        $appointmentId = (int) $this->pdo()->query(
            'SELECT id FROM technician_appointments WHERE request_id = ' . $requestId . ' LIMIT 1'
        )->fetchColumn();
        $customerCard = app()->handle(\FixPoint\Core\Request::create('GET', '/appointments/' . $appointmentId));
        $this->assertSame(200, $customerCard->status());
        $this->assertContains('История', $customerCard->body());
        $list = app()->handle(\FixPoint\Core\Request::create('GET', '/appointments'));
        $this->assertSame(200, $list->status());
        $this->assertContains('Мои встречи', $list->body());

        app()->auth()->logout();
        app()->auth()->login($otherCustomerId);
        $foreign = app()->handle(\FixPoint\Core\Request::create(
            'GET',
            '/requests/' . $requestId . '/appointments/book'
        ));
        $this->assertSame(404, $foreign->status());
        $foreignCard = app()->handle(\FixPoint\Core\Request::create('GET', '/appointments/' . $appointmentId));
        $this->assertSame(404, $foreignCard->status());

        app()->auth()->logout();
        app()->auth()->login($technicianId);
        $calendar = app()->handle(\FixPoint\Core\Request::create('GET', '/technician/calendar'));
        $this->assertSame(200, $calendar->status());
        $this->assertContains('Календарь встреч', $calendar->body());
        $calendarDay = app()->handle(\FixPoint\Core\Request::create('GET', '/technician/calendar?view=day'));
        $this->assertSame(200, $calendarDay->status());
        $settings = app()->handle(\FixPoint\Core\Request::create('GET', '/technician/calendar/settings'));
        $this->assertSame(200, $settings->status());
        $blocks = app()->handle(\FixPoint\Core\Request::create('GET', '/technician/calendar/blocks'));
        $this->assertSame(200, $blocks->status());

        $techCard = app()->handle(\FixPoint\Core\Request::create(
            'GET',
            '/technician/appointments/' . $appointmentId
        ));
        $this->assertSame(200, $techCard->status());
        $this->assertContains('История изменений', $techCard->body());
        $this->assertContains('Нужна консультация', $techCard->body());

        $confirmed = $this->post('/technician/appointments/' . $appointmentId . '/confirm', []);
        $this->assertSame(302, $confirmed->status());
        $this->assertSame('confirmed', $this->appointmentStatus($appointmentId));

        $cancelled = $this->post('/technician/appointments/' . $appointmentId . '/cancel', [
            'reason' => 'Мастер заболел',
        ]);
        $this->assertSame(302, $cancelled->status());
        $this->assertSame('cancelled', $this->appointmentStatus($appointmentId));

        app()->auth()->logout();
        app()->auth()->login($customerId);
        $customerCannotCancelTechnicianRoute = $this->post(
            '/technician/appointments/' . $appointmentId . '/cancel',
            []
        );
        $this->assertSame(403, $customerCannotCancelTechnicianRoute->status());
        $customerCannotSeeTechnician = app()->handle(\FixPoint\Core\Request::create(
            'GET',
            '/technician/appointments/' . $appointmentId
        ));
        $this->assertSame(403, $customerCannotSeeTechnician->status());
    }

    public function testConcurrentBookingsSerializeOnTechnicianLockAndOnlyOneWins(): void
    {
        $technicianId = $this->createUser('technician');
        $customerId = $this->createUser('customer');
        $otherCustomerId = $this->createUser('customer');
        $this->configureTechnician($technicianId);
        $date = $this->futureDate(2);
        $start = $date . ' 09:00:00';
        $end = $date . ' 10:00:00';
        $requestA = $this->createRequest($customerId, $technicianId);
        $requestB = $this->createRequest($otherCustomerId, $technicianId);

        $readyFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cal_lock_' . bin2hex(random_bytes(6));
        $config = app()->config();
        $command = [
            PHP_BINARY,
            dirname(__DIR__) . '/fixtures/hold_calendar_lock.php',
            (string) $config->get('database.host'),
            (string) $config->get('database.port'),
            (string) $config->get('database.name'),
            (string) $config->get('database.username'),
            (string) $config->get('database.password'),
            (string) $technicianId,
            (string) $requestA,
            (string) $customerId,
            $start,
            $end,
            $readyFile,
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertTrue(is_resource($process), 'Concurrent fixture process must start.');

        $deadline = microtime(true) + 8.0;
        while (!is_file($readyFile) && microtime(true) < $deadline) {
            usleep(25000);
        }
        $this->assertTrue(is_file($readyFile), 'Concurrent fixture must acquire the technician lock.');

        $thrown = null;
        try {
            $this->appointments()->book($this->customer($otherCustomerId), $requestB, [
                'meeting_type' => 'consultation',
                'start_utc' => $start,
            ]);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        @unlink($readyFile);

        $this->assertSame(0, $exitCode, 'Concurrent fixture failed: ' . trim($stdout . ' ' . $stderr));
        $this->assertInstanceOf(ValidationException::class, $thrown);
        $this->assertSame(1, $this->appointmentCount($requestA));
        $this->assertSame(0, $this->appointmentCount($requestB));
    }

    /** @param array<string, mixed> $input */
    private function configureTechnician(int $technicianId, array $input = []): void
    {
        $intervals = [];
        foreach (array_keys(CalendarService::WEEKDAYS) as $weekday) {
            $intervals[$weekday] = [['start' => '09:00', 'end' => '18:00']];
        }

        $this->calendar()->saveSettings($this->technician($technicianId), array_merge([
            'timezone' => 'UTC',
            'slot_minutes' => '60',
            'min_notice_minutes' => '0',
            'pending_hold_minutes' => '1440',
            'cancellation_cutoff_minutes' => '120',
            'intervals' => $intervals,
        ], $input));
    }

    private function createUser(string $role): int
    {
        $email = 'calendar.' . $role . '.' . bin2hex(random_bytes(6)) . '@fixpoint.test';
        $statement = $this->pdo()->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, is_active)
             VALUES (:full_name, :email, :phone, :password_hash, :role, 1)'
        );
        $statement->execute([
            'full_name' => 'CAL-01 ' . ucfirst($role),
            'email' => $email,
            'phone' => '+70000000000',
            'password_hash' => password_hash('Calendar!Test1', PASSWORD_DEFAULT),
            'role' => $role,
        ]);
        $id = (int) $this->pdo()->lastInsertId();
        $this->userIds[] = $id;

        return $id;
    }

    private function createRequest(int $customerId, int $technicianId, string $status = 'new'): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO repair_requests
                (public_number, customer_id, technician_id, status, priority,
                 device_type, device_brand, device_model, problem_description, customer_contact)
             VALUES
                (:public_number, :customer_id, :technician_id, :status, "normal",
                 "Ноутбук", "Test", "CAL-01", "Проверка календаря и записи на встречу.", :customer_contact)'
        );
        $statement->execute([
            'public_number' => 'CAL-' . strtoupper(bin2hex(random_bytes(8))),
            'customer_id' => $customerId,
            'technician_id' => $technicianId,
            'status' => $status,
            'customer_contact' => 'calendar-fixture@fixpoint.test',
        ]);
        $id = (int) $this->pdo()->lastInsertId();
        $this->requestIds[] = $id;

        return $id;
    }

    private function insertAppointment(
        int $requestId,
        int $customerId,
        int $technicianId,
        string $status,
        string $startUtc,
        string $endUtc
    ): int {
        $statement = $this->pdo()->prepare(
            'INSERT INTO technician_appointments
                (request_id, customer_id, technician_id, meeting_type, status,
                 starts_at_utc, ends_at_utc, timezone, purpose, created_by)
             VALUES
                (:request_id, :customer_id, :technician_id, "consultation", :status,
                 :starts_at_utc, :ends_at_utc, "UTC", "Консультация", :created_by)'
        );
        $statement->execute([
            'request_id' => $requestId,
            'customer_id' => $customerId,
            'technician_id' => $technicianId,
            'status' => $status,
            'starts_at_utc' => $startUtc,
            'ends_at_utc' => $endUtc,
            'created_by' => $customerId,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function hasSlot(int $technicianId, string $date, string $startUtc): bool
    {
        foreach ($this->calendar()->availableSlots($technicianId, $date) as $slot) {
            if ((string) $slot['start_utc'] === $startUtc) {
                return true;
            }
        }

        return false;
    }

    private function appointmentStatus(int $appointmentId): string
    {
        $statement = $this->pdo()->prepare('SELECT status FROM technician_appointments WHERE id = :id');
        $statement->execute(['id' => $appointmentId]);

        return (string) $statement->fetchColumn();
    }

    private function appointmentStart(int $appointmentId): string
    {
        $statement = $this->pdo()->prepare('SELECT starts_at_utc FROM technician_appointments WHERE id = :id');
        $statement->execute(['id' => $appointmentId]);

        return (string) $statement->fetchColumn();
    }

    private function appointmentCount(int $requestId): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM technician_appointments WHERE request_id = :request_id'
        );
        $statement->execute(['request_id' => $requestId]);

        return (int) $statement->fetchColumn();
    }

    private function blockCount(int $technicianId): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM technician_calendar_blocks WHERE technician_id = :technician_id'
        );
        $statement->execute(['technician_id' => $technicianId]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<string> */
    private function eventActions(int $appointmentId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT action FROM technician_appointment_events
             WHERE appointment_id = :id ORDER BY created_at ASC, id ASC'
        );
        $statement->execute(['id' => $appointmentId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function futureDate(int $days): string
    {
        return (new DateTimeImmutable('+' . $days . ' days', new DateTimeZone('UTC')))->format('Y-m-d');
    }

    private function pastDate(int $days): string
    {
        return (new DateTimeImmutable('-' . $days . ' days', new DateTimeZone('UTC')))->format('Y-m-d');
    }

    private function capture(callable $callback): ?Throwable
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            return $exception;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function customer(int $id): array
    {
        return ['id' => $id, 'role' => 'customer', 'full_name' => 'CAL-01 Customer'];
    }

    /** @return array<string, mixed> */
    private function technician(int $id): array
    {
        return ['id' => $id, 'role' => 'technician', 'full_name' => 'CAL-01 Technician'];
    }

    private function calendar(): CalendarService
    {
        return app()->make(CalendarService::class);
    }

    private function appointments(): AppointmentService
    {
        return app()->make(AppointmentService::class);
    }

    /** @param array<string, mixed> $data */
    private function post(string $uri, array $data): \FixPoint\Core\Response
    {
        $data['_token'] = app()->csrf()->token();

        return app()->handle(\FixPoint\Core\Request::create('POST', $uri, $data));
    }

    private function pdo(): PDO
    {
        return app()->database()->connection();
    }
}
