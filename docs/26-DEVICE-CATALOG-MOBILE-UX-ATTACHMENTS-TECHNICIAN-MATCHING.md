# FixPoint — каталог устройств, mobile-first UX, вложения и подбор мастера

**Статус:** product/architecture proposal for the next implementation pass.  
**Branch:** `qa/browser-visual-capture`  
**Constraint:** preserve the existing service-center workflow and PHP/MySQL architecture.

## Product decision

FixPoint remains a repair service center, not an open marketplace. A customer should be able to describe a problem without knowing the exact device model or technical cause. The service center remains responsible for assigning the specialist, managing the repair, explaining the estimate and maintaining the request history.

Do not introduce public technician booking, independent price bids or a customer-to-technician marketplace. These would create a separate product with availability calendars, booking/payment rules, profile moderation, cancellation flows, notifications and much broader authorization requirements.

## 1. Device identity without a giant device database

### Customer selection flow

1. **Device type:** compact choices such as laptop, desktop PC, phone, tablet, monitor, console, printer and other equipment.
2. **Manufacturer:** a curated grid of recognizable manufacturer marks with a search field. Show a handful of common manufacturers first, not hundreds of brands at once.
3. **Series / family:** optional text or popular family suggestions where useful. Example: ASUS → TUF Gaming. A series is not a separate manufacturer.
4. **Model:** optional text/autocomplete. Example: TUF Gaming F15 / FX506LH. Never require an exact model to create a request.
5. **Problem:** plain-language description. Keep “Не знаю / нужна диагностика” as a valid option.

### Data strategy

- Start with a curated static catalogue/JSON so the feature does not require importing a huge list of phones and laptops.
- Store a canonical manufacturer name and normalize aliases such as `ASUSTeK` → `ASUS`, `Apple` / `iPhone` → `Apple`, and known spelling variants.
- Use one consistent brand logo/mark per manufacturer; display device series and model as text. Do not search for a separate image/logo for every individual device model.
- For unknown or niche manufacturers, use the generic device-type icon and preserve the user's typed brand/model.
- Do not guess the exact model from an image or logo. Visual identification may suggest a brand, but the customer can correct it.
- Existing `repair_requests.device_type`, `device_brand` and `device_model` fields already accept text, so an initial curated picker can preserve backend compatibility without a schema migration.

### Example

`Ноутбук → ASUS (brand mark) → TUF Gaming (series) → F15 / FX506LH (optional model) → Describe the symptoms.`

## 2. A genuinely mobile-first interface

Mobile should not be a desktop page with oversized cards squeezed into a narrow viewport. Keep the desktop workspace, but give phones a focused interaction pattern.

- Compact top bar with clear back navigation and page title.
- Four-item bottom navigation: Overview, Requests, Create, Profile. Respect safe-area insets and never cover the last form control.
- A short request wizard: Device → Problem → Photos → Review & send. Preserve the same backend POST fields and validation rules.
- Device types and brand marks should use compact, equal-height rows/tiles. A searchable bottom sheet or collapsible brand list is preferable to a huge alphabetized card wall.
- Keep model as optional. Do not punish users who only know “Samsung phone” or “ASUS TUF laptop”.
- One primary action per screen, placed in a reachable lower area. Sticky actions must reserve bottom padding so they do not cover content.
- Keep readable body text around 14–16 px; use compact labels and metadata rather than shrinking all content. Touch targets should generally be at least 44 px.
- Use single-column request history lists with clear status, device, request number, latest update and one obvious “Open” action.
- Keep filters in a collapsible panel on phones; do not put six desktop filter controls on one mobile row.
- Use accordions for secondary details, not for the main status or estimate decision.

## 3. Photos and an asynchronous conversation

### Current capability

The application already has `request_comments` with author, request, body, timestamp and `is_internal`. Customer-facing comments and internal staff notes are separate. The request pages already render comments. There is no attachment/media table or secure image upload flow yet.

### Recommended experience

- Attach up to five photos to the initial request, or add photos to a later message.
- Allow camera/gallery selection on mobile (`image/*`, environment camera hint where supported), show thumbnails and remove/reorder controls before sending.
- Add a short optional caption to each image or message.
- Customer and technician messages appear in one chronological request discussion; show sender, timestamp and image previews.
- Internal staff notes stay in the staff workspace and must never appear in the customer conversation.
- Treat this as asynchronous case discussion, not a promise of instant live chat. A refresh/new-message indicator is enough for the first version; notifications and push delivery can follow.
- Include a clear message such as “Мастер ответит в этой заявке. О новых сообщениях мы сообщим, когда уведомления будут подключены.” Do not promise an email/SMS/push notification until that channel exists.

### Storage and security requirements

- Add a `request_attachments` table with request ID, optional comment ID, uploader ID, opaque storage key, verified MIME type, byte size, image dimensions and created timestamp.
- Store image bytes outside the public web root. Serve them through an authenticated controller that re-checks request ownership or staff assignment on every read.
- Validate real file content with MIME inspection and image decoding; never trust the browser-provided filename or content type.
- Allow only safe raster formats supported by the deployment (initially JPEG, PNG and WebP). Reject SVG, HTML, executable files and archives.
- Set a per-file size cap and per-request count cap; a reasonable first UI target is five images, with a server-side cap such as 8–10 MB per image.
- Re-encode images where the available image library permits it to remove EXIF metadata, including GPS coordinates. Preserve the original only if there is a documented business need.
- Use random storage keys, private cache headers and `X-Content-Type-Options: nosniff`. Never expose filesystem paths or raw public storage URLs.
- Enforce CSRF, authentication, rate/size limits, ownership/assignment checks and deletion/retention policy.
- Keep metadata and permissions in MySQL; image content remains in private filesystem/object storage.

