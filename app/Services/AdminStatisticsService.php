<?php

declare(strict_types=1);

namespace FixPoint\Services;

use DateTimeImmutable;
use FixPoint\Core\HttpException;
use FixPoint\Repositories\RequestRepository;

/**
 * Administrator analytics. Every number comes from RequestRepository; this
 * service only validates the period, maps month keys to labels and fills month
 * gaps so the chart stays continuous. An invalid or reversed period is not
 * silently applied: the page renders an explicit message and falls back to the
 * unfiltered report.
 */
final class AdminStatisticsService
{
    /** @var array<int, string> */
    private const MONTH_LABELS = [
        1 => 'Янв',
        2 => 'Фев',
        3 => 'Мар',
        4 => 'Апр',
        5 => 'Май',
        6 => 'Июн',
        7 => 'Июл',
        8 => 'Авг',
        9 => 'Сен',
        10 => 'Окт',
        11 => 'Ноя',
        12 => 'Дек',
    ];

    public function __construct(private readonly RequestRepository $requests)
    {
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{
     *   summary: array{
     *     total_requests: int, requests_completed: int, requests_in_progress: int,
     *     requests_waiting_approval: int, requests_rejected: int
     *   },
     *   statusBreakdown: list<array{status: string, request_count: int}>,
     *   monthlyRequests: list<array{month_label: string, request_count: int}>,
     *   topServices: list<array{name: string, request_count: int}>,
     *   filters: array{from: string, to: string},
     *   periodError: string
     * }
     * @throws HttpException
     */
    public function report(array $actor, string $fromInput, string $toInput): array
    {
        $this->assertAdmin($actor);
        [$from, $to, $periodError] = $this->normalisePeriod($fromInput, $toInput);

        return [
            'summary' => $this->requests->statisticsSummary($from, $to),
            'statusBreakdown' => $this->requests->statusBreakdownForPeriod($from, $to),
            'monthlyRequests' => $this->monthlyRequests($from, $to),
            'topServices' => $this->requests->topServicesForPeriod($from, $to),
            'filters' => [
                'from' => $from ?? '',
                'to' => $to ?? '',
            ],
            'periodError' => $periodError,
        ];
    }

    /**
     * @return list<array{month_label: string, request_count: int}>
     */
    private function monthlyRequests(?string $from, ?string $to): array
    {
        $rows = $this->requests->monthlyCountsForPeriod($from, $to);
        if ($rows === []) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            if (preg_match('/^\d{4}-\d{2}$/', $row['month_key']) === 1) {
                $counts[$row['month_key']] = $row['request_count'];
            }
        }
        if ($counts === []) {
            return [];
        }

        $keys = array_keys($counts);
        sort($keys, SORT_STRING);
        $cursor = new DateTimeImmutable($keys[0] . '-01 00:00:00');
        $last = new DateTimeImmutable($keys[count($keys) - 1] . '-01 00:00:00');

        $months = [];
        while ($cursor <= $last) {
            $key = $cursor->format('Y-m');
            $label = self::MONTH_LABELS[(int) $cursor->format('n')] ?? $key;
            $months[] = [
                'month_label' => $label . ' ' . $cursor->format('y'),
                'request_count' => $counts[$key] ?? 0,
            ];
            $cursor = $cursor->modify('+1 month');
        }

        return $months;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: string}
     */
    private function normalisePeriod(string $fromInput, string $toInput): array
    {
        $from = trim($fromInput);
        $to = trim($toInput);

        if (($from !== '' && !$this->isValidDate($from)) || ($to !== '' && !$this->isValidDate($to))) {
            return [
                null,
                null,
                'Период указан некорректно. Используйте даты в формате ГГГГ-ММ-ДД; отчёт построен без фильтра.',
            ];
        }

        if ($from !== '' && $to !== '' && $from > $to) {
            return [
                null,
                null,
                'Дата начала позже даты окончания. Отчёт построен без фильтра.',
            ];
        }

        return [$from === '' ? null : $from, $to === '' ? null : $to, ''];
    }

    private function isValidDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    /** @param array<string, mixed> $actor */
    private function assertAdmin(array $actor): void
    {
        if ((string) ($actor['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }
    }
}
