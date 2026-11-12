# FixPoint Asset Guide

**Status:** UI-02 includes the first original production-intended vector asset. The visual library is still small; this document distinguishes completed resources from future needs.

## Asset policy

- Keep third-party references only when source and usage rights are recorded.
- Keep original UI artwork in `public/assets/images/`.
- Do not copy competitor logos, proprietary illustrations or screenshots into production.
- Prefer original SVG for product-specific diagrams and icons.
- Use raster photography only when it adds value that vector art cannot provide.
- Avoid remote image hotlinks. Production assets must be committed locally.
- Give every meaningful image a useful alt description; decorative images use empty alt text.
- Keep SVG accessible, self-contained and free of external dependencies.

## Current original assets

| File | Purpose | Format | Status |
|---|---|---|---|
| `public/assets/images/repair-dossier.svg` | Homepage hero: exploded laptop / repair workbench illustration | SVG | Added in UI-02 |

The illustration is intentionally decorative in the hero and has empty HTML alt text. Its SVG includes its own title and description for standalone use. It is self-contained and does not load remote fonts, images or scripts.

## Future asset opportunities

1. Device/category illustrations where they improve catalogue scanning.
2. Small empty-state artwork for no requests, no search results and completed work.
3. Optional real workshop photography if the project has rights to use suitable images.
4. A consistent icon family for status, diagnosis, estimate and repair events.

Do not generate assets just to fill empty space. Each must support a page task or strengthen the visual identity.

## Reference inventory requirements

For each external reference, record:
- pattern being studied;
- relevant interface area;
- what is adapted rather than copied;
- source URL and access date;
- license/permission status;
- design decision made for FixPoint.

## Image quality requirements

- Prefer SVG for scalable interface art.
- Raster assets should be optimized, appropriately sized and stored locally.
- Avoid text baked into raster images where the same copy belongs in HTML.
- Avoid tiny decorative details that disappear on mobile.
- Check contrast and crop behavior at desktop and mobile breakpoints.
