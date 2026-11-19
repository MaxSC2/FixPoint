<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\AttachmentService;

final class AttachmentController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly AttachmentService $attachmentService
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->abort(403, 'Authentication required');
        }

        $requestId = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        $attachmentId = filter_var($params['attachmentId'] ?? null, FILTER_VALIDATE_INT);
        if ($requestId === false || $requestId < 1 || $attachmentId === false || $attachmentId < 1) {
            $this->abort(404, 'Attachment not found');
        }

        $file = $this->attachmentService->findAccessible((int) $attachmentId, $user);
        if ($file === null) {
            $this->abort(404, 'Attachment not found');
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $mimeType = (string) $file['mime_type'];
        if (!in_array($mimeType, $allowedTypes, true)) {
            $this->abort(404, 'Attachment not found');
        }

        $contents = file_get_contents((string) $file['path']);
        if ($contents === false) {
            $this->abort(404, 'Attachment not found');
        }

        $originalName = basename(str_replace('\\', '/', (string) $file['original_name']));
        $originalName = preg_replace('/[\x00-\x1F\x7F"\\\\]/u', '', $originalName) ?? '';
        if ($originalName === '') {
            $originalName = 'image';
        }
        $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?? 'image';
        if ($fallbackName === '') {
            $fallbackName = 'image';
        }

        return Response::binary($contents, $mimeType, [
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($originalName),
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
