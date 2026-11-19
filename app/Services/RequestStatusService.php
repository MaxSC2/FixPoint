<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\ValidationException;

/**
 * Single source of truth for repair-request status transitions. Persistence
 * happens in RequestRepository, but no caller may change a status without
 * asking this service first.
 */
final class RequestStatusService
{
    /** @var list<string> */
    public const STATUSES = [
        'new',
        'accepted',
        'diagnostics',
        'awaiting_customer_approval',
        'awaiting_parts',
        'repairing',
        'ready_for_pickup',
        'completed',
        'cancelled',
        'rejected',
    ];

    /**
     * The repair starts only from a quote the customer has approved.
     *
     * `diagnostics` may only hand the job over to the customer, and
     * `awaiting_parts` is reachable only from a running repair, so the only
     * ways into `repairing` are `awaiting_customer_approval` (the customer's
     * decision) and `awaiting_parts` (a pause of an already approved repair).
     * There is deliberately no `diagnostics -> repairing` and no
     * `diagnostics -> awaiting_parts`: both would let a technician start the
     * work without the customer ever seeing an amount.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'new' => ['accepted', 'rejected', 'cancelled'],
        'accepted' => ['diagnostics', 'cancelled'],
        'diagnostics' => ['awaiting_customer_approval', 'rejected'],
        'awaiting_customer_approval' => ['repairing', 'cancelled'],
        'repairing' => ['awaiting_parts', 'ready_for_pickup'],
        'awaiting_parts' => ['repairing'],
        'ready_for_pickup' => ['completed'],
        'completed' => [],
        'cancelled' => [],
        'rejected' => [],
    ];

    /** @return list<string> */
    public function allowedTransitions(string $fromStatus): array
    {
        return self::TRANSITIONS[$fromStatus] ?? [];
    }

    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, $this->allowedTransitions($fromStatus), true);
    }

    public function isTerminal(string $status): bool
    {
        return in_array($status, ['completed', 'cancelled', 'rejected'], true);
    }

    /** @throws ValidationException */
    public function assertTransition(string $fromStatus, string $toStatus): void
    {
        if (!in_array($fromStatus, self::STATUSES, true)) {
            throw new ValidationException(['status' => 'Неизвестный текущий статус заявки.']);
        }

        if (!in_array($toStatus, self::STATUSES, true) || !$this->canTransition($fromStatus, $toStatus)) {
            throw new ValidationException(['status' => 'Этот переход статуса недопустим.']);
        }
    }
}
