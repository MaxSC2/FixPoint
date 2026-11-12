# UI-01 / UI-02 QA Checklist

## Structure and semantics
- [ ] Exactly one page-level H1 per public page.
- [ ] Header navigation has an accessible label and active page state.
- [ ] Skip link reaches the main content.
- [ ] Forms have associated labels; decorative SVGs have empty alt text or are hidden from assistive technology.
- [ ] Buttons are buttons; navigation is links.
- [ ] Illustration is local and does not depend on a third-party URL.

## Responsive checks
- [ ] 1440 px desktop.
- [ ] 1280 px desktop.
- [ ] 1024 px tablet landscape.
- [ ] 768 px tablet.
- [ ] 390 px common mobile.
- [ ] 360 px minimum supported width.
- [ ] No horizontal overflow or clipped primary action.
- [ ] Hero title, illustration annotations and CTA do not overlap.
- [ ] Navigation works with keyboard, click, Escape and without JavaScript.

## Motion and accessibility
- [ ] Scroll reveal works when IntersectionObserver is available.
- [ ] Content remains visible if JavaScript is disabled or IntersectionObserver is unavailable.
- [ ] `prefers-reduced-motion: reduce` disables non-essential motion.
- [ ] Keyboard focus remains visible over light and dark surfaces.
- [ ] Text and status labels remain readable without color.

## Content integrity
- [ ] Prices originate from service data and are clearly presented as estimates.
- [ ] No fake testimonials, awards, guarantees, phone numbers or address.
- [ ] Illustrative repair timeline is labelled as an example.
- [ ] Empty catalog is a valid state, not a hardcoded fake list.
- [ ] Contact placeholders explicitly indicate missing settings.
- [ ] Internal repair comments are not exposed in customer views.

## PHP/backend integration
- [ ] e() helper exists and escapes HTML special characters using UTF-8.
- [ ] ViewRenderer resolves fixed logical names only.
- [ ] Shared layout data is always passed.
- [ ] CSRF token is present on logout form for authenticated sessions.
- [ ] Routes for public pages and registration/login are registered.
- [ ] View data matches docs/16-VIEW-TEMPLATE-CONTRACT.md.

## Automated checks
- [ ] `find app/Views -name '*.php' -print0 | xargs -0 -n1 php -l`
- [ ] `node --check public/assets/js/app.js`
- [ ] Run the project's route smoke tests.
- [ ] Render all four public pages using empty and populated view models.
- [ ] Verify CSS mobile-navigation rules parse as separate selectors.
## UI-03 customer workspace
- [ ] Customer overview renders only controller-provided recent requests and has a useful empty state.
- [ ] Request list uses the backend-scoped list model, filters and pagination.
- [ ] Request creation submits all documented fields with POST + CSRF.
- [ ] Request detail displays only customer-visible comments and history.
- [ ] Estimate approve/reject controls are shown only for the provided presentation permission and the expected status; backend still enforces authorization.
- [ ] Comment form is shown only when can_comment is true and submits POST + CSRF.
- [ ] No sample request numbers, fabricated statuses, costs or dates appear in empty states.
- [ ] Request list, create and detail pages are usable at 390px and 360px.
