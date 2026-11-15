<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\SiteContentRepository;

/** Validates and serves the administrator-editable public content blocks. */
final class SiteContentService
{
    private const ABOUT_KEY = 'about';

    public function __construct(private readonly SiteContentRepository $content)
    {
    }

    /** @return array<string, mixed>|null */
    public function about(): ?array
    {
        return $this->content->findByKey(self::ABOUT_KEY);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @throws ValidationException|HttpException
     */
    public function updateAbout(array $user, array $input): void
    {
        if ((string) ($user['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }

        $limits = [
            'title' => 180,
            'lead' => 255,
            'section_heading' => 180,
            'section_body' => 4000,
        ];
        $minimums = [
            'title' => 5,
            'lead' => 10,
            'section_heading' => 3,
            'section_body' => 20,
        ];
        $labels = [
            'title' => 'Заголовок',
            'lead' => 'Краткое описание',
            'section_heading' => 'Заголовок раздела',
            'section_body' => 'Основной текст',
        ];
        $values = [];
        $errors = [];

        foreach ($limits as $key => $maximum) {
            $raw = $input[$key] ?? '';
            $value = is_string($raw) ? trim($raw) : '';
            $length = mb_strlen($value);
            if ($length < $minimums[$key]) {
                $errors[$key] = $labels[$key] . ' слишком короткий.';
            } elseif ($length > $maximum) {
                $errors[$key] = $labels[$key] . ' превышает лимит (' . $maximum . ' символов).';
            } elseif (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
                $errors[$key] = $labels[$key] . ' содержит недопустимые управляющие символы.';
            }
            $values[$key] = $value;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->content->updateByKey(self::ABOUT_KEY, $values, (int) ($user['id'] ?? 0));
    }
}