## 4. Technician capabilities and explainable recommendations

### Technician profile

Do not ask a technician to list every individual model they have ever repaired. Use broad, useful capabilities:

- Device types: laptops, desktops, phones, tablets, consoles, printers, monitors and other equipment.
- Manufacturers: ASUS, Apple, Samsung, Lenovo, Dell, HP, Xiaomi, Sony, etc.
- Service skills: diagnostics, cooling/thermal service, power/charging, display, data recovery, operating-system repair, board-level work and similar categories.
- Optional proficiency level or experience note.

Use an admin-managed capability-tag dictionary and a many-to-many mapping from technician to tags. A technician may propose their capabilities, while an administrator can mark them verified. Do not represent self-declared expertise as certified.

### First matching algorithm: deterministic, no AI required

1. Exclude inactive technicians and anyone who is not eligible for assignment.
2. Match the request's device category and required service first.
3. Add a brand match where the request contains a known manufacturer.
4. Use current assigned/open request count as a workload signal, not as a claim of real-time availability.
5. Return a short ranked candidate list with explanations such as `ASUS laptops`, `cooling service`, `2 active requests`.
6. Let the administrator choose and confirm the assignment. Never let an LLM assign a request or change lifecycle status by itself.

Example candidate score can be based on exact service match, device category match, brand match and current workload. Keep the weights in code/configuration and expose the match reasons so administrators can understand why a technician is recommended.

## 5. Google AI: useful layer, not the source of truth

Two different Google offerings are relevant; they should not be confused:

- **Gemini API** is a hosted cloud API. The current Gemini 3.8 Flash model supports image input, structured outputs and function calling. Its API free tier has limited model access/quotas. Google states that content submitted to unpaid services can be used to improve products, and human reviewers may process it; Google's terms specifically say not to submit sensitive, confidential or personal information to unpaid services.
- **Gemma 4** is an open-weight model family that can be downloaded and run on compatible devices or servers. Google's current guidance lists E2B for mobile devices, E4B for mobile/laptops, 12B for laptops/desktops/small servers, 26B A4B for desktops/small servers and 31B for larger servers.

### Practical recommendation

- Do not put a free-tier Gemini key into a public browser or expose it in JavaScript. Calls must go through the backend, with quotas and rate limits.
- Do not send customer photos, names, contact details, serial numbers or private request history to a free-tier hosted API.
- Start without AI: deterministic capability matching is easier to test, cheaper, explainable and sufficient for the first recommendation feature.
- Later, an optional AI triage service may convert a problem description (and, only with explicit consent under a suitable data policy, a photo) into structured tags such as device category, manufacturer, symptom family and requested service.
- The backend validates these tags against its own catalogue, scores eligible technicians, and returns ranked suggestions. AI does not create a diagnosis, estimate cost or assign a technician without human review.
- Use a provider interface so Gemini API, a self-hosted Gemma model or no AI at all can be selected by configuration.
- If local inference is desired, benchmark a small Gemma 4 E4B quantized build before planning around it. The official target class includes laptops, but actual speed and memory use must be measured on the deployment hardware.

## 6. Delivery order

### Phase A — low-risk UX, existing schema

- Curated manufacturer catalogue with aliases and brand marks.
- Device-type → manufacturer → optional series/model selection.
- Mobile-first request creation flow using existing form fields and endpoint.
- Compact mobile bottom navigation and request lists.

### Phase B — attachments and case discussion

- Private upload storage and `request_attachments` migration.
- Photos on initial requests and existing comments.
- Customer/technician thread UI and strict internal-note separation.
- Upload security, image processing and retention tests.

### Phase C — specialist capability profiles

- Capability tag dictionary and technician-to-capability mapping migration.
- Technician capability editor and administrator verification.
- Explainable candidate ranking using skills and current workload.
- Human-confirmed assignment remains the only assignment path.

### Phase D — optional AI triage

- Provider abstraction and structured output contract.
- Text categorization first; image input only after privacy/consent decisions.
- Server-side validation and deterministic candidate ranking.
- Explicit fallback when AI is unavailable or over quota.

## Acceptance principles

- A customer can create a request knowing only the device category and symptoms.
- A customer never has to pick the exact service or exact model to proceed.
- Manufacturer marks identify brands; series/model remain optional text, not an endless image catalogue.
- The mobile request flow is designed for one-hand operation and is tested at 360, 390 and 430 px.
- All request photos are private and authorized per request; internal notes never leak to customers.
- Technician recommendations include an explanation and require administrator confirmation.
- The application works fully with AI disabled.

## Current implementation boundary

This document records the next product direction. It does not claim that uploads, technician skill profiles, AI triage or real-time notifications already exist. Those features require their own migrations, authorization tests and browser coverage.