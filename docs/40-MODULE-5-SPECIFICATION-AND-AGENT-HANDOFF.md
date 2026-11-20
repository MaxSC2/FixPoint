# FixPoint — Module 5 specification and agent handoff

**Baseline:** `924337cdde5df4bc7ee7e4bb486e9b41b6bd1b85`  
**Baseline PR:** #33 (chat, private image attachments, expanded catalogue)  
**Module 5 integration branch:** `feature/module-5-knowledge-base-2026-11-20`

## 1. Product goal

Module 5 turns FixPoint from a request-tracking website into a service workflow for customers and technicians. It adds four connected capabilities:

1. Public troubleshooting knowledge base ("Частые неисправности").
2. Technician availability calendar and customer appointment booking.
3. Technician public profiles and verified customer reviews.
4. Homepage honour board ("Доска почёта") based on trustworthy review data.

Existing functionality is a hard compatibility requirement: roles, request lifecycle, catalogue, chat/read markers, private request attachments, admin panel, migrations and demo launch must keep working.

## 2. Work split

### Main implementation stream (repository owner)
- Knowledge base, article publishing/moderation UI and customer-facing troubleshooting pages.
- Integrate article links into existing navigation and request creation flow.
- Final integration of shared route/navigation/homepage contracts after the calendar branch is reviewed.
- End-to-end integration, final QA and release notes.

### Agent stream: Calendar / appointments only
Work in an isolated branch based on the latest Module 5 base branch. Do not modify the knowledge-base files. The agent owns:
- technician recurring work availability;
- one-off busy blocks / leave / personal markers;
- customer appointment request flow;
- technician confirm / decline / propose new time / cancel / complete actions;
- appointment history and calendar screens;
- transactional overlap protection and tests.

To avoid merge conflicts, the agent must NOT modify these shared integration files in its first delivery:
- `config/routes.php`
- `app/Views/layouts/header.php`
- `app/Views/layouts/footer.php`
- `app/Views/pages/public/home.php`
- `public/assets/css/app.css`
- `README.md`

Instead, report the exact route registrations, navigation links, dashboard links, view data contracts and CSS integration points needed. The owner will integrate these shared files after reviewing the implementation. The agent must not implement profiles, reviews, ratings or the honour board in this calendar task.

## 3. Module 5.1 — Knowledge base

### Public UX
- Public route: `GET /guides`; article route: `GET /guides/{slug}`.
- Search by title, summary, symptoms, device type and article content.
- Filter by category/device type; paginate results.
- Article sections: issue summary, symptoms, likely causes, safe user checks (numbered steps), things the user must NOT do, when to stop and contact a technician.
- Show a visible safety note. Articles must not encourage opening power supplies, handling swollen/damaged batteries, bypassing electrical protections, or performing hazardous measurements.
- Main CTA: "Создать заявку с этой проблемой", linking to request creation and pre-filling a trusted guide reference/summary server-side where feasible. A guide ID sent by the browser must be revalidated; never trust arbitrary hidden fields.
- Increment view count safely without allowing an invalid slug to update arbitrary rows.

### Admin UX
- Admin CRUD for guides and categories (at minimum categories can be seeded and managed through a controlled enum if full category CRUD adds unjustified complexity).
- Draft/published status. Only published articles are visible publicly.
- Slug uniqueness, validation, audit fields (created_by / updated_by), CSRF, admin-only access.
- Preview/edit form; preserve form input and show field-level validation.
- A guide with existing references should be archived/unpublished, not hard-deleted.

### Suggested categories
- Ноутбуки и ПК
- Смартфоны и планшеты
- Программное обеспечение
- Накопители и данные
- Сеть и подключение
- Периферия

### Safety / quality
- Clearly distinguish simple checks (power cable, charger compatibility, restart, free disk space, visible cable connections) from actions that require a professional.
- Avoid guarantees and unsupported diagnosis. Use phrasing like "возможная причина", not "точно сломано".
- Seed 12–18 editorial-quality sample articles with safe, practical instructions. Seed must be idempotent and must not overwrite admin-edited articles.

## 4. Module 5.2 — Calendar and appointments

