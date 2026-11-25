<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Repositories\TechnicianProfileRepository;
use FixPoint\Repositories\TechnicianReviewRepository;

/**
 * Reputation model of the public team.
 *
 * The public pages rank the masters by the same weighted rating, so the admin
 * workspace can show what a moderation decision really changes: the average
 * rating of the master, the number of reviews and his place on the honour
 * board.
 */
final class TechnicianReputationService
{
    public function __construct(
        private readonly TechnicianProfileRepository $profiles,
        private readonly TechnicianReviewRepository $reviews
    ) {
    }

    /**
     * Current honour board with the rating and the place of every master.
     *
     * @return list<array<string, mixed>>
     */
    public function board(int $limit = 5): array
    {
        $ranking = $this->ranking(null);

        return array_slice($ranking, 0, max(1, $limit));
    }

    /**
     * What happens to the master when one review is hidden.
     *
     * @return array<string, mixed>
     */
    public function impactOfHiding(int $reviewId): array
    {
        $reviews = $this->reviews->publishedReviews();
        $target = null;
        foreach ($reviews as $review) {
            if ((int) $review['id'] === $reviewId) {
                $target = $review;
                break;
            }
        }
        if ($target === null) {
            return ['known' => false];
        }

        $technicianId = (int) $target['technician_id'];

        return [
            'known' => true,
            'technician_id' => $technicianId,
            'current' => $this->masterStats($this->ranking(null), $technicianId),
            'hidden' => $this->masterStats($this->ranking($reviewId), $technicianId),
        ];
    }

    /**
     * Ranking of the public team, optionally without one review.
     *
     * @return list<array<string, mixed>>
     */
    private function ranking(?int $excludedReviewId): array
    {
        $masters = $this->profiles->reputationSnapshot();
        $reviews = $this->reviews->publishedReviews();

        $totals = [];
        $globalCount = 0;
        $globalSum = 0.0;
        foreach ($reviews as $review) {
            if ($excludedReviewId !== null && (int) $review['id'] === $excludedReviewId) {
                continue;
            }
            $technicianId = (int) $review['technician_id'];
            $rating = (float) $review['rating'];
            $totals[$technicianId]['count'] = ($totals[$technicianId]['count'] ?? 0) + 1;
            $totals[$technicianId]['sum'] = ($totals[$technicianId]['sum'] ?? 0.0) + $rating;
            $globalCount++;
            $globalSum += $rating;
        }
        $globalAverage = $globalCount > 0 ? $globalSum / $globalCount : 0.0;

        $rows = [];
        foreach ($masters as $master) {
            $technicianId = (int) $master['technician_id'];
            $count = (int) ($totals[$technicianId]['count'] ?? 0);
            $sum = (float) ($totals[$technicianId]['sum'] ?? 0.0);
            $average = $count > 0 ? $sum / $count : null;
            $weighted = $count > 0
                ? (($average * $count) + ($globalAverage * 5)) / ($count + 5)
                : null;

            $rows[] = [
                'technician_id' => $technicianId,
                'full_name' => (string) $master['full_name'],
                'review_count' => $count,
                'average_rating' => $average,
                'weighted_rating' => $weighted,
                'completed_count' => (int) $master['completed_count'],
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            if ($left['weighted_rating'] === null || $right['weighted_rating'] === null) {
                if ($left['weighted_rating'] !== $right['weighted_rating']) {
                    return $left['weighted_rating'] === null ? 1 : -1;
                }
                $completed = $right['completed_count'] <=> $left['completed_count'];
                return $completed !== 0 ? $completed : $left['technician_id'] <=> $right['technician_id'];
            }
            if ($left['weighted_rating'] === $right['weighted_rating']) {
                $average = ($right['average_rating'] ?? 0.0) <=> ($left['average_rating'] ?? 0.0);
                if ($average !== 0) {
                    return $average;
                }
                $completed = $right['completed_count'] <=> $left['completed_count'];
                return $completed !== 0 ? $completed : $left['technician_id'] <=> $right['technician_id'];
            }

            return $right['weighted_rating'] <=> $left['weighted_rating'];
        });

        foreach ($rows as $index => $row) {
            $rows[$index]['position'] = $index + 1;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $ranking
     * @return array<string, mixed>|null
     */
    private function masterStats(array $ranking, int $technicianId): ?array
    {
        foreach ($ranking as $row) {
            if ((int) $row['technician_id'] === $technicianId) {
                return $row;
            }
        }

        return null;
    }
}
