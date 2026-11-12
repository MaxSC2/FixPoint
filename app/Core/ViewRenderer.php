<?php

declare(strict_types=1);

namespace FixPoint\Core;

use RuntimeException;

/**
 * PHP view renderer compatible with the integration contract.
 *
 * Production templates live in app/Views (frontend-owned). Until a template
 * exists there, a clearly marked backend fallback from app/Support/views is
 * used so the application has no fake routes or fake success pages.
 */
final class ViewRenderer
{
    public function __construct(private readonly Config $config)
    {
    }

    /** @param array<string, mixed> $data */
    public function renderPage(string $view, array $data = [], int $status = 200): Response
    {
        [$file, $isFallback] = $this->locate($view);
        $content = $this->capture($file, $data);

        if (!$isFallback) {
            $layout = $this->config->get('paths.views') . '/layouts/app.php';
            if (is_file($layout)) {
                $content = $this->capture($layout, array_merge($data, ['content' => $content]));
            }
        }

        return Response::html($content, $status);
    }

    /** @param array<string, mixed> $data */
    public function renderError(int $status, array $data = []): Response
    {
        $data = array_merge([
            'status' => $status,
            'title' => 'Ошибка',
            'message' => 'Запрос не может быть выполнен.',
            'errorId' => null,
            'pageTitle' => 'Ошибка ' . $status,
        ], $data);

        $view = 'errors/' . $status;
        try {
            [$file] = $this->locate($view);
        } catch (RuntimeException) {
            [$file] = $this->locate('errors/error');
        }

        return Response::html($this->capture($file, $data), $status);
    }

    public function exists(string $view): bool
    {
        try {
            $this->locate($view);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /** @return array{0: string, 1: bool} */
    private function locate(string $view): array
    {
        if (preg_match('/^[A-Za-z0-9_\/-]+$/', $view) !== 1 || str_contains($view, '..')) {
            throw new RuntimeException('Invalid view name.');
        }

        $relative = str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';
        $production = rtrim((string) $this->config->get('paths.views'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative;
        if (is_file($production)) {
            return [$production, false];
        }

        $fallback = rtrim((string) $this->config->get('paths.fallback_views'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative;
        if (is_file($fallback)) {
            return [$fallback, true];
        }

        throw new RuntimeException('View not found: ' . $view);
    }

    /** @param array<string, mixed> $data */
    private function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