### Technician availability
- Technician configures weekly working hours by weekday (one or more intervals), timezone, slot duration (30/60/90 min) and booking lead time.
- Technician can add one-off calendar blocks: unavailable, leave, personal, already occupied. Blocks have start/end, label and optional note. Private notes are never shown to customers.
- Calendar day/week/month views show appointments and blocks with clear statuses and accessible legends.
- Technician can confirm, decline with reason, propose a different time, cancel and mark an appointment completed/no-show.

### Customer booking
- Booking starts from a request and is only available to its owner and the assigned active technician.
- Customer chooses appointment purpose: `device_dropoff`, `device_pickup`, `consultation`; chooses one offered free slot; adds an optional note.
- Newly requested appointment is `pending`; the interval is not treated as confirmed until technician accepts. However overlapping pending requests should be controlled by an explicit short-lived reservation/locking policy so two customers cannot book the same interval at once.
- Technician confirmation atomically checks schedule and conflicts again.
- Customer can view status, cancel within policy, or request a reschedule.
- No appointment may be created for another customer's request or a technician not assigned to the request.
- No appointment can be booked in the past, outside working hours, across a busy block, or overlapping a confirmed appointment.
- UI must display timestamps in the configured timezone and persist normalized timestamps consistently (prefer UTC in DB, timezone-aware conversion in services).

### Required statuses
`pending`, `confirmed`, `declined`, `reschedule_proposed`, `cancelled`, `completed`, `no_show`.

### Suggested entities (adapt after inspecting the actual schema)
- `technician_weekly_availability`
- `technician_calendar_blocks`
- `appointments`
- optionally `appointment_events` for audit history.

### Concurrency / reliability
- Use MySQL transactions and appropriate row locks / unique constraints. UI-only disabled slots are insufficient.
- Check intervals using half-open ranges `[start, end)`, so adjacent appointments are allowed.
- Revalidate on create, confirm and reschedule.
- Keep an immutable event trail for important state transitions.
- Use an explicit appointment duration from settings; never infer duration from the request type.
- A database race test must prove two overlapping bookings cannot both become confirmed.
- No WebSocket dependency is required for this module; normal refresh/navigation is acceptable initially.

## 5. Module 5.3 — Technician profiles

- Public list: `GET /masters`; profile: `GET /masters/{id}`.
- Public fields: display name, avatar, short headline, bio, specialties, completed request count, average rating and number of reviews.
- Never expose email, phone, internal notes, account identifiers or private request details.
- A technician edits their own profile only. Admin can moderate visibility and edit moderation fields.
- Inactive or non-public technicians must not appear in public lists or leaderboards.
- Avatar uploads must reuse the existing private image validation / authorization strategy. Do not put untrusted original files into `public/`.
- Empty profile fields use a graceful fallback; do not fabricate experience or qualifications.

## 6. Module 5.4 — Reviews, ratings and honour board

### Review rules
- Review can be submitted only by the owner of a request with terminal status `completed`.
- One review per request; rating is integer 1–5; text is optional, up to 1500 chars.
- Review is linked to request, customer and assigned technician.
- No self-reviews, cross-user reviews, duplicate reviews, reviews before completion or review for a request with no assigned technician.
- Admin may hide/unpublish a review with moderation reason. Hidden reviews do not affect aggregates.
- Customer can edit review only within a documented limited window (default: no edits after 24h, or disallow edits in v1); whichever policy is chosen must be enforced server-side.

### Rating integrity
- Show average rating and review count separately.
- Homepage ranking must use a Bayesian/weighted score, not raw average alone. Suggested formula:
  `weighted = (avg * count + global_avg * 5) / (count + 5)`.
- Do not show a fake 5.0 for technicians without reviews.
- Require at least 3 published reviews to enter the top 3/5; before enough data exists show an honest empty state.
- Tie-break using completed request count, then most recent published review, then stable technician ID.
- Public profile shows latest published reviews and the real average/count.

### Homepage
- Replace the "Популярные услуги" feature block with "Доска почёта" TOP-3 or TOP-5.
- Card: avatar, name, specialties, weighted ranking position, real average, number of reviews, completed request count, link to profile.
- Add link to full technician directory.
- If no qualified technicians exist, show "Доска почёта формируется" and a useful CTA; do not invent scores or testimonials.
- Keep the service catalogue accessible from the main navigation.

