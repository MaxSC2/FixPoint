# FixPoint Asset Guide

Status: planning baseline. This document does not claim that a PNG/JPG reference pack exists.

## Asset policy

- Keep third-party references in `design/references/` only when their source and license/usage terms are recorded.
- Keep original project UI artwork in `public/assets/images/`.
- Do not copy competitor logos, proprietary illustrations, or screenshots into production assets.
- Do not use random stock imagery as a substitute for product-specific interface design.
- Prefer CSS/SVG for simple icons and interface decoration; use raster assets only when they add real value.
- Every raster asset must have a meaningful filename, source/creator, license note, intended use, and alt text where applicable.

## Planned folder layout

```text
design/
  references/
    README.md
    sources/
    screenshots/
    moodboards/
  specs/
    DESIGN-SYSTEM.md
    ASSET-GUIDE.md
public/
  assets/
    images/
    icons/
```

## Required reference subjects

1. Public landing page and service-centre value proposition.
2. Service catalogue and service detail.
3. Repair request creation form.
4. Customer dashboard and repair tracking.
5. Technician work queue and repair detail.
6. Administrator dashboard, user/service management, and request table.
7. Responsive mobile navigation and compact ticket view.

## Reference selection criteria

For each reference record:
- what pattern is being studied;
- which part of the interface is relevant;
- what should be adapted, not copied;
- source URL and access date;
- license/permission status;
- design decision made for FixPoint.

## Image generation status

The image-generation quota is currently unavailable in this session. Until original reference images are actually generated or sourced and committed, the team must treat this as a pending deliverable. A textual moodboard is not an image pack.
