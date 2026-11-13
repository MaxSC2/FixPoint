# FixPoint Design System — v0.2

**Status:** UI-02 visual direction implemented on `feature/ui-02-identity-and-motion`. This is the current design baseline for review, not a claim that every authenticated view is complete.

## Product character

FixPoint uses a **repair dossier / modern workshop** direction. The service should feel like a careful technical workshop: precise, approachable, transparent and human. The visual language combines editorial typography, warm neutral surfaces, deep workshop green-charcoal, cobalt actions and a restrained chartreuse signal accent.

The interface must not resemble a generic AI-generated SaaS template. Avoid decorative gradients, random 3D objects, repeated glass cards, neon effects and unearned statistics.

## Visual principles

1. **Show the process.** Repair is presented as a sequence of understandable events, not an opaque status field.
2. **Evidence before promise.** Diagnosis, estimate and customer approval are distinct stages.
3. **Clear next action.** Every screen should make the next useful action obvious.
4. **Different workspaces, one identity.** Customer, technician and admin experiences may have different density and navigation.
5. **Composition over decoration.** Technical illustration and typography carry meaning; decoration is secondary.
6. **Content integrity.** Never invent prices, addresses, credentials, testimonials, KPIs or real request data.

## Current design tokens

| Token | Value | Use |
|---|---|---|
| `--color-ink` | `#202D30` | Main text |
| `--color-ink-soft` | `#34474A` | Secondary headings |
| `--color-muted` | `#667674` | Supporting text |
| `--color-primary` | `#315FC1` | Main actions and links |
| `--color-primary-dark` | `#254C9E` | Hover |
| `--color-primary-soft` | `#E8EDF8` | Soft action surfaces |
| `--color-bg` | `#F3F1E9` | Warm canvas |
| `--color-surface` | `#FBFAF5` | Panels and content surfaces |
| `--color-surface-soft` | `#F7F5EE` | Secondary surface |
| `--color-border` | `#DCDED5` | Dividers and outlines |
| Signal accent | `#D9ED74` | Small highlights, not body text |
| Workshop dark | `#1D3036` | Dark process areas and footer |
| Success | `#397B59` | Positive status |
| Warning | `#A66A31` | Waiting / approval |
| Danger | `#A84F4F` | Errors / rejected |
| `--radius-card` | `13px` | Standard cards |
| `--radius-control` | `7px` | Buttons and controls |
| `--content-width` | `1260px` | Main page width |

Use CSS custom properties; don't scatter raw brand colors across templates. Status colors remain semantic and always pair with readable text.

## Typography

- Use a local system sans-serif stack; no mandatory CDN font request.
- Use a restrained serif italic accent for selected editorial phrases, not as the main interface font.
- Desktop hero: approximately 52–84 px, responsive with `clamp()`.
- Section headings: approximately 32–54 px.
- Body copy: 14–17 px with comfortable line-height.
- Metadata: normally at least 12 px; essential status and form help must remain comfortably readable.
- Keep heading line lengths controlled and avoid forced line breaks that clip on narrow screens.

## Layout

- Primary content width: 1260 px.
- Breakpoints: 1100 px, 900 px, 640 px.
- Mobile minimum test width: 360 px.
- Touch controls: at least 44 px high.
- Use open rows and strong dividers where information is naturally sequential; don't put every element inside a card.
- Cards are reserved for actual grouped content, not used as the default container for every section.

## Signature components

- Repair dossier hero artwork: `public/assets/images/repair-dossier.svg`.
- Service index rows with number, device glyph, description and clear navigation affordance.
- Four-stage repair process line.
- Repair record / timeline panel, explicitly marked as an illustrative schema.
- Shared status badges, form fields, empty states, data tables and flash alerts.
- Sticky responsive public header and shared footer.

## Motion

- Small interaction feedback: 140–220 ms.
- Content reveal: approximately 650 ms with a restrained vertical offset.
- Motion should clarify hierarchy or state, not delay actions.
- Scroll reveal is progressively enhanced through `IntersectionObserver`.
- If JavaScript is unavailable, all content remains visible.
- Respect `prefers-reduced-motion`; never rely on animation to communicate a status.

## Accessibility and implementation

- Semantic landmarks and one clear H1 per page.
- Real links for navigation; real buttons for actions.
- Explicit field labels and visible focus.
- Text labels accompany status colors.
- Decorative SVGs use empty alt text or are hidden from assistive technology; meaningful SVGs have title/description.
- PHP templates only render data. No SQL, authorization, business rules or fake successful submissions in views.
- Keep production frontend to PHP views, HTML, CSS and vanilla JavaScript.

## Role-specific density

- Customer: low density, clear progress, cost decision, next action and history.
- Technician: efficient queue, compact device/problem context, diagnosis and status actions.
- Admin: information-dense overview, reliable filters, tables and operational metrics from the database.

These role patterns share the same tokens and status language, but not necessarily the same page composition.