## 7. Database and architecture rules

- Follow the existing PHP MVC layers: Controller → Service → Repository → MySQL.
- Use the current dependency container, Request/Response, Auth, CSRF and view conventions.
- Every schema change is a new immutable numbered SQL migration. Module 5 owner reserved migrations 015–016 for the knowledge base, 018–020 for profiles/reviews, and 021 for the source-guide reference on repair requests. The calendar agent owns migration 017 for the initial appointment schema and must coordinate before adding further migrations. Never edit an applied migration.
- Never edit an applied migration.
- Add proper foreign keys, indexes, uniqueness constraints and check constraints where supported by MySQL 8.
- Use transactions for multi-table writes and concurrency-sensitive booking.
- Seed only through the current idempotent seed architecture; do not truncate or rewrite existing user/request/demo rows.
- All public pages escape user/editor input with the existing `e()` helper; never render article HTML from arbitrary submitted content.
- All POST endpoints use CSRF and role checks at route and service/policy levels.
- Avoid adding external services / paid APIs / WebSocket as a requirement.
- Preserve local Windows demo compatibility and Docker runtime.

## 8. Testing requirements

Each phase must include:
- Unit tests for validation and calculation logic.
- Integration tests for successful workflows and all role boundaries.
- Negative tests for unauthenticated access, wrong owner, wrong technician, CSRF failure, duplicates, invalid status transitions and malicious input.
- Migration repeatability and fresh-test-schema checks.
- Idempotent seed test.
- Regression run of the existing suite.
- Browser QA at 360, 390, 768 and 1440 px for the delivered pages.
- A final manual demo scenario using the existing customer, technician and admin demo accounts.

No claim of "done" without exact test commands and actual test results. Clearly mark anything not executed locally.

## 9. Branch / PR / Git discipline

- Do not modify `main`, do not merge PR #33, and do not rewrite the Module 4 integration commit.
- Module 5 work lives on a dedicated feature branch.
- Agent work is submitted as a PR targeting the Module 5 integration branch, not main.
- Never force-push or reset the owner's working tree.
- Before editing: inspect branch, HEAD, dirty state and migrations.
- Before each commit: review changed-file list and test diff.
- Keep commits small and descriptive.
- Do not include unrelated formatting or generated files.
- Report commit SHA, branch, files changed, tests run, actual results and known limitations.

## 10. Acceptance scenarios

1. A guest opens the knowledge base, searches "ноутбук не включается", filters results and opens a safe step-by-step article.
2. Customer starts a repair request from an article; the request retains the selected guide context without trusting an arbitrary client-supplied ID.
3. Admin creates a draft article; it stays private. Admin publishes it; it becomes publicly searchable. Admin edits it; public output is escaped.
4. Technician sets recurring working hours, adds a personal unavailable block and sees it on their calendar. Customer must not see the private block title/note.
5. Customer requests device drop-off for a free slot. Technician confirms it. The meeting is visible to both sides and linked to the request.
6. A competing overlapping booking is rejected server-side, including under concurrent requests.
7. Customer cannot book another customer's request or another technician's time.
8. Customer completes an assigned repair, submits one review, and the technician rating is recalculated.
9. A duplicate, self-review, review before completion, and review for an unassigned request are rejected.
10. Public technician profile and homepage honour board use only published, legitimate data. A technician without reviews has no fabricated rating.
11. Existing chat, attachments, 100-service catalogue, admin/customer/technician workflows remain green.

## 11. Agent's immediate task — CAL-01

Implement only Module 5.2 (calendar / appointments), using the newest Module 5 branch as base.

Deliver:
- calendar migration `017_*` (reserved for CAL-01; coordinate with owner before adding any further migration numbers);
- repositories/services/controllers;
- views for technician availability/calendar and customer appointment booking/history;
- server-side role/ownership rules;
- conflict detection with transaction-safe confirmation;
- appointment event history;
- integration tests and regression report.

Do not edit shared integration files listed in Section 2. Provide exact route declarations and a concise integration note for the owner. Open a draft PR against `feature/module-5-knowledge-base-2026-11-20`.

Do not start profile/review/leaderboard work in CAL-01.
