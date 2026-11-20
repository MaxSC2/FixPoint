<?php

declare(strict_types=1);

namespace FixPoint\Repositories;

use FixPoint\Core\Database;
use PDO;
use Throwable;

final class TechnicianProfileRepository
{
    private const PROFILE_SELECT = 'SELECT
            u.id AS technician_id,
            u.full_name,
            p.headline,
            p.bio,
            p.avatar_storage_key,
            COALESCE(p.is_public, 1) AS is_public,
            COALESCE(p.admin_is_visible, 1) AS admin_is_visible,
            COALESCE(completed.completed_count, 0) AS completed_count,
            COALESCE(ratings.review_count, 0) AS review_count,
            ROUND(ratings.average_rating, 2) AS average_rating,
            CASE
                WHEN COALESCE(ratings.review_count, 0) > 0 THEN
                    (
                        ratings.average_rating * ratings.review_count
                        + COALESCE(
                            (SELECT AVG(global_review.rating)
                             FROM technician_reviews global_review
                             WHERE global_review.status = "published"),
                            ratings.average_rating
                        ) * 5
                    ) / (ratings.review_count + 5)
                ELSE NULL
            END AS weighted_rating,
            ratings.last_review_at,
            COALESCE(specialties.specialties, "") AS specialties
        FROM users u
        LEFT JOIN technician_profiles p ON p.technician_id = u.id
        LEFT JOIN (
            SELECT technician_id, COUNT(*) AS completed_count
            FROM repair_requests
            WHERE status = "completed"
            GROUP BY technician_id
        ) completed ON completed.technician_id = u.id
        LEFT JOIN (
            SELECT technician_id,
                   COUNT(*) AS review_count,
                   AVG(rating) AS average_rating,
                   MAX(created_at) AS last_review_at
            FROM technician_reviews
            WHERE status = "published"
            GROUP BY technician_id
        ) ratings ON ratings.technician_id = u.id
        LEFT JOIN (
            SELECT ps.technician_id,
                   GROUP_CONCAT(sc.name ORDER BY sc.sort_order, sc.name SEPARATOR " · ") AS specialties
            FROM technician_profile_specialties ps
            INNER JOIN service_categories sc ON sc.id = ps.category_id AND sc.is_active = 1
            GROUP BY ps.technician_id
        ) specialties ON specialties.technician_id = u.id';

