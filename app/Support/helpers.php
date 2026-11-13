<?php

declare(strict_types=1);

use FixPoint\Core\App;
use FixPoint\Core\Request;
use FixPoint\Core\Response;

/**
 * Shared helpers. Names are documented in README.md:
 * e, url, asset, redirect, back, csrf_token, csrf_field, flash, flash_errors,
 * flash_old, auth_user, has_role, format_money, format_datetime, status_label,
 * old_value, base_path, app, config.
 */

if (!function_exists('app')) {
    function app(): App
    {
        return App::instance();
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return app()->config()->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

        return $path === '' ? $base : rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        if ($path === '') {
            return $base . '/';
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to, int $status = 302): Response
    {
        if (!is_safe_local_path($to)) {
            throw new InvalidArgumentException('Redirect target must be a local application path.');
        }

        return Response::redirect($to, $status);
    }
}

if (!function_exists('is_safe_local_path')) {
    function is_safe_local_path(string $path): bool
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return false;
        }

        return preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }
}

if (!function_exists('back')) {
    function back(Request $request, string $fallback = '/'): Response
    {
        $referer = $request->referer();
        if ($referer !== null) {
            $parts = parse_url($referer);
            $appParts = parse_url((string) config('app.url', ''));
            if (is_array($parts) && is_array($appParts)
                && isset($parts['host'], $appParts['host'])
                && strcasecmp($parts['host'], $appParts['host']) === 0
                && strcasecmp($parts['scheme'] ?? 'http', $appParts['scheme'] ?? 'http') === 0
            ) {
                $target = $parts['path'] ?? '/';
                if (isset($parts['query'])) {
                    $target .= '?' . $parts['query'];
                }
                if (is_safe_local_path($target)) {
                    return redirect($target);
                }
            }
        }

        return redirect($fallback);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return app()->csrf()->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        app()->session()->flash($type, $message);
    }
}

if (!function_exists('flash_errors')) {
    /** @param array<string, string> $errors */
    function flash_errors(array $errors): void
    {
        app()->session()->flashErrors($errors);
    }
}

if (!function_exists('flash_old')) {
    /** @param array<string, mixed> $input */
    function flash_old(array $input): void
    {
        app()->session()->flashOld($input);
    }
}

if (!function_exists('auth_user')) {
    /** @return array{id: int, full_name: string, email: string, role: string}|null */
    function auth_user(): ?array
    {
        return app()->auth()->user();
    }
}

if (!function_exists('has_role')) {
    function has_role(string ...$roles): bool
    {
        return app()->auth()->hasRole(...$roles);
    }
}

if (!function_exists('role_home')) {
    function role_home(?string $role): string
    {
        return match ($role) {
            'technician' => '/technician/requests',
            'admin' => '/admin',
            'customer' => '/account',
            default => '/',
        };
    }
}

if (!function_exists('old_value')) {
    /** @param array<string, mixed> $old */
    function old_value(array $old, string $key, mixed $default = null): mixed
    {
        return $old[$key] ?? $default;
    }
}

if (!function_exists('format_money')) {
    function format_money(float|int|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        return number_format((float) $amount, 0, '.', ' ') . ' ₸';
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $value, string $format = 'd.m.Y H:i'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));

            return $date->setTimezone(new DateTimeZone((string) config('app.timezone', 'UTC')))->format($format);
        } catch (Throwable) {
            return '—';
        }
    }
}

if (!function_exists('status_label')) {
    function status_label(string $status): string
    {
        return [
            'new' => 'Новая',
            'accepted' => 'Принята',
            'diagnostics' => 'Диагностика',
            'awaiting_customer_approval' => 'Ожидает согласования',
            'awaiting_parts' => 'Ожидает запчасти',
            'repairing' => 'В ремонте',
            'ready_for_pickup' => 'Готова к выдаче',
            'completed' => 'Завершена',
            'cancelled' => 'Отменена',
            'rejected' => 'Отклонена',
        ][$status] ?? $status;
    }
}
