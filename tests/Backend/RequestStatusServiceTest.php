<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\ValidationException;
use FixPoint\Services\RequestStatusService;
use FixPoint\Tests\TestCase;

final class RequestStatusServiceTest extends TestCase
{
    public function testEveryTerminalStatusHasNoTransitions(): void
    {
        $service = new RequestStatusService();

        foreach (['completed', 'cancelled', 'rejected'] as $status) {
            $this->assertSame([], $service->allowedTransitions($status));
            $this->assertTrue($service->isTerminal($status));
        }
    }

    public function testFullTransitionMatrixIsExplicit(): void
    {
        $service = new RequestStatusService();

        $this->assertSame(['accepted', 'rejected', 'cancelled'], $service->allowedTransitions('new'));
        $this->assertSame(['diagnostics', 'cancelled'], $service->allowedTransitions('accepted'));
        $this->assertSame(
            ['awaiting_customer_approval', 'rejected'],
            $service->allowedTransitions('diagnostics')
        );
        $this->assertSame(['repairing', 'cancelled'], $service->allowedTransitions('awaiting_customer_approval'));
        $this->assertSame(['awaiting_parts', 'ready_for_pickup'], $service->allowedTransitions('repairing'));
        $this->assertSame(['repairing'], $service->allowedTransitions('awaiting_parts'));
        $this->assertSame(['completed'], $service->allowedTransitions('ready_for_pickup'));
    }

    public function testRepairCannotBeStartedWithoutTheCustomerDecision(): void
    {
        $service = new RequestStatusService();

        // The diagnostics stage must hand the job over to the customer; it may
        // never start the repair itself, and it may not park the job in
        // "awaiting parts" either, because that would be a second way around
        // the customer's decision.
        $this->assertFalse($service->canTransition('diagnostics', 'repairing'));
        $this->assertFalse($service->canTransition('diagnostics', 'awaiting_parts'));

        // The only way into a running repair is the customer's approval, or a
        // pause of a repair that the customer already approved.
        $this->assertTrue($service->canTransition('awaiting_customer_approval', 'repairing'));
        $this->assertTrue($service->canTransition('awaiting_parts', 'repairing'));
        $this->assertTrue($service->canTransition('repairing', 'awaiting_parts'));
    }

    public function testInvalidTransitionThrowsValidationException(): void
    {
        $service = new RequestStatusService();

        foreach ([
            ['new', 'completed'],
            ['completed', 'repairing'],
            ['ready_for_pickup', 'rejected'],
            ['unknown', 'accepted'],
            ['new', 'unknown'],
        ] as [$from, $to]) {
            try {
                $service->assertTransition($from, $to);
                $this->fail('Expected invalid transition to be rejected: ' . $from . ' -> ' . $to);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
        }
    }

    public function testValidTransitionDoesNotThrow(): void
    {
        $service = new RequestStatusService();

        $service->assertTransition('new', 'accepted');
        $service->assertTransition('diagnostics', 'awaiting_customer_approval');
        $service->assertTransition('awaiting_customer_approval', 'repairing');
        $service->assertTransition('repairing', 'ready_for_pickup');
        $service->assertTransition('ready_for_pickup', 'completed');

        $this->assertTrue($service->canTransition('awaiting_customer_approval', 'cancelled'));
        $this->assertFalse($service->canTransition('new', 'repairing'));
    }
}
