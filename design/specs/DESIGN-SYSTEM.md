# FixPoint Design System — v0.1

Status: working baseline for UI-001. Validate after reviewing actual visual references; do not treat this as a final brand system.

## Product character

FixPoint is a practical computer and electronics repair service portal. The interface should feel reliable, precise, calm, and operational. Avoid gamer aesthetics, neon cyberpunk styling, excessive gradients, glassmorphism, and decorative dashboards that hide the next action.

## Visual principles

1. Status before decoration: users should immediately understand what is happening with a repair.
2. One primary action per screen or major section.
3. Use consistent page widths and spacing.
4. Dense operational tables are acceptable on desktop, but must become readable cards or horizontally managed views on mobile.
5. Distinguish estimated prices from confirmed prices.
6. Every form needs visible labels, validation, loading, success, and error states.
7. Color must not be the only way to communicate status.

## Provisional tokens

| Token | Value | Use |
|---|---|---|
| Brand / primary | `#2563EB` | primary actions, links, active navigation |
| Primary hover | `#1D4ED8` | hover/focus interaction |
| Ink | `#172033` | headings and main text |
| Muted text | `#64748B` | secondary labels |
| Page background | `#F5F7FB` | app canvas |
| Surface | `#FFFFFF` | cards and panels |
| Border | `#E2E8F0` | dividers and outlines |
| Success | `#15803D` | completed / accepted |
| Warning | `#B45309` | waiting / approval needed |
| Danger | `#B91C1C` | rejected / destructive errors |
| Info | `#0369A1` | informational state |

These values are provisional. Keep them as CSS custom properties so the palette can be revised centrally.

## Typography

- Use a system sans-serif stack initially; avoid loading remote fonts before performance and licensing are checked.
- Page title: 28–32 px desktop, 24–28 px mobile.
- Section title: 18–22 px.
- Body: 14–16 px, line-height 1.45–1.6.
- Metadata: 12–13 px; never use tiny text for critical status or form help.

## Layout and spacing

- Desktop content max width: 1200 px.
- Main page padding: 32 px desktop, 20 px tablet, 16 px mobile.
- Spacing scale: 4, 8, 12, 16, 24, 32, 40, 48 px.
- Cards: 12–16 px radius, 1 px border, restrained shadow only where elevation clarifies hierarchy.
- Buttons and inputs: minimum 44 px touch height on mobile.
- Breakpoints: 640 px, 768 px, 1024 px, 1280 px.

## Components required for UI-001

- Header with logo/wordmark, public navigation, auth actions.
- Mobile navigation with accessible open/close behavior.
- Main container and page header.
- Primary, secondary, tertiary, and danger buttons.
- Text, email, telephone, password, select, textarea, and file inputs.
- Form field help, validation error, and success message.
- Cards, service cards, status badges, alerts, tables, pagination.
- Empty, loading, and error states.
- Footer with contacts and legal links placeholders.

## Repair status semantics

| Status | Meaning | Visual treatment |
|---|---|---|
| new | Request received | info |
| accepted | Accepted by service centre | info |
| diagnostics | Device is being diagnosed | info |
| awaiting_customer_approval | Customer decision required | warning |
| waiting_parts | Waiting for required parts | warning |
| repairing | Repair work in progress | info |
| ready_for_pickup | Ready for collection | success |
| completed | Work completed and handed over | success |
| rejected | Request rejected | danger |
| cancelled | Cancelled | neutral |

Always pair a color with a text label and, where useful, an icon.

## Accessibility baseline

- Semantic landmarks and heading order.
- Explicit labels for every input.
- Keyboard-visible focus states.
- Sufficient contrast for text and controls.
- Buttons must be real buttons; navigation must be links.
- Error messages must be associated with the relevant field.
- Respect reduced-motion preferences.

## Page composition

### Public landing page
1. Header.
2. Hero with clear proposition and one primary CTA to create a repair request.
3. Service categories / popular services.
4. Three-step repair process.
5. Trust and quality information without fabricated testimonials or certifications.
6. Contact/location block.
7. Footer.

### Service catalogue
- Search and category filter.
- Service cards with title, short scope, estimated price or “from” label, and details CTA.
- Clear disclaimer that final price follows diagnostics.

### Auth pages
- Compact centered form surface.
- Clear validation and password visibility control.
- Link between sign-in and registration.
- No decorative illustration that competes with the form.

### Authenticated workspaces
- Customer: repair overview, status timeline, next required action, history.
- Technician: assigned queue, priority, device details, diagnosis, action log.
- Administrator: KPIs, filters, request management, users, technicians, services.

## Implementation note

The design system must be implemented with CSS variables and reusable components. Avoid scattering raw colors, spacing values, or one-off button styles across pages.
