<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\RepairGuideRepository;

final class RepairGuideService
{
    /** @var array<string, string> */
    private const CATEGORIES = [
        'laptops-pc' => 'Ноутбуки и ПК',
        'smartphones' => 'Смартфоны и планшеты',
        'software' => 'Программное обеспечение',
        'storage' => 'Накопители и данные',
        'network' => 'Сеть и подключение',
        'peripherals' => 'Периферия',
    ];

    public function __construct(private readonly RepairGuideRepository $guides)
    {
    }

    /** @return array<string, mixed> */
    public function publicIndex(string $query = '', string $category = '', int $page = 1): array
    {
        $query = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $query));
        $query = mb_substr($query, 0, 100);
        if (!array_key_exists($category, self::CATEGORIES)) {
            $category = '';
        }

        // The public knowledge base is rendered inside .service-grid, which
        // switches between 4, 3, 2 and 1 columns depending on the viewport.
        // A page size that divides by every column count keeps the last row
        // full instead of leaving empty slots and pushing the tail to the
        // next page. 12 is divisible by 4, 3, 2 and 1.
        $perPage = 12;
        $total = $this->guides->countPublished($query, $category);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, $page));
        $items = $this->guides->listPublished($query, $category, $perPage, ($page - 1) * $perPage);

        foreach ($items as &$item) {
            $item['category_label'] = self::CATEGORIES[(string) $item['category_slug']] ?? 'Другое';
        }
        unset($item);

        return [
            'items' => $items,
            'categories' => $this->categoryOptions(),
            'filters' => ['q' => $query, 'category' => $category],
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => $perPage],
        ];
    }

    /** @return array<string, mixed>|null */
    public function requestContext(string $slug): ?array
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        return $this->guides->findPublishedBySlugForRequest($slug);
    }

    /** @return array<string, mixed> */
    public function publicArticle(string $slug): array
    {
        $guide = $this->guides->findPublishedBySlug($slug);
        if ($guide === null) {
            throw new HttpException(404, 'Repair guide not found');
        }

        $guide['category_label'] = self::CATEGORIES[(string) $guide['category_slug']] ?? 'Другое';
        $steps = json_decode((string) $guide['steps_json'], true);
        $guide['steps'] = is_array($steps) ? array_values(array_filter($steps, 'is_string')) : [];

        return $guide;
    }

    /** @return list<array<string, mixed>> */
    public function adminIndex(array $user): array
    {
        $this->assertAdmin($user);
        $items = $this->guides->listForAdmin();
        foreach ($items as &$item) {
            $item['category_label'] = self::CATEGORIES[(string) $item['category_slug']] ?? 'Другое';
            $item['status_label'] = match ($item['status']) {
                'published' => 'Опубликована',
                'archived' => 'В архиве',
                default => 'Черновик',
            };
        }
        unset($item);

        return $items;
    }

    /** @return array<string, mixed> */
    public function adminFind(array $user, int $id): array
    {
        $this->assertAdmin($user);
        $guide = $this->guides->findById($id);
        if ($guide === null) {
            throw new HttpException(404, 'Repair guide not found');
        }
        $decoded = json_decode((string) $guide['steps_json'], true);
        $guide['steps_text'] = is_array($decoded) ? implode("\n", array_filter($decoded, 'is_string')) : '';
        $guide['category_label'] = self::CATEGORIES[(string) $guide['category_slug']] ?? 'Другое';

        return $guide;
    }

    /** @param array<string, mixed> $input */
    public function create(array $user, array $input): int
    {
        $this->assertAdmin($user);
        $data = $this->validate($input);
        return $this->guides->create($data, (int) $user['id']);
    }

    /** @param array<string, mixed> $input */
    public function update(array $user, int $id, array $input): void
    {
        $this->assertAdmin($user);
        if ($this->guides->findById($id) === null) {
            throw new HttpException(404, 'Repair guide not found');
        }
        $data = $this->validate($input, $id);
        $this->guides->update($id, $data, (int) $user['id']);
    }

    public function archive(array $user, int $id): void
    {
        $this->assertAdmin($user);
        if ($this->guides->findById($id) === null) {
            throw new HttpException(404, 'Repair guide not found');
        }
        $this->guides->archive($id, (int) $user['id']);
    }

    /** @return list<array{slug: string, name: string}> */
    public function categoryOptions(): array
    {
        $items = [];
        foreach (self::CATEGORIES as $slug => $name) {
            $items[] = ['slug' => $slug, 'name' => $name];
        }

        return $items;
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed> */
    private function validate(array $input, ?int $excludeId = null): array
    {
        $values = [];
        $errors = [];
        $limits = [
            'title' => 180,
            'device_type' => 100,
            'summary' => 420,
            'symptoms' => 2500,
            'possible_causes' => 2500,
            'do_not' => 2000,
            'when_to_contact' => 2000,
        ];
        $minimums = [
            'title' => 5,
            'device_type' => 2,
            'summary' => 15,
            'symptoms' => 15,
            'possible_causes' => 15,
            'do_not' => 10,
            'when_to_contact' => 10,
        ];
        $labels = [
            'title' => 'Название',
            'device_type' => 'Тип устройства',
            'summary' => 'Краткое описание',
            'symptoms' => 'Признаки проблемы',
            'possible_causes' => 'Возможные причины',
            'do_not' => 'Что нельзя делать',
            'when_to_contact' => 'Когда обратиться к мастеру',
        ];

        foreach ($limits as $key => $maximum) {
            $raw = $input[$key] ?? '';
            $value = is_string($raw) ? trim($raw) : '';
            $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
            $length = mb_strlen($value);
            if ($length < $minimums[$key]) {
                $errors[$key] = $labels[$key] . ' слишком короткое.';
            } elseif ($length > $maximum) {
                $errors[$key] = $labels[$key] . ' превышает лимит (' . $maximum . ' символов).';
            }
            $values[$key] = $value;
        }

        $category = is_string($input['category_slug'] ?? null) ? (string) $input['category_slug'] : '';
        if (!array_key_exists($category, self::CATEGORIES)) {
            $errors['category_slug'] = 'Выберите существующую категорию.';
        }
        $values['category_slug'] = $category;

        $slug = is_string($input['slug'] ?? null) ? trim((string) $input['slug']) : '';
        if ($slug === '') {
            $slug = $this->slugify($values['title']);
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || mb_strlen($slug) > 190) {
            $errors['slug'] = 'Используйте латинские буквы, цифры и дефисы.';
        } elseif ($this->guides->slugExists($slug, $excludeId)) {
            $errors['slug'] = 'Этот адрес уже занят.';
        }
        $values['slug'] = $slug;

        $stepsRaw = is_string($input['steps'] ?? null) ? (string) $input['steps'] : '';
        $steps = array_values(array_filter(array_map(
            static fn (string $line): string => trim((string) preg_replace('/^\s*\d+[.)]\s*/u', '', $line)),
            preg_split('/\R/u', $stepsRaw) ?: []
        ), static fn (string $line): bool => $line !== ''));
        if (count($steps) < 3 || count($steps) > 10) {
            $errors['steps'] = 'Добавьте от 3 до 10 шагов, каждый с новой строки.';
        } else {
            foreach ($steps as $index => $step) {
                if (mb_strlen($step) < 10 || mb_strlen($step) > 400) {
                    $errors['steps'] = 'Каждый шаг должен содержать от 10 до 400 символов.';
                    break;
                }
            }
        }
        $values['steps'] = $steps;

        $status = is_string($input['status'] ?? null) ? (string) $input['status'] : 'draft';
        if (!in_array($status, ['draft', 'published'], true)) {
            $errors['status'] = 'Недопустимый статус публикации.';
        }
        $values['status'] = $status;

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $values;
    }

    private function slugify(string $value): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z',
            'и'=>'i','й'=>'i','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
            'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ];
        $value = mb_strtolower($value);
        $value = strtr($value, $map);
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/', '-', $value));
        return trim($slug, '-') ?: 'repair-guide';
    }

    /** @param array<string, mixed> $user */
    private function assertAdmin(array $user): void
    {
        if ((string) ($user['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }
    }
}
