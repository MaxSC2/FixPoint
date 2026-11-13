<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\RequestRepository;
use FixPoint\Repositories\ServiceRepository;

/**
 * Customer-facing request rules: validation, ownership, list shaping,
 * comments and estimate decisions. Controllers translate results into HTTP;
 * views only render the supplied view model.
 */
final class RequestService
{
    private const PER_PAGE = 8;

    public function __construct(
        private readonly RequestRepository $requests,
        private readonly ServiceRepository $services,
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
     * Add a customer-visible comment. Internal notes are never created through
     * this route; staff get their own guarded flow later.
     *
     * @param array<string, mixed> $user
     * @throws ValidationException|HttpException
     */
    public function addComment(int $requestId, array $user, string $body): int
    {
        if ($this->findVisibleForUser($requestId, $user) === null) {
            throw new HttpException(404, 'Request not found');
        }

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

        return $this->requests->addComment($requestId, (int) $user['id'], $body, false);
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
