# FixPoint — UI/UX refinement principles

## Product model: service center, not a freelance marketplace

FixPoint is organized as a repair-service workflow. The customer is not expected to diagnose the fault, compare individual technicians, or negotiate with several professionals before submitting a request. That would be a different product model.

The current model is intentional:

1. **Choose a device/service direction.** The catalogue helps users understand what the service center handles. Specific services show indicative starting prices where available.
2. **Describe the symptoms.** The request form allows the user to choose “Не уверен / нужна диагностика”. A customer should not need technical knowledge to submit a request.
3. **The service center takes ownership.** An administrator assigns a technician. The technician diagnoses the device and records findings.
4. **The customer makes the financial decision.** The diagnosis and estimate are shown before repair. Work proceeds only after the customer approves.
5. **The request becomes the shared history.** Status changes, comments, estimate decisions and service events stay attached to one request.

This structure is easier to understand and operate than a marketplace with public technician profiles, availability calendars, direct chat, competing quotes and independent booking. The current data model does not contain the entities or permissions needed for such a marketplace. Adding it now would require a new product scope, database design, moderation rules, notifications and additional security testing.

**Decision:** keep the current service-center workflow. Improve its explanation and visual affordances instead of adding a professional marketplace or live chat.

## UI rules

- One clear primary action per screen. Secondary actions must look secondary.
- Service categories describe *what kind of device*; service cards describe *what work may be needed*. Keep these two levels visually distinct.
- Never require customers to identify the fault. “Нужна диагностика” must remain an obvious, valid choice.
- Prices marked “от” are indicative and must never look like a confirmed quote.
- Card grids use consistent spacing, alignment, predictable title wrapping and a stable footer/action area.
- Status, priority and category are separate concepts. They must not compete visually or stretch to the height of unrelated content.
- Keep the existing warm workshop identity, dark teal sections and blue action colour. Use decorative imagery only when it explains the device, process or workshop; avoid filler illustrations.
- Desktop and mobile layouts are reviewed separately. A desktop arrangement must not be treated as a mobile design simply scaled down.

## Findings from the first manual screenshot review

- Customer dashboard mobile hero: the decorative `CLIENT` seal overlaps the primary “Создать заявку” button. Hide the seal at narrow widths.
- Technician queue: priority chip stretches vertically to match the device information block. Align it to the top and let the card content determine its own height.
- Service catalogue: 10 results in a three-column grid leave one isolated final card. At wide desktop widths, let that final item span the row; keep normal two-column behavior on tablet and one-column behavior on mobile.
- Service cards reuse the same device icon and omit the category. Add the category label so users can distinguish service families without changing the data model.
- Service category rows on the home page have a subtle arrow but no explicit action label. Add “Выбрать”.
- Catalogue users who do not know the exact service need a direct route to request diagnostics. Explain this beside the indicative-price note and link to request creation.
- Reduce excessive vertical spacing around the catalogue and workspace screens while retaining enough breathing room for readability.

## Scope of this refinement pass

Changes are presentation and guidance only. No role model, route semantics, request lifecycle, assignment behavior, pricing rules or database schema are changed.

After each pass, rerun PHP/MySQL CI and the Playwright/Chromium visual workflow. Review actual screenshots at desktop, tablet and mobile widths. Automated axe checks do not replace visual inspection or keyboard/screen-reader testing.

## Reference

The generated desktop/mobile mockup is a visual direction only. It is not an implementation screenshot and is not copied as a full page. Existing page structure and real backend behavior remain the source of truth.