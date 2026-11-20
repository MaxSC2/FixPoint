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
use FixPoint\Services\TechnicianProfileService;
use FixPoint\Services\TechnicianReviewService;

final class TechnicianProfileController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly TechnicianProfileService $profiles,
        private readonly TechnicianReviewService $reviews
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $query = $request->query('q', '');
        $category = $request->query('category', '');
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $data = $this->profiles->directory(
            is_string($query) ? $query : '',
            is_string($category) ? $category : '',
            $page === false ? 1 : (int) $page
        );

        return $this->renderPage('pages/public/masters/index', [
            'pageTitle' => 'Мастера FixPoint',
            'activeNav' => 'masters',
            'masters' => $data['items'],
            'categories' => $data['categories'],
            'filters' => $data['filters'],
            'pagination' => $data['pagination'],
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $technicianId = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($technicianId === false) {
            $this->abort(404, 'Technician not found');
        }
        $profile = $this->profiles->publicProfile((int) $technicianId);
        if ($profile === null) {
            $this->abort(404, 'Technician profile not found');
        }

        return $this->renderPage('pages/public/masters/show', [
            'pageTitle' => (string) $profile['full_name'] . ' — мастер FixPoint',
            'activeNav' => 'masters',
            'profile' => $profile,
            'reviews' => $this->reviews->publicReviews((int) $technicianId, 30),
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $data = $this->profiles->editorData($user);
        return $this->renderPage('pages/technician/profile/form', [
            'pageTitle' => 'Мой профиль мастера',
            'activeNav' => 'technician',
            'profile' => $data['profile'],
            'categories' => $data['categories'],
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $user = $this->requireTechnician();
        $specialties = $request->post('specialties', []);
        $input = [
            'headline' => $this->stringPost($request, 'headline'),
            'bio' => $this->stringPost($request, 'bio'),
            'is_public' => $this->stringPost($request, 'is_public'),
            'specialties' => is_array($specialties) ? $specialties : [],
        ];

        try {
            $this->profiles->update($user, $input);
        } catch (ValidationException $exception) {
            $this->session->flashErrors($exception->errors());
            $this->session->flashOld($input);
            $this->session->flash('error', 'Проверьте данные профиля.');
            return Response::redirect('/technician/profile');
        }

        $this->session->flash('success', 'Профиль мастера обновлён.');
        return Response::redirect('/technician/profile');
    }

    /** @return array<string, mixed> */
    private function requireTechnician(): array
    {
        $user = $this->auth->user();
        if ($user === null || (string) ($user['role'] ?? '') !== 'technician') {
            throw new HttpException(403, 'Technician access required');
        }
        return $user;
    }

    private function stringPost(Request $request, string $key): string
    {
        $value = $request->post($key, '');
        return is_string($value) ? trim($value) : '';
    }
}
