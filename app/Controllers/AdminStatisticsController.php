<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use DateTimeImmutable;
use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ViewRenderer;
use FixPoint\Repositories\AdminStatisticsRepository;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;

/** Admin reporting screen backed by date-scoped database aggregates. */
final class AdminStatisticsController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly AdminStatisticsRepository $statistics
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $today = new DateTimeImmutable('today');
        $defaultFrom = $today->modify('first day of -5 months');
        $from = $this->validDate($request->query('from'), $defaultFrom);
        $to = $this->validDate($request->query('to'), $today);

        // A reversed range is corrected deterministically rather than returning
        // an empty chart that looks like a backend failure.
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $filters = ['from' => $from, 'to' => $to];

        return $this->renderPage('pages/admin/statistics', [
            'pageTitle' => 'Статистика — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'filters' => $filters,
            'summary' => $this->statistics->summary($filters),
            'statusBreakdown' => $this->statistics->statusBreakdown($filters),
            'monthlyRequests' => $this->statistics->monthlyRequests($filters),
            'topServices' => $this->statistics->topServices($filters),
        ]);
    }

    private function validDate(mixed $value, DateTimeImmutable $fallback): string
    {
        if (!is_string($value) || $value === '') {
            return $fallback->format('Y-m-d');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (!$date instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return $fallback->format('Y-m-d');
        }

        return $date->format('Y-m-d');
    }
}
