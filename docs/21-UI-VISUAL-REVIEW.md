# FixPoint — UI visual review 2026-11

**Scope:** source-based review of the current `develop` UI, plus a generated visual concept used as a composition reference. This is not a browser screenshot of the running PHP application. Runtime rendering and interaction still require the Docker/browser environment.

## 1. Current visual direction

The current implementation already has a distinct Repair Dossier / Modern Workshop identity:

- warm neutral page canvas (`#f3f1e9`) and surface (`#fbfaf5`);
- ink / deep workshop background (`#202d30`, `#1d3036`);
- blue action color (`#315fc1`) and restrained lime signal (`#d9ed74`);
- editorial serif emphasis in hero headings;
- technical SVG artwork and numbered process language;
- shared responsive rules in `public/assets/css/app.css`.

The existing homepage template is not a generic card-grid landing page. Its service section is an editorial list of four rows (laptop, desktop, smartphone, peripherals), followed by a dark process section. Keep that intentional composition. The generated concept image is only a direction reference; it incorrectly turns the service rows into a 2x2 card grid and must not be treated as a faithful screenshot or implementation spec.

## 2. Findings and proposed tuning

### P1 — muted text contrast needs a targeted pass

Contrast calculations from the current CSS token values:

| Foreground | Background | Contrast | Assessment |
|---|---|---:|---|
| `#667674` | `#f3f1e9` | 4.21:1 | Below WCAG AA 4.5:1 for normal text |
| `#5d6d69` | `#f3f1e9` | 4.81:1 | Pass |
| `#77847d` | `#fbfaf5` | 3.73:1 | Below AA for normal text |
| `#aebdb2` | `#1d3036` | 7.02:1 | Pass |
| `#b9c6bc` | `#1d3036` | 7.76:1 | Pass |
| `#65736f` | `#fbfaf5` | 4.74:1 | Pass |

The global muted token and small visual-note/index text are the first candidates to darken. Do not globally darken every secondary color: several current combinations already pass and are part of the intended hierarchy. Recheck hover, disabled, error and status states separately.

### P2 — protect the hero at compact desktop widths

The hero remains a two-column composition down to 901 px. At the 901–1100 px breakpoint the text and artwork compete for width. Browser QA should inspect 1024 px specifically for:
- heading wrapping into awkward single-word lines;
- the floating note colliding with the artwork/container edge;
- CTA wrapping and vertical alignment;
- hero height becoming disproportionate.

If the rendered page is cramped at 1024 px, switch the hero to one column at a slightly higher breakpoint (for example 980–1020 px) rather than shrinking the type until it becomes timid.

### P2 — mobile header and hero need real viewport captures

The CSS has dedicated rules at 900 px and 640 px, and the mobile hero intentionally stacks copy before artwork. Validate 390 px and 360 px:
- no horizontal page overflow;
- the account/login actions do not collide with the menu toggle;
- the visual note stays inside the artwork bounds;
- the hero CTA buttons remain full width and clearly separated;
- the four service rows keep readable descriptions and tap targets.

### P3 — simplify the stylesheet's token history

The stylesheet declares an earlier blue/white token set and later overrides it with the workshop palette. The cascade currently resolves to the intended later values, but the duplicate root definitions make future maintenance harder and can mislead contributors reviewing only the first block. Consolidate tokens in a dedicated first block after confirming no legacy selectors depend on the earlier values. This is cleanup, not a visual blocker.

## 3. Recommended visual direction

Keep the current visual identity. Do not replace it with a generic dashboard/SaaS kit.

1. Preserve the warm canvas, dark workshop process panel, blue primary actions and restrained lime signal.
2. Keep service rows on the homepage; use compact cards only where the actual catalogue data is displayed.
3. Make secondary copy slightly darker only where contrast fails.
4. Keep headings expressive, but avoid reducing mobile body copy below comfortable reading size.
5. Treat status color as reinforcement only; keep text labels visible.
6. Prefer spacing and alignment corrections over adding more shadows, gradients or decorative metrics.

## 4. Render and QA status

- [x] Read current homepage template, shared header, auth login template and UI specification.
- [x] Read current stylesheet token, hero, service-list, process, auth and responsive sections.
- [x] Generate a visual composition reference based on the existing copy and visual identity.
- [x] Calculate contrast for representative foreground/background pairs.
- [ ] Render the actual PHP templates against the current backend in a browser.
- [ ] Capture actual 1440, 1024, 768, 390 and 360 px screenshots.
- [ ] Test keyboard focus, mobile navigation, form errors, reduced motion and long content.
- [ ] Apply CSS changes only after reviewing actual rendered output.

**Important:** No claim is made that the running application has been browser-tested in this environment. The image concept is a visual proposal, not evidence of runtime rendering.
