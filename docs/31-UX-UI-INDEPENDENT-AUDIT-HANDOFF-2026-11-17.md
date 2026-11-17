# FixPoint — UX/UI audit handoff (17 Nov 2026)

> Review-only task for the independent LM Arena reviewer. Do not modify code, branches, issues, pull requests, or workflow files.

## Repository snapshot

- Repository: https://github.com/MaxSC2/FixPoint
- Branch: `qa/browser-visual-capture`
- Snapshot audited: `e8ee131262239db8286e42d4e97bc5bd2ca975b5`
- PR: https://github.com/MaxSC2/FixPoint/pull/21 (Draft; do not merge)
- QA discussion: https://github.com/MaxSC2/FixPoint/pull/21#issuecomment-6938711378

Before reviewing, check whether the branch has moved. If it has, state the actual SHA and do not attribute findings to the old snapshot without checking the changed files.

## Screenshot package (stored on GitHub Actions)

The full screenshot ZIP is intentionally kept as a workflow artifact rather than committed as a 27 MB binary into the source repository.

- Browser Visual QA run: https://github.com/MaxSC2/FixPoint/actions/runs/44017395348
- Artifact name: `fixpoint-visual-review-75`
- Size at creation: approximately 27.1 MB
- Captures: 139
- Retention noted by GitHub: until 1 Dec 2026
- Open the workflow run, scroll to **Artifacts**, and download `fixpoint-visual-review-75`.

Coverage includes public pages, customer workspace, technician workspace and admin workspace at applicable widths 360, 390, 768, 1024, 1280 and 1440 px. The artifact is the source of truth for screenshot review. Do not claim to have visually inspected images that could not be opened.

Useful screenshot filenames:
- `public__home__desktop-1440.png`
- `public__home__small-mobile-360__viewport.png`
- `public__services__desktop-1440.png`
- `public__login__desktop-1440.png`
- `public__login__small-mobile-360__viewport.png`
- `public__login__mobile-390__viewport.png`
- `customer-kovalev__requests-create__small-mobile-360__viewport.png`
- `customer-kovalev__requests-1__desktop-1440.png`
- `technician-ivanov__technician-requests-1__desktop-1440.png`
- `technician-ivanov__technician-requests-1__mobile-390.png`
- `admin__admin-requests__desktop-1440.png`
- `admin__admin-requests__mobile-390__viewport.png`
- `admin__admin-users__desktop-1440.png`
- `admin__admin-users__mobile-390.png`

## Existing visual observations to verify independently

These are hypotheses/findings from a selective prior review, not instructions to agree with them:

1. Homepage hero illustration: decorative index label and status callout may overlap or lose legibility at narrow widths; inspect `app/Views/pages/public/home.php`, `public/assets/css/app.css` and `public/assets/images/repair-dossier.svg`. The SVG itself is static; inspect the parent frame's reveal/hover transforms and independently positioned callouts.
2. Login: the story/promo panel may dominate the form, especially on 360/390 px; inspect first viewport and whether the actual login fields are visible without scrolling. Check heading clipping at desktop and narrow mobile.
3. Typography: body text is generally around 15 px, but task-relevant labels, metadata and table content may be 10–12 px. Distinguish functional text from decorative indices.
4. Contrast: prior static estimates flagged pale form placeholders and hero micro-labels. Recalculate from actual foreground/background colors and actual font size; report ratios rather than impressions.
5. Admin mobile tables: horizontal scrolling exists, but users may not discover it. Evaluate whether an affordance or mobile record layout is justified.
6. Admin request list may show an assignment action for terminal requests even though the server rejects it. Verify template and service behavior.

## Scope and role separation

The primary coding agent is fixing three P1 business-logic findings concerning estimate approval, estimate mutation and money rounding. Do not edit or propose concurrent code changes to those same functions. This review is UX/UI only; note any visible consequences and report independently.

Review these user journeys:

- Customer: register/login → create request → track status → read diagnosis/estimate → approve/reject estimate → follow repair → completion.
- Technician: queue → inspect device → diagnosis → quote/readiness date → wait for customer decision → repair → ready for pickup → completion.
- Admin: request queue → search/filter → assign technician → users/roles → services/categories → statistics → public content.

## Deliverable

Return a Markdown report in chat (or attach it) with:
- current branch SHA;
- 1–5 scores for overall ergonomics, customer, technician, admin, typography, contrast/accessibility, responsiveness and visual consistency;
- prioritized findings P0–P3 in a table;
- exact screenshot filename and viewport for every visual finding;
- source file/line when a code claim is made;
- distinction between confirmed defect, UX hypothesis and subjective preference;
- minimal actionable recommendation and regression risk;
- top five improvements, what should not be changed, future improvements, and limitations of screenshot-only review.

Do not change repository files or GitHub state.

## Acceptance principle

FixPoint should be convenient for daily work, not merely attractive in screenshots. Preserve its existing Repair Dossier visual identity. Prioritize task clarity, legibility, correct information hierarchy, feedback and safe actions over decorative animation or a total redesign.
