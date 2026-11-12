# UI-01 QA Checklist

## Structure and semantics
- [ ] Exactly one page-level H1 per public page.
- [ ] Header navigation has an accessible label and active page state.
- [ ] Skip link reaches the main content.
- [ ] Forms have associated labels; decorative SVGs are hidden from assistive technology.
- [ ] Buttons are buttons; navigation is links.

## Responsive checks
- [ ] 1440 px desktop.
- [ ] 1024 px tablet landscape.
- [ ] 768 px tablet.
- [ ] 640 px mobile breakpoint.
- [ ] 390 px common mobile.
- [ ] 360 px minimum supported width.
- [ ] No horizontal overflow or clipped primary action.
- [ ] Navigation works with keyboard, click, Escape and without JavaScript.

## Content integrity
- [ ] Prices originate from service data and are clearly presented as estimates.
- [ ] No fake testimonials, awards, guarantees, phone numbers or address.
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
- [ ] find app/Views -name '*.php' -print0 | xargs -0 -n1 php -l
- [ ] node --check public/assets/js/app.js
- [ ] Run the project's route smoke tests.
- [ ] Render all four public pages using empty and populated view models.
