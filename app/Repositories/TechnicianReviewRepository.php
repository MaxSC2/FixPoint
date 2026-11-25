<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;
use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use PDOException;
use Throwable;

final class TechnicianReviewRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listPublishedForTechnician(int $technicianId, int $limit = 20): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT r.id, r.rating, r.review_text, r.created_at,
                    SUBSTRING_INDEX(c.full_name, " ", 1) AS customer_first_name,
                    q.public_number
             FROM technician_reviews r
             INNER JOIN users c ON c.id = r.customer_id
             INNER JOIN repair_requests q ON q.id = r.request_id
             WHERE r.technician_id = :technician_id AND r.status = "published"
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ' . max(1, min(50, $limit))
        );
        $statement->execute(['technician_id' => $technicianId]);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findForRequest(int $requestId, int $customerId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT r.id, r.request_id, r.technician_id, r.rating, r.review_text,
                    r.status, r.created_at, u.full_name AS technician_name
             FROM technician_reviews r
             INNER JOIN users u ON u.id = r.technician_id
             WHERE r.request_id = :request_id AND r.customer_id = :customer_id
             LIMIT 1'
        );
        $statement->execute(['request_id' => $requestId, 'customer_id' => $customerId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function createForCompletedRequest(
        int $requestId,
        int $customerId,
        int $rating,
        ?string $reviewText
    ): int {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare(
                'SELECT customer_id, technician_id, status
                 FROM repair_requests WHERE id = :request_id FOR UPDATE'
            );
            $lock->execute(['request_id' => $requestId]);
            $request = $lock->fetch();

            if (!is_array($request)) {
                throw new HttpException(404, 'Request not found');
            }
            if ((int) $request['customer_id'] !== $customerId) {
                throw new HttpException(404, 'Request not found');
            }
            if ((string) $request['status'] !== 'completed') {
                throw new ValidationException(['review' => 'Оставить отзыв можно только после завершения ремонта.']);
            }
            $technicianId = (int) ($request['technician_id'] ?? 0);
            if ($technicianId < 1) {
                throw new ValidationException(['review' => 'Для этой заявки ещё не назначен мастер.']);
            }

            $existing = $pdo->prepare(
                'SELECT id FROM technician_reviews WHERE request_id = :request_id LIMIT 1 FOR UPDATE'
            );
            $existing->execute(['request_id' => $requestId]);
            if ($existing->fetchColumn() !== false) {
                throw new ValidationException(['review' => 'Для этой заявки отзыв уже оставлен.']);
            }

            $insert = $pdo->prepare(
                'INSERT INTO technician_reviews
                    (request_id, customer_id, technician_id, rating, review_text, status)
                 VALUES (:request_id, :customer_id, :technician_id, :rating, :review_text, "published")'
            );
            $insert->execute([
                'request_id' => $requestId,
                'customer_id' => $customerId,
                'technician_id' => $technicianId,
                'rating' => $rating,
                'review_text' => $reviewText,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                throw new ValidationException(['review' => 'Для этой заявки отзыв уже оставлен.']);
            }
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForAdmin(): array
    {
        return $this->database->connection()->query(
            'SELECT r.id, r.request_id, r.rating, r.review_text, r.status,
                    r.moderation_reason, r.created_at, q.public_number,
                    c.full_name AS customer_name, t.full_name AS technician_name
             FROM technician_reviews r
             INNER JOIN repair_requests q ON q.id = r.request_id
             INNER JOIN users c ON c.id = r.customer_id
             INNER JOIN users t ON t.id = r.technician_id
             ORDER BY r.created_at DESC, r.id DESC'
        )->fetchAll();
    }

    public function moderate(int $reviewId, int $adminId, string $status, ?string $reason): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE technician_reviews
             SET status = :status, moderated_by = :moderated_by, moderation_reason = :reason
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $reviewId,
            'status' => $status,
            'moderated_by' => $adminId,
            'reason' => $reason,
        ]);
        if ($statement->rowCount() === 0) {
            $check = $this->database->connection()->prepare(
                'SELECT id FROM technician_reviews WHERE id = :id'
            );
            $check->execute(['id' => $reviewId]);
            if ($check->fetchColumn() === false) {
                throw new HttpException(404, 'Review not found');
            }
        }
    }

    /**
     * Correct the rating and the text of a review. The moderator and the
     * reason are stored next to the change, so the edit stays auditable.
     */
    public function updateContent(int $reviewId, int $rating, ?string $text, int $adminId, ?string $reason): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE technician_reviews
             SET rating = :rating, review_text = :review_text, moderated_by = :moderated_by,
                 moderation_reason = :reason
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $reviewId,
            'rating' => $rating,
            'review_text' => $text,
            'moderated_by' => $adminId,
            'reason' => $reason,
        ]);
        if ($statement->rowCount() === 0) {
            $check = $this->database->connection()->prepare(
                'SELECT id FROM technician_reviews WHERE id = :id'
            );
            $check->execute(['id' => $reviewId]);
            if ($check->fetchColumn() === false) {
                throw new HttpException(404, 'Review not found');
            }
        }
    }

    /**
     * Published reviews with the technician they belong to.
     *
     * @return list<array<string, mixed>>
     */
    public function publishedReviews(): array
    {
        return $this->database->connection()->query(
            'SELECT id, technician_id, rating FROM technician_reviews WHERE status = "published"'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $reviewId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, request_id, customer_id, technician_id, rating, review_text, status
             FROM technician_reviews WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $reviewId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
