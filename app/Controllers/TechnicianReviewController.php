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
use FixPoint\Services\RequestService;
use FixPoint\Services\TechnicianReviewService;

final class TechnicianReviewController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly RequestService $requests,
        private readonly TechnicianReviewService $reviews
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->requireCustomer();
        $requestId = (int) ($params['id'] ?? 0);
        $repairRequest = $this->requests->findVisibleForUser($requestId, $user);
        if ($repairRequest === null) {
            $this->abort(404, 'Request not found');
        }
        $existing = $this->reviews->forRequest($requestId, (int) $user['id']);
        $canReview = (string) ($repairRequest['status'] ?? '') === 'completed'
            && (int) ($repairRequest['technician_id'] ?? 0) > 0
            && $existing === null;

        return $this->renderPage('pages/customer/requests/review', [
            'pageTitle' => 'Оценка мастера — ' . (string) ($repairRequest['public_number'] ?? 'Заявка'),
            'activeNav' => 'requests',
            'request' => $repairRequest,
            'existingReview' => $existing,
            'canReview' => $canReview,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = $this->requireCustomer();
        $requestId = (int) ($params['id'] ?? 0);
        $rating = $request->post('rating', '');
        $reviewText = $request->post('review_text', '');
        $input = [
            'rating' => is_scalar($rating) ? (string) $rating : '',
            'review_text' => is_string($reviewText) ? trim($reviewText) : '',
        ];

        try {
            $this->reviews->submit($user, $requestId, $input);
        } catch (ValidationException $exception) {
            $this->session->flashErrors($exception->errors());
            $this->session->flashOld($input);
            $this->session->flash('error', 'Не удалось отправить отзыв. Проверьте данные.');
            return Response::redirect('/requests/' . $requestId . '/review');
        }

        $this->session->flash('success', 'Спасибо! Ваш отзыв опубликован и учтён в рейтинге мастера.');
        return Response::redirect('/requests/' . $requestId . '/review');
    }

    /** @return array<string, mixed> */
    private function requireCustomer(): array
    {
        $user = $this->auth->user();
        if ($user === null || (string) ($user['role'] ?? '') !== 'customer') {
            throw new HttpException(403, 'Customer access required');
        }
        return $user;
    }
}
