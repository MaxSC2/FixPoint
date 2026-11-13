<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\ServiceCategoryRepository;
use FixPoint\Repositories\ServiceRepository;
use PDOException;

/**
 * Administrator catalogue management for services and categories. Validation
 * lives here so controllers only translate input into redirects and flashes.
 * Public visibility still requires both the service and its category to be
 * active; the repositories enforce that in every public query.
 */
final class AdminCatalogService
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceCategoryRepository $categories
    ) {
    }

    /**
     * @param array<string, mixed> $actor
     * @return list<array<string, mixed>>
     */
    public function listServices(array $actor): array
    {
        $this->assertAdmin($actor);

        return $this->services->listAllForAdmin();
    }

    /**
     * @param array<string, mixed> $actor
     * @return array<string, mixed>|null
     */
    public function findService(int $id, array $actor): ?array
    {
        $this->assertAdmin($actor);

        return $this->services->findByIdForAdmin($id);
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @throws ValidationException|HttpException
     */
    public function createService(array $actor, array $input): int
    {
        $this->assertAdmin($actor);
        $data = $this->validateService($input, null, true);

        try {
            return $this->services->createForAdmin(
                $data['category_id'],
                $data['name'],
                $data['slug'],
                $data['short_description'],
                $data['description'],
                $data['price_from'],
                $data['estimated_minutes'],
                $data['is_active']
            );
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ValidationException(['slug' => 'Услуга с таким slug уже существует.']);
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @throws ValidationException|HttpException
     */
    public function updateService(int $id, array $actor, array $input): void
    {
        $this->assertAdmin($actor);
        $existing = $this->services->findByIdForAdmin($id);
        if ($existing === null) {
            throw new HttpException(404, 'Service not found');
        }

        $data = $this->validateService($input, $id, (int) ($existing['is_active'] ?? 0) === 1);

        try {
            $this->services->updateForAdmin(
                $id,
                $data['category_id'],
                $data['name'],
                $data['slug'],
                $data['short_description'],
                $data['description'],
                $data['price_from'],
                $data['estimated_minutes'],
                $data['is_active']
            );
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ValidationException(['slug' => 'Услуга с таким slug уже существует.']);
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $actor
     * @return list<array<string, mixed>>
     */
    public function listCategories(array $actor): array
    {
        $this->assertAdmin($actor);

        return $this->categories->listAllForAdmin();
    }

    /**
     * @param array<string, mixed> $actor
     * @return array<string, mixed>|null
     */
    public function findCategory(int $id, array $actor): ?array
    {
        $this->assertAdmin($actor);

        return $this->categories->findById($id);
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @throws ValidationException|HttpException
     */
    public function createCategory(array $actor, array $input): int
    {
        $this->assertAdmin($actor);
        $data = $this->validateCategory($input, null, true);

        try {
            return $this->categories->create(
                $data['name'],
                $data['slug'],
                $data['description'],
                $data['sort_order'],
                $data['is_active']
            );
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ValidationException(['slug' => 'Категория с таким slug уже существует.']);
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @throws ValidationException|HttpException
     */
    public function updateCategory(int $id, array $actor, array $input): void
    {
        $this->assertAdmin($actor);
        $existing = $this->categories->findById($id);
        if ($existing === null) {
            throw new HttpException(404, 'Category not found');
        }

        $data = $this->validateCategory($input, $id, (int) ($existing['is_active'] ?? 0) === 1);

        try {
            $this->categories->update(
                $id,
                $data['name'],
                $data['slug'],
                $data['description'],
                $data['sort_order'],
                $data['is_active']
            );
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ValidationException(['slug' => 'Категория с таким slug уже существует.']);
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   category_id: int, name: string, slug: string,
     *   short_description: ?string, description: ?string,
     *   price_from: ?string, estimated_minutes: ?int, is_active: bool
     * }
     * @throws ValidationException
     */
    private function validateService(array $input, ?int $excludeId, bool $currentActive): array
    {
        $errors = [];

        $categoryRaw = $this->scalar($input['category_id'] ?? '');
        $categoryId = filter_var($categoryRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($categoryId === false) {
            $errors['category_id'] = 'Выберите категорию услуги.';
        } elseif ($this->categories->findById((int) $categoryId) === null) {
            $errors['category_id'] = 'Выбранная категория не существует.';
        }

        $name = $this->scalar($input['name'] ?? '');
        if ($this->hasControlCharacters($name)) {
            $errors['name'] = 'Название содержит недопустимые символы.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            $errors['name'] = 'Укажите название длиной от 2 до 160 символов.';
        }

        $slug = $this->normaliseSlug($this->scalar($input['slug'] ?? ''));
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $errors['slug'] = 'Slug: только строчные латинские буквы, цифры и дефисы между словами.';
        } elseif (mb_strlen($slug) < 2 || mb_strlen($slug) > 180) {
            $errors['slug'] = 'Slug должен быть длиной от 2 до 180 символов.';
        } elseif ($this->services->slugExists($slug, $excludeId)) {
            $errors['slug'] = 'Услуга с таким slug уже существует.';
        }

        $shortDescription = $this->scalar($input['short_description'] ?? '');
        if ($this->hasControlCharacters($shortDescription, true)) {
            $errors['short_description'] = 'Краткое описание содержит недопустимые символы.';
        } elseif (mb_strlen($shortDescription) > 255) {
            $errors['short_description'] = 'Краткое описание не должно превышать 255 символов.';
        }

        $description = $this->scalar($input['description'] ?? '');
        if ($this->hasControlCharacters($description, true)) {
            $errors['description'] = 'Описание содержит недопустимые символы.';
        } elseif (mb_strlen($description) > 10000) {
            $errors['description'] = 'Описание слишком длинное.';
        }

        $priceRaw = $this->scalar($input['price_from'] ?? '');
        $price = null;
        if ($priceRaw !== '') {
            if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $priceRaw) !== 1) {
                $errors['price_from'] = 'Укажите неотрицательную цену с максимум двумя знаками после запятой.';
            } elseif ((float) $priceRaw > 99999999.99) {
                $errors['price_from'] = 'Цена слишком большая.';
            } else {
                $price = number_format((float) $priceRaw, 2, '.', '');
            }
        }

        $minutesRaw = $this->scalar($input['estimated_minutes'] ?? '');
        $minutes = null;
        if ($minutesRaw !== '') {
            if (preg_match('/^\d{1,6}$/', $minutesRaw) !== 1) {
                $errors['estimated_minutes'] = 'Время должно быть целым числом минут.';
            } else {
                $minutes = (int) $minutesRaw;
                if ($minutes < 1 || $minutes > 100000) {
                    $errors['estimated_minutes'] = 'Время должно быть от 1 до 100000 минут.';
                }
            }
        }

        $isActive = $this->parseActive($input, $currentActive);
        if ($isActive === null) {
            $errors['is_active'] = 'Некорректное состояние услуги.';
            $isActive = $currentActive;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'category_id' => (int) $categoryId,
            'name' => $name,
            'slug' => $slug,
            'short_description' => $shortDescription === '' ? null : $shortDescription,
            'description' => $description === '' ? null : $description,
            'price_from' => $price,
            'estimated_minutes' => $minutes,
            'is_active' => $isActive,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{name: string, slug: string, description: ?string, sort_order: int, is_active: bool}
     * @throws ValidationException
     */
    private function validateCategory(array $input, ?int $excludeId, bool $currentActive): array
    {
        $errors = [];

        $name = $this->scalar($input['name'] ?? '');
        if ($this->hasControlCharacters($name)) {
            $errors['name'] = 'Название содержит недопустимые символы.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'Укажите название длиной от 2 до 120 символов.';
        }

        $slug = $this->normaliseSlug($this->scalar($input['slug'] ?? ''));
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $errors['slug'] = 'Slug: только строчные латинские буквы, цифры и дефисы между словами.';
        } elseif (mb_strlen($slug) < 2 || mb_strlen($slug) > 140) {
            $errors['slug'] = 'Slug должен быть длиной от 2 до 140 символов.';
        } elseif ($this->categories->slugExists($slug, $excludeId)) {
            $errors['slug'] = 'Категория с таким slug уже существует.';
        }

        $description = $this->scalar($input['description'] ?? '');
        if ($this->hasControlCharacters($description, true)) {
            $errors['description'] = 'Описание содержит недопустимые символы.';
        } elseif (mb_strlen($description) > 2000) {
            $errors['description'] = 'Описание не должно превышать 2000 символов.';
        }

        $sortRaw = $this->scalar($input['sort_order'] ?? '');
        if ($sortRaw === '') {
            $sortRaw = '0';
        }
        $sortOrder = null;
        if (preg_match('/^\d{1,6}$/', $sortRaw) !== 1) {
            $errors['sort_order'] = 'Порядок должен быть целым неотрицательным числом.';
        } else {
            $sortOrder = (int) $sortRaw;
            if ($sortOrder > 100000) {
                $errors['sort_order'] = 'Порядок не должен превышать 100000.';
            }
        }

        $isActive = $this->parseActive($input, $currentActive);
        if ($isActive === null) {
            $errors['is_active'] = 'Некорректное состояние категории.';
            $isActive = $currentActive;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $description === '' ? null : $description,
            'sort_order' => (int) $sortOrder,
            'is_active' => $isActive,
        ];
    }

    /** @param array<string, mixed> $input */
    private function parseActive(array $input, bool $current): ?bool
    {
        if (!array_key_exists('is_active', $input)) {
            return $current;
        }

        $value = $this->scalar($input['is_active']);
        if (!in_array($value, ['0', '1'], true)) {
            return null;
        }

        return $value === '1';
    }

    /** @param array<string, mixed> $actor */
    private function assertAdmin(array $actor): void
    {
        if ((string) ($actor['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }
    }

    private function normaliseSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }

    private function hasControlCharacters(string $value, bool $allowWhitespace = false): bool
    {
        $pattern = $allowWhitespace
            ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'
            : '/[\x00-\x1F\x7F]/u';

        return preg_match($pattern, $value) === 1;
    }

    private function scalar(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }
}
