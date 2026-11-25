<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Core\UploadedFile;
use FixPoint\Repositories\TechnicianProfileRepository;
use Throwable;

final class TechnicianProfileService
{
    public function __construct(
        private readonly TechnicianProfileRepository $profiles,
        private readonly TechnicianAvatarService $avatars
    )
    {
    }

    /** @return array<string, mixed> */
    public function directory(string $query = '', string $category = '', int $page = 1): array
    {
        $query = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $query));
        $query = mb_substr($query, 0, 100);
        $category = trim($category);
        $validSlugs = array_column($this->profiles->activeCategories(), 'slug');
        if ($category !== '' && !in_array($category, $validSlugs, true)) {
            $category = '';
        }
        $perPage = 12;
        $total = $this->profiles->countPublic($query, $category);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, $page));
        return [
            'items' => $this->profiles->listPublic($query, $category, $perPage, ($page - 1) * $perPage),
            'categories' => $this->profiles->activeCategories(),
            'filters' => ['q' => $query, 'category' => $category],
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => $perPage],
        ];
    }

    /** @return array<string, mixed>|null */
    public function publicProfile(int $technicianId): ?array
    {
        if ($technicianId < 1) {
            return null;
        }
        $profile = $this->profiles->findPublic($technicianId);
        if ($profile === null) {
            return null;
        }
        return $profile;
    }

    /**
     * Completed works of a technician for the public profile page.
     *
     * @return list<array<string, mixed>>
     */
    public function completedWorks(int $technicianId, int $limit = 6): array
    {
        if ($technicianId < 1) {
            return [];
        }

        return $this->profiles->completedWorks($technicianId, $limit);
    }

    /** @return list<array<string, mixed>> */
    public function leaderboard(int $limit = 5): array
    {
        return $this->profiles->leaderboard($limit);
    }

    /**
     * Specialists shown on the public home page.
     *
     * Masters with published ratings come first — they are the ones that
     * belong to the honour board. The rest of the public team fills the
     * remaining slots, so the section still shows real specialists with their
     * rating instead of an empty placeholder while the rating history is
     * still short.
     *
     * @return list<array<string, mixed>>
     */
    public function featured(int $limit = 5): array
    {
        $limit = max(1, min(5, $limit));

        $featured = $this->profiles->leaderboard($limit);
        if (count($featured) >= $limit) {
            return $featured;
        }

        $taken = array_map('intval', array_column($featured, 'technician_id'));
        foreach ($this->profiles->listPublic('', '', $limit * 2, 0) as $master) {
            if (count($featured) >= $limit) {
                break;
            }
            $technicianId = (int) ($master['technician_id'] ?? 0);
            if (in_array($technicianId, $taken, true)) {
                continue;
            }
            $featured[] = $master;
            $taken[] = $technicianId;
        }

        return $featured;
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    public function editorData(array $user): array
    {
        $this->assertTechnician($user);
        $profile = $this->profiles->findForEditor((int) $user['id']);
        if ($profile === null) {
            throw new HttpException(404, 'Technician profile not found');
        }
        $ids = array_filter(explode(',', (string) ($profile['specialty_ids'] ?? '')));
        $profile['specialty_ids'] = array_values(array_map('intval', $ids));
        return ['profile' => $profile, 'categories' => $this->profiles->activeCategories()];
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $input */
    public function update(array $user, array $input, ?UploadedFile $avatar = null): void
    {
        $this->assertTechnician($user);
        $errors = [];
        $headline = is_string($input['headline'] ?? null) ? trim($input['headline']) : '';
        $bio = is_string($input['bio'] ?? null) ? trim($input['bio']) : '';
        if (mb_strlen($headline) > 140) {
            $errors['headline'] = 'Заголовок профиля не должен превышать 140 символов.';
        }
        if (mb_strlen($bio) > 2000) {
            $errors['bio'] = 'Описание профиля не должно превышать 2000 символов.';
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $headline . $bio) === 1) {
            $errors['bio'] = 'Текст содержит недопустимые управляющие символы.';
        }

        $rawIds = $input['specialties'] ?? [];
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        if (count($rawIds) > 12) {
            $errors['specialties'] = 'Можно выбрать не более 12 направлений.';
        }
        $categoryIds = [];
        foreach ($rawIds as $rawId) {
            $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $errors['specialties'] = 'В списке направлений обнаружено некорректное значение.';
                continue;
            }
            $categoryIds[] = (int) $id;
        }
        $categoryIds = array_values(array_unique($categoryIds));
        $validIds = $this->profiles->validActiveCategoryIds($categoryIds);
        if (count($validIds) !== count($categoryIds)) {
            $errors['specialties'] = 'Выберите направления только из действующего каталога.';
        }

        $isPublic = in_array((string) ($input['is_public'] ?? ''), ['1', 'on', 'true'], true);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $technicianId = (int) $user['id'];
        $currentProfile = $this->profiles->findForEditor($technicianId);
        $oldAvatarKey = is_array($currentProfile) && is_string($currentProfile['avatar_storage_key'] ?? null)
            ? (string) $currentProfile['avatar_storage_key']
            : null;
        $newAvatarKey = null;

        if ($avatar !== null) {
            $newAvatarKey = $this->avatars->store($technicianId, $avatar);
        }

        try {
            $this->profiles->saveProfile(
                $technicianId,
                $headline === '' ? null : $headline,
                $bio === '' ? null : $bio,
                $isPublic,
                $validIds,
                $newAvatarKey,
                $avatar !== null
            );
        } catch (Throwable $exception) {
            if ($newAvatarKey !== null) {
                $this->avatars->delete($newAvatarKey);
            }
            throw $exception;
        }

        if ($newAvatarKey !== null && $oldAvatarKey !== null && $oldAvatarKey !== $newAvatarKey) {
            $this->avatars->delete($oldAvatarKey);
        }
    }

    /** @param array<string, mixed> $user @return list<array<string, mixed>> */
    public function adminList(array $user): array
    {
        $this->assertAdmin($user);
        return $this->profiles->listForAdmin();
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $input */
    public function moderateVisibility(array $user, int $technicianId, array $input): void
    {
        $this->assertAdmin($user);
        $rawVisibility = is_string($input['is_visible'] ?? null) ? $input['is_visible'] : '';
        if (!in_array($rawVisibility, ['0', '1'], true)) {
            throw new ValidationException(['is_visible' => 'Выберите допустимый статус видимости профиля.']);
        }

        $isVisible = $rawVisibility === '1';
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        if (!$isVisible && $reason === '') {
            throw new ValidationException(['reason' => 'Укажите причину скрытия профиля.']);
        }
        if (mb_strlen($reason) > 500) {
            throw new ValidationException(['reason' => 'Причина не должна превышать 500 символов.']);
        }

        $this->profiles->setAdminVisibility(
            $technicianId,
            (int) $user['id'],
            $isVisible,
            $reason === '' ? null : $reason
        );
    }

    /** @param array<string, mixed> $user */
    private function assertAdmin(array $user): void
    {
        if ((string) ($user['role'] ?? '') !== 'admin' || (int) ($user['id'] ?? 0) < 1) {
            throw new HttpException(403, 'Admin access required');
        }
    }

    /** @param array<string, mixed> $user */
    private function assertTechnician(array $user): void
    {
        if ((string) ($user['role'] ?? '') !== 'technician' || (int) ($user['id'] ?? 0) < 1) {
            throw new HttpException(403, 'Technician access required');
        }
    }
}
