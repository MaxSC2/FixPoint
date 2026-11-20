<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\TechnicianReviewRepository;

final class TechnicianReviewService
{
    public function __construct(private readonly TechnicianReviewRepository $reviews)
    {
    }

    /** @return list<array<string, mixed>> */
    public function publicReviews(int $technicianId, int $limit = 20): array
    {
        return $this->reviews->listPublishedForTechnician($technicianId, $limit);
    }

    /** @return array<string, mixed>|null */
    public function forRequest(int $requestId, int $customerId): ?array
    {
        return $this->reviews->findForRequest($requestId, $customerId);
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $input */
    public function submit(array $user, int $requestId, array $input): int
    {
        $this->assertCustomer($user);
        $rawRating = $input['rating'] ?? null;
        $rating = filter_var($rawRating, FILTER_VALIDATE_INT);
        if ($rating === false || $rating < 1 || $rating > 5) {
            throw new ValidationException(['rating' => 'Выберите оценку от 1 до 5.']);
        }

        $reviewText = is_string($input['review_text'] ?? null) ? trim($input['review_text']) : '';
        $reviewText = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $reviewText);
        if (mb_strlen($reviewText) > 1500) {
            throw new ValidationException(['review_text' => 'Отзыв не должен превышать 1500 символов.']);
        }

        return $this->reviews->createForCompletedRequest(
            $requestId,
            (int) $user['id'],
            $rating,
            $reviewText === '' ? null : $reviewText
        );
    }

    /** @param array<string, mixed> $user @return list<array<string, mixed>> */
    public function adminList(array $user): array
    {
        $this->assertAdmin($user);
        return $this->reviews->listForAdmin();
    }

    /** @param array<string, mixed> $user */
    public function moderate(array $user, int $reviewId, array $input): void
    {
        $this->assertAdmin($user);
        $status = is_string($input['status'] ?? null) ? $input['status'] : '';
        if (!in_array($status, ['published', 'hidden'], true)) {
            throw new ValidationException(['status' => 'Выберите допустимый статус модерации.']);
        }
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        if ($status === 'hidden' && $reason === '') {
            throw new ValidationException(['reason' => 'Укажите причину скрытия отзыва.']);
        }
        if (mb_strlen($reason) > 500) {
            throw new ValidationException(['reason' => 'Причина модерации не должна превышать 500 символов.']);
        }
        $this->reviews->moderate(
            $reviewId,
            (int) $user['id'],
            $status,
            $reason === '' ? null : $reason
        );
    }

    /** @param array<string, mixed> $user */
    private function assertCustomer(array $user): void
    {
        if ((string) ($user['role'] ?? '') !== 'customer' || (int) ($user['id'] ?? 0) < 1) {
            throw new HttpException(403, 'Customer access required');
        }
    }

    /** @param array<string, mixed> $user */
    private function assertAdmin(array $user): void
    {
        if ((string) ($user['role'] ?? '') !== 'admin' || (int) ($user['id'] ?? 0) < 1) {
            throw new HttpException(403, 'Admin access required');
        }
    }
}
