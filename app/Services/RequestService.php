<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\RequestRepository;
use FixPoint\Repositories\ServiceRepository;
use FixPoint\Repositories\UserRepository;
use DateTimeImmutable;
use Throwable;

/**
 * Customer and staff request rules: validation, ownership, list shaping,
 * comments, estimate decisions and technician workflow. Controllers translate
 * results into HTTP; views only render the supplied view model.
 */
final class RequestService
{
    private const PER_PAGE = 8;

    /** @var list<string> */
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function __construct(
        private readonly RequestRepository $requests,
        private readonly ServiceRepository $services,
        private readonly UserRepository $users,
        private readonly RequestStatusService $statuses
    ) {
    }

    /**
     * @param array{q?: mixed, status?: mixed, sort?: mixed} $input
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array{q: string, status: string, sort: string}}
     */
    public function listForCustomer(int $customerId, array $input, int $page): array
    {
        $query = $this->scalar($input['q'] ?? '');
        $query = mb_substr(trim($query), 0, 100);
        $status = $this->scalar($input['status'] ?? '');
        if (!in_array($status, RequestStatusService::STATUSES, true)) {
            $status = '';
        }
        $sort = $this->scalar($input['sort'] ?? 'newest');
        if (!in_array($sort, ['newest', 'oldest', 'updated'], true)) {
            $sort = 'newest';
        }

        $filters = ['q' => $query, 'status' => $status, 'sort' => $sort];
        $total = $this->requests->countForCustomerFiltered($customerId, $filters);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($lastPage, $page));
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = $this->requests->listForCustomerFiltered($customerId, $filters, self::PER_PAGE, $offset);

        return [
            'items' => array_map(fn (array $row): array => $this->present($row), $rows),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => self::PER_PAGE,
                'total' => $total,
            ],
            'filters' => $filters,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function recentForCustomer(int $customerId, int $limit = 4): array
    {
        return array_map(
            fn (array $row): array => $this->present($row),
            $this->requests->listForCustomer($customerId, $limit)
        );
    }

    /**
     * Work queue for a technician (scoped to the current user) or an admin
     * (all requests). Filters mirror the technician template contract.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array{q: string, status: string, priority: string, sort: string}}
     */
    public function listQueue(array $user, array $input, int $page): array
    {
        $role = (string) ($user['role'] ?? '');
        if (!in_array($role, ['technician', 'admin'], true)) {
            throw new HttpException(403, 'Staff access required');
        }

        $query = mb_substr(trim($this->scalar($input['q'] ?? '')), 0, 100);
        $status = $this->scalar($input['status'] ?? '');
        if (!in_array($status, RequestStatusService::STATUSES, true)) {
            $status = '';
        }
        $priority = $this->scalar($input['priority'] ?? '');
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = '';
        }
        $sort = $this->scalar($input['sort'] ?? 'newest');
        if (!in_array($sort, ['newest', 'oldest', 'priority'], true)) {
            $sort = 'newest';
        }