    public function __construct(private readonly Database $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listPublic(string $query, string $categorySlug, int $limit, int $offset): array
    {
        [$where, $params] = $this->publicFilters($query, $categorySlug);
        $sql = self::PROFILE_SELECT . ' WHERE ' . $where
            . ' ORDER BY weighted_rating IS NULL ASC, weighted_rating DESC, completed_count DESC, u.full_name ASC'
            . ' LIMIT ' . max(1, min(30, $limit)) . ' OFFSET ' . max(0, $offset);
        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function countPublic(string $query, string $categorySlug): int
    {
        [$where, $params] = $this->publicFilters($query, $categorySlug);
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(DISTINCT u.id)
             FROM users u
             LEFT JOIN technician_profiles p ON p.technician_id = u.id
             WHERE ' . $where
        );
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findPublic(int $technicianId): ?array
    {
        $statement = $this->database->connection()->prepare(
            self::PROFILE_SELECT . '
             WHERE u.id = :technician_id
               AND u.role = "technician"
               AND u.is_active = 1
               AND COALESCE(p.is_public, 1) = 1
               AND COALESCE(p.admin_is_visible, 1) = 1
             LIMIT 1'
        );
        $statement->execute(['technician_id' => $technicianId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function leaderboard(int $limit): array
    {
        $statement = $this->database->connection()->prepare(
            self::PROFILE_SELECT . '
             WHERE u.role = "technician"
               AND u.is_active = 1
               AND COALESCE(p.is_public, 1) = 1
               AND COALESCE(p.admin_is_visible, 1) = 1
               AND COALESCE(ratings.review_count, 0) >= 3
             ORDER BY weighted_rating DESC, average_rating DESC, completed_count DESC,
                      last_review_at DESC, u.id ASC
             LIMIT ' . max(1, min(5, $limit))
        );
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listForAdmin(): array
    {
        return $this->database->connection()->query(
            'SELECT u.id AS technician_id, u.full_name, u.is_active,
                    COALESCE(p.is_public, 1) AS owner_is_public,
                    COALESCE(p.admin_is_visible, 1) AS admin_is_visible,
                    p.admin_moderation_reason,
                    COALESCE(r.review_count, 0) AS review_count,
                    ROUND(r.average_rating, 2) AS average_rating,
                    COALESCE(c.completed_count, 0) AS completed_count
             FROM users u
             LEFT JOIN technician_profiles p ON p.technician_id = u.id
             LEFT JOIN (
                SELECT technician_id, COUNT(*) AS review_count, AVG(rating) AS average_rating
                FROM technician_reviews WHERE status = "published" GROUP BY technician_id
             ) r ON r.technician_id = u.id
             LEFT JOIN (
                SELECT technician_id, COUNT(*) AS completed_count
                FROM repair_requests WHERE status = "completed" GROUP BY technician_id
             ) c ON c.technician_id = u.id
             WHERE u.role = "technician"
             ORDER BY u.is_active DESC, COALESCE(p.admin_is_visible, 1) DESC, u.full_name ASC'
        )->fetchAll();
    }

    public function setAdminVisibility(int $technicianId, int $adminId, bool $isVisible, ?string $reason): void
    {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();
        try {
            $user = $pdo->prepare(
                'SELECT id FROM users WHERE id = :technician_id AND role = "technician" FOR UPDATE'
            );
            $user->execute(['technician_id' => $technicianId]);
            if ($user->fetchColumn() === false) {
                throw new \FixPoint\Core\HttpException(404, 'Technician not found');
            }

            $pdo->prepare(
                'INSERT INTO technician_profiles
                    (technician_id, admin_is_visible, admin_moderation_reason, admin_moderated_by)
                 VALUES (:technician_id, :is_visible, :reason, :moderator_id)
                 ON DUPLICATE KEY UPDATE
                    admin_is_visible = VALUES(admin_is_visible),
                    admin_moderation_reason = VALUES(admin_moderation_reason),
                    admin_moderated_by = VALUES(admin_moderated_by)'
            )->execute([
                'technician_id' => $technicianId,
                'is_visible' => $isVisible ? 1 : 0,
                'reason' => $isVisible ? null : $reason,
                'moderator_id' => $adminId,
            ]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    public function findForEditor(int $technicianId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT u.id AS technician_id, u.full_name,
                    p.headline, p.bio, p.avatar_storage_key, COALESCE(p.is_public, 1) AS is_public,
                    GROUP_CONCAT(ps.category_id ORDER BY ps.category_id) AS specialty_ids
             FROM users u
             LEFT JOIN technician_profiles p ON p.technician_id = u.id
             LEFT JOIN technician_profile_specialties ps ON ps.technician_id = u.id
             WHERE u.id = :technician_id AND u.role = "technician"
             GROUP BY u.id, u.full_name, p.headline, p.bio, p.is_public
             LIMIT 1'
        );
        $statement->execute(['technician_id' => $technicianId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array{id: int, name: string, slug: string}> */
    public function activeCategories(): array
    {
        return $this->database->connection()->query(
            'SELECT id, name, slug FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name'
        )->fetchAll();
    }

    /** @param list<int> $categoryIds @return list<int> */
    public function validActiveCategoryIds(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach (array_values(array_unique($categoryIds)) as $index => $id) {
            $key = 'category_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $statement = $this->database->connection()->prepare(
            'SELECT id FROM service_categories WHERE is_active = 1 AND id IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($params);
        return array_map('intval', array_column($statement->fetchAll(), 'id'));
    }

    /** @param list<int> $categoryIds */
    public function saveProfile(
        int $technicianId,
        ?string $headline,
        ?string $bio,
        bool $isPublic,
        array $categoryIds,
        ?string $avatarStorageKey = null,
        bool $replaceAvatar = false
    ): void {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO technician_profiles
                    (technician_id, headline, bio, is_public, avatar_storage_key)
                 VALUES (:technician_id, :headline, :bio, :is_public, :avatar_storage_key)
                 ON DUPLICATE KEY UPDATE headline = VALUES(headline), bio = VALUES(bio),
                                         is_public = VALUES(is_public),
                                         avatar_storage_key = IF(:replace_avatar = 1, VALUES(avatar_storage_key), avatar_storage_key)'
            )->execute([
                'technician_id' => $technicianId,
                'headline' => $headline,
                'bio' => $bio,
                'is_public' => $isPublic ? 1 : 0,
                'avatar_storage_key' => $avatarStorageKey,
                'replace_avatar' => $replaceAvatar ? 1 : 0,
            ]);

            $pdo->prepare(
                'DELETE FROM technician_profile_specialties WHERE technician_id = :technician_id'
            )->execute(['technician_id' => $technicianId]);

            $insert = $pdo->prepare(
                'INSERT INTO technician_profile_specialties (technician_id, category_id)
                 VALUES (:technician_id, :category_id)'
            );
            foreach ($categoryIds as $categoryId) {
                $insert->execute(['technician_id' => $technicianId, 'category_id' => $categoryId]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function publicFilters(string $query, string $categorySlug): array
    {
        $where = [
            'u.role = "technician"',
            'u.is_active = 1',
            'COALESCE(p.is_public, 1) = 1',
            'COALESCE(p.admin_is_visible, 1) = 1',
        ];
        $params = [];
        if ($categorySlug !== '') {
            $where[] = 'EXISTS (
                SELECT 1
                FROM technician_profile_specialties filter_ps
                INNER JOIN service_categories filter_sc ON filter_sc.id = filter_ps.category_id
                WHERE filter_ps.technician_id = u.id
                  AND filter_sc.slug = :category_slug
                  AND filter_sc.is_active = 1
            )';
            $params['category_slug'] = $categorySlug;
        }
        if ($query !== '') {
            $where[] = '(u.full_name LIKE :name_term
                OR p.headline LIKE :headline_term
                OR p.bio LIKE :bio_term
                OR EXISTS (
                    SELECT 1
                    FROM technician_profile_specialties search_ps
                    INNER JOIN service_categories search_sc ON search_sc.id = search_ps.category_id
                    WHERE search_ps.technician_id = u.id
                      AND search_sc.name LIKE :specialty_term
                      AND search_sc.is_active = 1
                ))';
            $term = '%' . $query . '%';
            $params['name_term'] = $term;
            $params['headline_term'] = $term;
            $params['bio_term'] = $term;
            $params['specialty_term'] = $term;
        }
        return [implode(' AND ', $where), $params];
    }
}
