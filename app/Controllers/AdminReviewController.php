<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\HttpException;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ValidationException;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\TechnicianReviewService;

final class AdminReviewController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly TechnicianReviewService $reviews
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        return $this->renderPage('pages/admin/reviews/index', [
            'pageTitle' => 'Отзывы мастеров — модерация',
            'activeNav' => 'admin',
            'reviews' => $this->reviews->adminList($this->requireAdmin()),
        ]);
    }

    public function moderate(Request $request, array $params = []): Response
    {
        $user = $this->requireAdmin();
        $status = $request->post('status', '');
        $reason = $request->post('reason', '');
        try {
            $this->reviews->moderate($user, (int) ($params['id'] ?? 0), [
                'status' => is_string($status) ? $status : '',
                'reason' => is_string($reason) ? $reason : '',
            ]);
        } catch (ValidationException $exception) {
            $this->session->flashErrors($exception->errors());
            $this->session->flash('error', 'Не удалось изменить статус отзыва.');
            return Response::redirect('/admin/reviews');
        }

        $this->session->flash('success', 'Статус отзыва обновлён.');
        return Response::redirect('/admin/reviews');
    }

    /** @return array<string, mixed> */
    private function requireAdmin(): array
    {
        $user = $this->auth->user();
        if ($user === null || (string) ($user['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }
        return $user;
    }
}