        $filters = ['q' => $query, 'status' => $status, 'priority' => $priority, 'sort' => $sort];
        $technicianId = $role === 'technician' ? (int) ($user['id'] ?? 0) : null;
        $total = $this->requests->countForQueueFiltered($technicianId, $filters);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($lastPage, $page));
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = $this->requests->listForQueueFiltered($technicianId, $filters, self::PER_PAGE, $offset);

        return [
            'items' => array_map(fn (array $row): array => $this->present($row), $rows),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => self::PER_PAGE,
                'total' => $total,
            ],
            'filters' => $filters,
        ];
    }

    /**
     * Global request registry for the administrator: every request regardless
     * of owner or assignment. Filters mirror the admin template contract.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array{q: string, status: string, priority: string, sort: string}}
     */
    public function listForAdmin(array $user, array $input, int $page): array
    {
        if ((string) ($user['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }

        $query = mb_substr(trim($this->scalar($input['q'] ?? '')), 0, 100);
        $status = $this->scalar($input['status'] ?? '');
        if (!in_array($status, RequestStatusService::STATUSES, true)) {
            $status = '';
        }
        $priority = $this->scalar($input['priority'] ?? '');
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = '';
        }
        $sort = $this->scalar($input['sort'] ?? 'newest');
        if (!in_array($sort, ['newest', 'oldest', 'updated'], true)) {
            $sort = 'newest';
        }

        $filters = ['q' => $query, 'status' => $status, 'priority' => $priority, 'sort' => $sort];
        $total = $this->requests->countForAdminFiltered($filters);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($lastPage, $page));
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = $this->requests->listForAdminFiltered($filters, self::PER_PAGE, $offset);

        return [
            'items' => array_map(fn (array $row): array => $this->present($row), $rows),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => self::PER_PAGE,
                'total' => $total,
            ],
            'filters' => $filters,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function servicesForSelect(): array
    {
        return $this->services->listActiveForSelect();
    }

    /**
     * Validate and create a request for the authenticated customer.
     *
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    public function create(int $customerId, array $input): int
    {
        $errors = [];

        $deviceType = $this->scalar($input['device_type'] ?? '');
        if ($deviceType === '') {
            $errors['device_type'] = 'Укажите тип устройства.';
        } elseif ($this->hasControlCharacters($deviceType) || mb_strlen($deviceType) > 100) {
            $errors['device_type'] = 'Тип устройства содержит недопустимые символы или слишком длинный.';
        }

        $brand = $this->scalar($input['device_brand'] ?? '');
        if ($brand !== '' && ($this->hasControlCharacters($brand) || mb_strlen($brand) > 100)) {
            $errors['device_brand'] = 'Производитель содержит недопустимые символы или слишком длинный.';
        }

        $model = $this->scalar($input['device_model'] ?? '');
        if ($model !== '' && ($this->hasControlCharacters($model) || mb_strlen($model) > 160)) {
            $errors['device_model'] = 'Модель содержит недопустимые символы или слишком длинная.';
        }

        $serviceId = null;
        $rawServiceId = $this->scalar($input['service_id'] ?? '');
        if ($rawServiceId !== '') {
            $validated = filter_var($rawServiceId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($validated === false) {
                $errors['service_id'] = 'Выбранная услуга недоступна.';
            } else {
                $service = $this->services->findActiveById((int) $validated);
                if ($service === null) {
                    $errors['service_id'] = 'Выбранная услуга недоступна.';
                } else {
                    $serviceId = (int) $validated;
                }
            }
        }

        $problem = $this->scalar($input['problem_description'] ?? '');
        $problemLength = mb_strlen($problem);
        if ($problemLength < 10) {
            $errors['problem_description'] = 'Опишите проблему подробнее — не менее 10 символов.';
        } elseif ($problemLength > 5000) {
            $errors['problem_description'] = 'Описание проблемы слишком длинное.';
        } elseif ($this->hasControlCharacters($problem)) {
            $errors['problem_description'] = 'Описание содержит недопустимые символы.';
        }

        $contact = $this->scalar($input['customer_contact'] ?? '');
        if ($contact === '') {
            $errors['customer_contact'] = 'Укажите контакт для связи.';
        } elseif ($this->hasControlCharacters($contact) || mb_strlen($contact) < 3 || mb_strlen($contact) > 80) {
            $errors['customer_contact'] = 'Укажите контакт длиной от 3 до 80 символов.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->requests->createForCustomer($customerId, [
            'device_type' => $deviceType,
            'device_brand' => $brand === '' ? null : $brand,
            'device_model' => $model === '' ? null : $model,
            'service_id' => $serviceId,
            'problem_description' => $problem,
            'customer_contact' => $contact,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findVisibleForUser(int $requestId, array $user): ?array
    {
        $request = $this->requests->findById($requestId);
        if ($request === null) {
            return null;
        }

        $role = (string) ($user['role'] ?? '');
        $userId = (int) ($user['id'] ?? 0);

        if ($role === 'admin') {
            return $request;
        }
        if ($role === 'technician' && (int) ($request['technician_id'] ?? 0) === $userId) {
            return $request;
        }
        if ($role === 'customer' && (int) ($request['customer_id'] ?? 0) === $userId) {
            return $request;
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public function history(int $requestId): array
    {
        return $this->requests->historyForRequest($requestId);
    }

    /** @return list<array<string, mixed>> */
    public function visibleComments(int $requestId): array
    {
        return $this->requests->commentsForRequest($requestId, false);
    }

    /** @param array<string, mixed> $user @return array<string, bool> */
    public function permissionsFor(array $request, array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $role = (string) ($user['role'] ?? '');
        $isOwner = $role === 'customer' && (int) ($request['customer_id'] ?? 0) === $userId;
        $isAssigned = $role === 'technician' && (int) ($request['technician_id'] ?? 0) === $userId;
        $isAdmin = $role === 'admin';

        return [
            'can_comment' => $isOwner || $isAssigned || $isAdmin,
            'can_decide_estimate' => $isOwner
                && (string) ($request['status'] ?? '') === 'awaiting_customer_approval',
        ];
    }

    /**
     * Staff detail lookup: assigned technician or admin only. A technician who
     * is not assigned gets null, which controllers expose as 404.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function findForStaff(int $requestId, array $user): ?array
    {
        if (!in_array((string) ($user['role'] ?? ''), ['technician', 'admin'], true)) {
            return null;
        }

        return $this->findVisibleForUser($requestId, $user);
    }

    /**
     * Admin-only detail lookup used by the assignment screen.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function findForAdmin(int $requestId, array $user): ?array
    {
        if ((string) ($user['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }

        return $this->requests->findById($requestId);
    }

    /**
     * Active technicians are the only valid assignees. The list is deliberately
     * empty-safe: no demo users are added when the database has none.
     *
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function techniciansForAssignment(array $user): array
    {
        if ((string) ($user['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }

        return $this->users->listByRole('technician', true);
    }

    /**
     * Assign an active technician to a non-terminal request. Status is not
     * changed here; the technician still advances the workflow explicitly.
     *
     * @param array<string, mixed> $user
     * @throws ValidationException|HttpException
     */
    public function assignTechnician(int $requestId, array $user, mixed $technicianInput): void
    {
        $request = $this->findForAdmin($requestId, $user);
        if ($request === null) {
            throw new HttpException(404, 'Request not found');
        }
        if ($this->statuses->isTerminal((string) ($request['status'] ?? ''))) {
            throw new ValidationException([
                'general' => 'Завершённую заявку нельзя переназначить.',
            ]);
        }

        $raw = $this->scalar($technicianInput);
        if ($raw === '') {
            throw new ValidationException(['technician_id' => 'Выберите мастера из списка.']);
        }

        $validated = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            throw new ValidationException(['technician_id' => 'Выберите мастера из списка.']);
        }

        $technician = $this->users->findById((int) $validated);
        if ($technician === null
            || (string) ($technician['role'] ?? '') !== 'technician'
            || (int) ($technician['is_active'] ?? 0) !== 1
        ) {
            throw new ValidationException([
                'technician_id' => 'Пользователь недоступен для назначения: нужен активный мастер.',
            ]);
        }

        if ((int) ($request['technician_id'] ?? 0) === (int) $validated) {
            return;
        }

        if (!$this->requests->assignTechnician($requestId, (int) $validated)) {
            throw new ValidationException([
                'general' => 'Заявка уже изменилась. Обновите страницу и попробуйте снова.',
            ]);
        }
    }

    /**
     * Staff comment list including internal notes. The caller must have passed
     * findForStaff first; the check is repeated here because internal notes are
     * the most sensitive part of the view model.
     *
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function staffComments(int $requestId, array $user): array
    {
        if ($this->findForStaff($requestId, $user) === null) {
            throw new HttpException(404, 'Request not found');
        }

        return $this->requests->commentsForRequest($requestId, true);
    }

    /**
     * Presentation flags for the technician workspace. Backend checks in
     * updateDiagnosis/changeStatus remain authoritative.
     *
     * @param array<string, mixed> $request
     * @param array<string, mixed> $user
     * @return array{can_edit_diagnosis: bool, can_change_status: bool, can_comment: bool}
     */
    public function staffPermissions(array $request, array $user): array
    {
        $role = (string) ($user['role'] ?? '');
        $isAdmin = $role === 'admin';
        $isAssigned = $role === 'technician'
            && (int) ($request['technician_id'] ?? 0) === (int) ($user['id'] ?? 0);
        $canAct = ($isAdmin || $isAssigned)
            && !$this->statuses->isTerminal((string) ($request['status'] ?? ''));

        return [
            'can_edit_diagnosis' => $canAct,
            'can_change_status' => $canAct
                && $this->statuses->allowedTransitions((string) ($request['status'] ?? '')) !== [],
            'can_comment' => $isAdmin || $isAssigned,
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $user
     * @return list<string>
     */
    public function allowedTransitionsFor(array $request, array $user): array
    {
        if (!$this->staffPermissions($request, $user)['can_change_status']) {
            return [];
        }

        return $this->statuses->allowedTransitions((string) ($request['status'] ?? ''));
    }

    /**
     * Save diagnosis and estimate. Terminal requests are read-only.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @throws ValidationException|HttpException
     */
    public function updateDiagnosis(int $requestId, array $user, array $input): void
    {
        $request = $this->findForStaff($requestId, $user);
        if ($request === null) {
            throw new HttpException(404, 'Request not found');
        }
        if ($this->statuses->isTerminal((string) $request['status'])) {
            throw new ValidationException([
                'general' => 'Завершённую заявку нельзя редактировать.',
            ]);
        }

        $errors = [];

        $diagnostic = $this->scalar($input['diagnostic_result'] ?? '');
        $diagnosticLength = mb_strlen($diagnostic);
        if ($diagnosticLength < 10) {
            $errors['diagnostic_result'] = 'Опишите результат диагностики — не менее 10 символов.';
        } elseif ($diagnosticLength > 10000) {
            $errors['diagnostic_result'] = 'Результат диагностики слишком длинный.';
        } elseif ($this->hasControlCharacters($diagnostic)) {
            $errors['diagnostic_result'] = 'Результат диагностики содержит недопустимые символы.';
        }

        $estimatedCost = null;
        $rawCost = $this->scalar($input['estimated_cost'] ?? '');
        if ($rawCost !== '') {
            if (!is_numeric($rawCost)) {
                $errors['estimated_cost'] = 'Укажите стоимость числом.';
            } else {
                $amount = (float) $rawCost;
                if ($amount < 0 || $amount > 99999999.99) {
                    $errors['estimated_cost'] = 'Стоимость должна быть в диапазоне от 0 до 99 999 999,99.';
                } else {
                    $estimatedCost = number_format($amount, 2, '.', '');
                }
            }
        }

        $estimatedReadyAt = null;
        $rawReadyAt = $this->scalar($input['estimated_ready_at'] ?? '');
        if ($rawReadyAt !== '') {
            try {
                $estimatedReadyAt = (new DateTimeImmutable($rawReadyAt))->format('Y-m-d H:i:s');
            } catch (Throwable) {
                $errors['estimated_ready_at'] = 'Укажите корректную дату готовности.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->requests->updateDiagnosis($requestId, $diagnostic, $estimatedCost, $estimatedReadyAt);
    }

    /**
     * Apply a permitted status transition with an audit trail. The transition
     * matrix and the conditional UPDATE both guard against stale pages.
     *
     * @param array<string, mixed> $user
     * @throws ValidationException|HttpException
     */
    public function changeStatus(int $requestId, array $user, string $toStatus, string $reason): void
    {
        $request = $this->findForStaff($requestId, $user);
        if ($request === null) {
            throw new HttpException(404, 'Request not found');
        }

        $fromStatus = (string) $request['status'];
        if ($this->statuses->isTerminal($fromStatus)) {
            throw new ValidationException([
                'general' => 'Завершённую заявку нельзя изменить.',
            ]);
        }
        $this->statuses->assertTransition($fromStatus, $toStatus);

        $reason = trim($reason);
        if (mb_strlen($reason) > 500) {
            throw new ValidationException(['reason' => 'Комментарий к переходу слишком длинный (максимум 500 символов).']);
        }
        if ($reason !== '' && $this->hasControlCharacters($reason)) {
            throw new ValidationException(['reason' => 'Комментарий содержит недопустимые символы.']);
        }

        $changed = $this->requests->transitionStatus(
            $requestId,
            $fromStatus,
            $toStatus,
            (int) ($user['id'] ?? 0),
            $reason === '' ? null : $reason
        );

        if (!$changed) {
            throw new ValidationException([
                'general' => 'Статус заявки уже изменился. Обновите страницу и попробуйте снова.',
            ]);
        }
    }

    /**
     * Add a comment. Customers can only write public comments; an assigned
     * technician or an admin may mark a note internal. The flag is ignored for
     * any other actor, and internal notes never reach customer view models.
     *
     * @param array<string, mixed> $user
     * @throws ValidationException|HttpException
     */
    public function addComment(int $requestId, array $user, string $body, bool $internal = false): int
    {
        $request = $this->findVisibleForUser($requestId, $user);
        if ($request === null) {
            throw new HttpException(404, 'Request not found');
        }

        $role = (string) ($user['role'] ?? '');
        $isStaff = $role === 'admin'
            || ($role === 'technician'
                && (int) ($request['technician_id'] ?? 0) === (int) ($user['id'] ?? 0));
        $internal = $internal && $isStaff;

        $body = trim($body);
        if ($body === '') {
            throw new ValidationException(['body' => 'Введите комментарий.']);
        }
        if (mb_strlen($body) > 3000) {
            throw new ValidationException(['body' => 'Комментарий слишком длинный (максимум 3000 символов).']);
        }
        if ($this->hasControlCharacters($body)) {
            throw new ValidationException(['body' => 'Комментарий содержит недопустимые символы.']);
        }

        return $this->requests->addComment($requestId, (int) $user['id'], $body, $internal);
    }

    /**
     * Approve or reject an estimate. Approval moves the request to repair;
     * rejection closes it as cancelled, matching the transition matrix.
     *
     * @param array<string, mixed> $user
     * @throws ValidationException|HttpException
     */
    public function decideEstimate(int $requestId, array $user, string $decision): void
    {
        $request = $this->requests->findById($requestId);
        if ($request === null
            || (string) ($user['role'] ?? '') !== 'customer'
            || (int) ($request['customer_id'] ?? 0) !== (int) ($user['id'] ?? 0)
        ) {
            throw new HttpException(404, 'Request not found');
        }

        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new ValidationException(['general' => 'Выберите решение по смете.']);
        }

        if ((string) $request['status'] !== 'awaiting_customer_approval') {
            throw new ValidationException([
                'general' => 'Смета уже обработана или заявка находится в другом статусе.',
            ]);
        }

        $toStatus = $decision === 'approve' ? 'repairing' : 'cancelled';
        $reason = $decision === 'approve'
            ? 'Клиент согласовал смету.'
            : 'Клиент отклонил смету.';

        $this->statuses->assertTransition((string) $request['status'], $toStatus);

        $changed = $this->requests->transitionStatus(
            $requestId,
            (string) $request['status'],
            $toStatus,
            (int) $user['id'],
            $reason
        );

        if (!$changed) {
            throw new ValidationException([
                'general' => 'Статус заявки уже изменился. Обновите страницу и попробуйте снова.',
            ]);
        }
    }

    /**
     * Convert flat repository aliases into the nested view model from
     * docs/10-INTEGRATION-CONTRACT.md.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $row['customer'] = [
            'id' => (int) ($row['customer_id'] ?? 0),
            'full_name' => (string) ($row['customer_name'] ?? ''),
        ];
        $row['technician'] = isset($row['technician_id'])
            ? [
                'id' => (int) $row['technician_id'],
                'full_name' => (string) ($row['technician_name'] ?? ''),
            ]
            : null;
        $row['service'] = isset($row['service_id'])
            ? [
                'id' => (int) $row['service_id'],
                'name' => (string) ($row['service_name'] ?? ''),
                'slug' => (string) ($row['service_slug'] ?? ''),
            ]
            : null;

        return $row;
    }

    private function scalar(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function hasControlCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1;
    }
}
