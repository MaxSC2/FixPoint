# View Template Contract — FixPoint UI

**Status:** UI-01 implementation contract, aligned with docs/10-INTEGRATION-CONTRACT.md.

This document tells the backend renderer how the UI views are structured. It does not add controllers or business logic.

## Renderer requirements

The backend should expose one e() escaping helper and a renderer that:
1. Resolves a fixed logical view name under app/Views/.
2. Passes the controller view model into the included template scope.
3. Buffers and returns the rendered HTML.
4. Never accepts a filesystem path from the request or includes a user-controlled template name.
5. Uses the shared helpers and conventions below.

Views are PHP includes, not standalone endpoints. The renderer should load exactly one page template. The page template includes the shared header and footer.

Suggested usage:

    return $view->render('pages/public/home', [
        'authUser' => $currentUser,
        'flash' => $flash,
        'csrfToken' => $csrfToken,
        'featuredServices' => $featuredServices,
    ]);

The renderer maps this logical name to app/Views/pages/public/home.php using a fixed application view root.

## Shared layout data

The page view receives the shared values defined in docs/10-INTEGRATION-CONTRACT.md:
- pageTitle (string)
- activeNav (home, services, about, contacts, or future workspace key)
- authUser (null or user view model)
- flash (null or {type,message})
- errors (array)
- old (array; never includes passwords)
- csrfToken (string)

app/Views/layouts/header.php opens the HTML document, renders navigation, flash and opens <main>.
app/Views/layouts/footer.php closes <main> and renders the footer/document close.

All values must be escaped at output through e(). The e() helper must support nullable/stringable values safely or the controller must pass strings. Do not echo untrusted HTML.

## Public page map

| Route | Logical view | Additional data |
|---|---|---|
| GET / | pages/public/home | featuredServices optional array |
| GET /services | pages/public/services/index | services, categories, filters |
| GET /about | pages/public/about | shared data only |
| GET /contacts | pages/public/contacts | contactAddress, contactPhone, contactEmail, workingHours optional strings |

The templates set their own pageTitle and activeNav values. The controller should still pass the shared variables.

## Catalogue view model

services is an array of service rows containing:
- name (string)
- slug (string)
- short_description (nullable string)
- price_from (nullable decimal/string)

categories is an array of:
- name (string)
- slug (string)

filters:
- q (string)
- category (string)

The GET form submits q and category to /services. Empty results render an explicit empty state; do not insert fake sample services.

The service detail route must use a slug allowlisted by the router/controller and render a separately implemented page. The card links to /services/{slug}.

## Components

- components/flash.php: reads flash[type,message].
- components/status-badge.php: reads status and maps the known repair status codes to a localized badge.
- components/service-card.php: reads one service array and renders a catalogue card.
- Components must not query PDO, mutate state, or decide permissions.

## Forms and interaction

- Logout is POST /logout with hidden _token.
- Guest CTAs navigate to /register; they do not POST.
- Catalogue filters use GET only.
- The public pages in UI-01 do not include a pretend contact-submit handler.
- All state-changing actions in future authenticated views must use POST and CSRF per the main integration contract.

## Route integration note

Before wiring controllers, confirm that these page names resolve exactly as listed. If the backend's ViewRenderer API differs, adjust the renderer adapter, not the individual templates or security requirements.
