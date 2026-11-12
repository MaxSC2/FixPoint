<?php
/**
 * Shared minimal style for backend fallback pages.
 * Production design lives in public/assets and app/Views (frontend-owned).
 */
?>
<style>
    :root {
        --fp-ink: #172033;
        --fp-muted: #687386;
        --fp-primary: #3157d5;
        --fp-primary-dark: #2444b3;
        --fp-bg: #f5f7fb;
        --fp-surface: #ffffff;
        --fp-border: #e4e9f1;
        --fp-success: #16845b;
        --fp-warning: #b7791f;
        --fp-danger: #c2414b;
        --fp-radius: 14px;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        background: var(--fp-bg);
        color: var(--fp-ink);
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        line-height: 1.55;
    }
    a { color: var(--fp-primary); }
    a:hover { color: var(--fp-primary-dark); }
    .fp-shell { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
    .fp-header { background: var(--fp-surface); border-bottom: 1px solid var(--fp-border); }
    .fp-header .fp-shell { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; padding-top: 14px; padding-bottom: 14px; }
    .fp-brand { font-weight: 700; font-size: 1.15rem; text-decoration: none; color: var(--fp-ink); }
    .fp-nav { display: flex; flex-wrap: wrap; gap: 16px; }
    .fp-nav a { text-decoration: none; color: var(--fp-muted); font-weight: 500; }
    .fp-nav a[aria-current="page"] { color: var(--fp-primary); }
    main.fp-main { padding: 28px 0 48px; }
    h1, h2, h3 { line-height: 1.2; }
    h1 { font-size: clamp(1.6rem, 4vw, 2.4rem); margin: 0 0 12px; }
    h2 { font-size: 1.25rem; margin: 28px 0 12px; }
    .fp-lead { color: var(--fp-muted); max-width: 68ch; }
    .fp-card { background: var(--fp-surface); border: 1px solid var(--fp-border); border-radius: var(--fp-radius); padding: 20px; }
    .fp-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    .fp-list { display: grid; gap: 12px; padding: 0; list-style: none; margin: 0; }
    .fp-list li { background: var(--fp-surface); border: 1px solid var(--fp-border); border-radius: var(--fp-radius); padding: 16px; }
    .fp-list h3 { margin: 0 0 4px; font-size: 1.05rem; }
    .fp-meta { color: var(--fp-muted); font-size: .9rem; }
    .fp-price { font-weight: 700; white-space: nowrap; }
    .fp-alert { border-radius: 10px; padding: 10px 14px; margin: 0 0 16px; border: 1px solid currentColor; }
    .fp-alert--success { color: var(--fp-success); background: #eaf7f1; }
    .fp-alert--error { color: var(--fp-danger); background: #fdeff0; }
    .fp-alert--info { color: #24568f; background: #eef5ff; }
    .fp-alert--warning { color: var(--fp-warning); background: #fdf6e7; }
    .fp-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; margin-bottom: 20px; }
    .fp-field { display: grid; gap: 4px; min-width: 180px; flex: 1 1 220px; }
    .fp-field label { font-weight: 600; font-size: .9rem; }
    input, select, button {
        font: inherit;
        border-radius: 10px;
        border: 1px solid var(--fp-border);
        padding: 9px 11px;
        background: var(--fp-surface);
        color: var(--fp-ink);
    }
    button, .fp-button {
        background: var(--fp-primary);
        color: #fff;
        border-color: var(--fp-primary);
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-weight: 600;
    }
    button:hover, .fp-button:hover { background: var(--fp-primary-dark); color: #fff; }
    :focus-visible { outline: 3px solid #9db2f5; outline-offset: 2px; }
    .fp-empty { border: 1px dashed var(--fp-border); border-radius: var(--fp-radius); padding: 24px; color: var(--fp-muted); background: var(--fp-surface); }
    .fp-pagination { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 20px; align-items: center; }
    .fp-pagination a, .fp-pagination span { padding: 6px 10px; border-radius: 8px; border: 1px solid var(--fp-border); background: var(--fp-surface); text-decoration: none; }
    .fp-pagination [aria-current="page"] { background: var(--fp-primary); color: #fff; border-color: var(--fp-primary); }
    .fp-footer { border-top: 1px solid var(--fp-border); background: var(--fp-surface); color: var(--fp-muted); font-size: .88rem; }
    .fp-footer .fp-shell { padding-top: 16px; padding-bottom: 16px; display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; }
    .fp-badge { display: inline-block; border-radius: 999px; padding: 2px 9px; background: #eef2fd; color: var(--fp-primary-dark); font-size: .8rem; font-weight: 600; }
    .fp-notice { border-left: 4px solid var(--fp-primary); background: #eef5ff; padding: 12px 16px; border-radius: 0 10px 10px 0; color: #24568f; }
    .fp-stat { font-size: 1.7rem; font-weight: 700; margin: 0; }
    @media (max-width: 520px) {
        .fp-shell { padding: 0 14px; }
        .fp-header .fp-shell { align-items: flex-start; }
        .fp-form { align-items: stretch; }
        button { width: 100%; }
    }
</style>
