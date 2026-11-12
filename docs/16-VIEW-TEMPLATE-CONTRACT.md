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
| GET /services/{slug} | pages/public/services/show | service (including category_name when available) |
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

The service detail route must use a slug allowlisted by the router/controller and render pages/public/services/show. The service view model contains name, slug, short_description, description, price_from, estimated_minutes, and optional category_name. The card links to /services/{slug}.

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

## Authentication templates (UI-02)

| Route | Logical view | Additional data |
|---|---|---|
| GET /login | pages/auth/login | shared layout data, optional errors and old |
| GET /register | pages/auth/register | shared layout data, optional errors and old |

Both views use a regular POST form to the matching route and include the supplied CSRF token as `_token`.

Login fields:
- `email`
- `password`

Registration fields:
- `full_name`
- `email`
- `phone` (optional)
- `password`
- `password_confirmation`

Field errors use `errors[field]`; a form-level error may use `errors.general`. Only non-secret fields (full_name, email, phone) may be retained in `old`. Never return passwords or password confirmation in old input. Password visibility is a client-side presentation control only and does not alter submission semantics.

The views assume the same shared helpers and layout values as public templates. They do not implement registration, credential verification, session changes, role assignment or access control.

## Customer workspace templates (UI-03)

The following templates are prepared against the routes in `docs/10-INTEGRATION-CONTRACT.md`. They do not register routes or implement business logic.

| Route | Logical view | Required view-model keys |
|---|---|---|
| GET /account | pages/customer/dashboard | `recentRequests[]` |
| GET /requests | pages/customer/requests/index | `requestList` |
| GET/POST /requests/create | pages/customer/requests/create | `services[]`, `old`, `errors`, `csrfToken` |
| GET /requests/{id} | pages/customer/requests/show | `request`, `history[]`, `comments[]`, `permissions` |

### Customer list model

`requestList` uses the generic list structure defined above:
- `items[]`: request view models matching the request fields in docs/10.
- `pagination`: current_page, last_page, per_page and total.
- `filters`: q, status and sort (newest, oldest or updated).

The controller must scope the query to the authenticated customer before rendering. The view does not treat a missing/empty list as a server error and never inserts sample records.

### Create request form

POST `/requests/create` sends:
- `device_type` (required string)
- `device_brand` (optional string)
- `device_model` (optional string)
- `service_id` (optional service identifier)
- `problem_description` (required string)
- `customer_contact` (required string)

The view model provides `services[]` with `id` and `name`. Field errors use the field names above. Only non-secret values may be returned in `old`. The form includes `_token` and uses POST + PRG.

### Request detail model and actions

The request detail view receives:
- `request`: the request model in docs/10, with optional `customer`, `technician` and `service` sub-models.
- `history[]`: chronological ascending events with to_status, reason, changed_at and changed_by_name.
- `comments[]`: visible comments with body, author_name, author_role and created_at. Internal comments must be excluded server-side; the template also skips any item marked is_internal.
- `permissions`: can_comment and can_decide_estimate boolean flags for presentation only. Backend authorization remains authoritative.

Actions:
- POST `/requests/{id}/comments`: `body` plus `_token`.
- POST `/requests/{id}/estimate-decision`: `decision=approve|reject` plus `_token`.

The estimate decision form is shown only when the backend permission flag is true and status is `awaiting_customer_approval`. Both decisions must be validated and applied by the backend; hiding a button is not access control.

### Reusable request card

`components/request-card.php` reads one `request` array and links to `/requests/{id}`. It must only be used with records already scoped by the controller/repository.

## Technician workspace templates (UI-04)

| Route | Logical view | Required view-model keys |
|---|---|---|
| GET /technician/requests | pages/technician/requests/index | `requestList` |
| GET /technician/requests/{id} | pages/technician/requests/show | `request`, `history[]`, `comments[]`, `permissions`, `allowedTransitions[]` |

The technician queue uses the generic list model. Its filters are q, status, priority and sort (newest, oldest or priority). Each item includes the request model and customer summary. The query must be scoped to requests assigned to the current technician unless the authenticated role is admin.

The technician detail model contains the request model from docs/10, customer and service summaries, chronological history and comments (including internal comments for authorized staff). `permissions` contains:
- can_edit_diagnosis
- can_change_status
- can_comment

`allowedTransitions[]` contains the status codes that the backend currently permits for this request and actor. A value may be a status code string or an object containing `code`. The template only renders these supplied options and never computes the transition matrix.

POST actions:
- `/technician/requests/{id}/diagnosis`: `diagnostic_result`, optional `estimated_cost`, optional `estimated_ready_at`, plus `_token`.
- `/technician/requests/{id}/status`: `status`, optional `reason`, plus `_token`.
- `/requests/{id}/comments`: `body`, optional `is_internal=1`, plus `_token`.

Internal comments may be displayed to authorized staff but must never be returned in customer-facing view models. The backend validates every status transition and role/assignment check, regardless of visible controls.

## Administrator workspace templates (UI-04)

| Route | Logical view | Required view-model keys |
|---|---|---|
| GET /admin | pages/admin/dashboard | `stats`, `recentRequests[]` |
| GET /admin/requests | pages/admin/requests/index | `requestList` |

Dashboard `stats` is a map of database-backed non-negative counts:
- total_requests
- requests_new
- requests_in_progress
- requests_waiting_approval
- requests_completed
- active_customers
- active_technicians

The admin request registry uses the generic list model. Items include request fields plus customer.full_name and technician.full_name when available. Filters are q, status, priority and sort (newest, oldest or updated). The list links to the authorized request detail route. No sample counts or demonstration rows are rendered when keys or items are missing.

