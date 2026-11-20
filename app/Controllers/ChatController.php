<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\HttpException;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\ChatService;

final class ChatController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly ChatService $chatService
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $onlyUnread = $request->query('filter') === 'unread';

        return $this->renderPage('pages/messages/index', [
            'pageTitle' => 'Сообщения — ' . $this->config->get('app.name'),
            'activeNav' => 'messages',
            'threads' => $this->chatService->listThreads($user, $onlyUnread),
            'onlyUnread' => $onlyUnread,
        ]);
    }

    public function markRead(Request $request, array $params = []): Response
    {
        $user = $this->requireUser();
        $requestId = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        if ($requestId === false || $requestId < 1) {
            $this->abort(404, 'Request not found');
        }

        $this->chatService->markRead((int) $requestId, $user);
        $path = (string) ($user['role'] ?? '') === 'customer'
            ? '/requests/' . $requestId
            : '/technician/requests/' . $requestId;

        return Response::redirect($path . '#chat');
    }

    /** @return array<string, mixed> */
    private function requireUser(): array
    {
        $user = $this->auth->user();
        if ($user === null) {
            throw new HttpException(403, 'Authentication required');
        }

        return $user;
    }
}
