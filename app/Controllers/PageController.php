<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Request;
use FixPoint\Core\Response;

/**
 * Static public pages that render frontend-owned templates. Contact values come
 * from configuration; empty values are rendered as explicit placeholders by the
 * view instead of invented phone numbers or addresses.
 */
final class PageController extends Controller
{
    public function about(Request $request, array $params = []): Response
    {
        return $this->renderPage('pages/public/about', [
            'pageTitle' => 'О сервисе — ' . $this->config->get('app.name'),
            'activeNav' => 'about',
        ]);
    }

    public function contacts(Request $request, array $params = []): Response
    {
        return $this->renderPage('pages/public/contacts', [
            'pageTitle' => 'Контакты — ' . $this->config->get('app.name'),
            'activeNav' => 'contacts',
            'contactAddress' => (string) $this->config->get('contacts.address', ''),
            'contactPhone' => (string) $this->config->get('contacts.phone', ''),
            'contactEmail' => (string) $this->config->get('contacts.email', ''),
            'workingHours' => (string) $this->config->get('contacts.working_hours', ''),
        ]);
    }
}
