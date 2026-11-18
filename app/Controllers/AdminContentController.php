<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ValidationException;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\SiteContentService;

/** Admin-only editor for the public About content block. */
final class AdminContentController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly SiteContentService $siteContent
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $this->requireUser();
        $content = $this->siteContent->about();
        if ($content === null) {
            $this->abort(404, 'About content block not found');
        }

        return $this->renderPage('pages/admin/content/about', [
            'pageTitle' => 'Контент сайта — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'content' => $content,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $input = [
            'title' => $this->stringPost($request, 'title'),
            'lead' => $this->stringPost($request, 'lead'),
            'section_heading' => $this->stringPost($request, 'section_heading'),
            'section_body' => $this->stringPost($request, 'section_body'),
        ];

        try {
            $this->siteContent->updateAbout($user, $input);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (isset($errors['general'])) {
                $this->session->flash('error', $errors['general']);
                unset($errors['general']);
            }
            if ($errors !== []) {
                $this->session->flashErrors($errors);
            }
            $this->session->flashOld($input);

            return Response::redirect('/admin/content/about');
        }

        $this->session->flash('success', 'Текст страницы «О сервисе» сохранён.');
        return Response::redirect('/admin/content/about');
    }

    /** @return array<string, mixed> */
    private function requireUser(): array
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->abort(403, 'Authentication required');
        }

        return $user;
    }

    private function stringPost(Request $request, string $key): string
    {
        $value = $request->post($key, '');
        return is_string($value) ? trim($value) : '';
    }
}
