'use strict';

const fs = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8080';
const PASSWORD = process.env.SEED_PASSWORD || 'FixPoint!VisualQA';
const OUTPUT_DIR = path.resolve('artifacts/visual-review');

const VIEWPORTS = [
    { name: 'desktop-1440', width: 1440, height: 1000 },
    { name: 'desktop-1280', width: 1280, height: 900 },
    { name: 'compact-1024', width: 1024, height: 900 },
    { name: 'mobile-390', width: 390, height: 844 },
];
const EXTRA_VIEWPORTS = [
    { name: 'tablet-768', width: 768, height: 1024 },
];
const SMALL_MOBILE_VIEWPORT = { name: 'small-mobile-360', width: 360, height: 800 };
const PUBLIC_ROUTES = ['/', '/services', '/services/laptop-cleaning', '/about', '/contacts', '/login', '/register'];

const ROLE_SESSIONS = [
    { role: 'customer-kovalev', email: 'customer.kovalev@fixpoint.test', routes: ['/account', '/requests', '/requests/create', '/requests/1', '/requests/3'] },
    { role: 'technician-ivanov', email: 'master.ivanov@fixpoint.test', routes: ['/technician/requests', '/technician/requests/1'] },
    { role: 'technician-smirnova', email: 'master.smirnova@fixpoint.test', routes: ['/technician/requests/3'] },
    { role: 'admin', email: 'admin@fixpoint.test', routes: ['/admin', '/admin/requests', '/admin/requests/1/assign', '/admin/users', '/admin/users/1/edit', '/admin/users/2/edit', '/admin/services', '/admin/services/1/edit', '/admin/categories', '/admin/categories/1/edit', '/admin/content/about', '/admin/statistics'] },
];
const SMALL_MOBILE_ROUTES = new Set([
    ...PUBLIC_ROUTES,
    ...ROLE_SESSIONS.flatMap((session) => session.routes),
]);

function slug(value) {
    return value === '/' ? 'home' : value.replace(/^\/|\/$/g, '').replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-|-$/g, '');
}
function safeRole(role) {
    return role.replace(/[^a-zA-Z0-9-]+/g, '-');
}

async function login(page, email, role) {
    await page.goto(BASE_URL + '/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill(PASSWORD);
    await page.locator('form.auth-form button[type="submit"]').click();
    try {
        await page.waitForURL((url) => url.pathname !== '/login', { timeout: 10000 });
    } catch {
        const body = await page.locator('body').innerText().catch(() => '');
        throw new Error('Login failed for ' + role + ': ' + body.slice(0, 260));
    }
}

async function revealWholePage(page) {
    // The page's IntersectionObserver is designed for user-paced scrolling.
    // Full-page screenshot capture scrolls faster than a person, so explicitly
    // settle every reveal target before taking a screenshot.
    await page.addStyleTag({ content: '.js [data-reveal] { opacity: 1 !important; transform: none !important; transition: none !important; animation: none !important; }' });
    await page.evaluate(() => document.querySelectorAll('[data-reveal]').forEach((element) => element.classList.add('is-visible')));
    await page.evaluate(async () => {
        const pause = () => new Promise((resolve) => requestAnimationFrame(() => resolve()));
        const step = Math.max(300, Math.floor(window.innerHeight * 0.72));
        const max = document.documentElement.scrollHeight;
        for (let y = 0; y < max; y += step) {
            window.scrollTo(0, y);
            await pause();
        }
        window.scrollTo(0, 0);
        await pause();
    });
    await page.evaluate(() => document.fonts ? document.fonts.ready : Promise.resolve());
}

async function inspectPage(page) {
    return page.evaluate(() => {
        const hasName = (element) => {
            const aria = element.getAttribute('aria-label') || element.getAttribute('aria-labelledby');
            if (aria && aria.trim()) return true;
            if (element.tagName === 'INPUT' && element.type === 'hidden') return true;
            if (element.labels && element.labels.length > 0) return true;
            if (element.getAttribute('title')) return true;
            return Boolean((element.innerText || element.value || '').trim());
        };
        const controls = Array.from(document.querySelectorAll('button, input, select, textarea'));
        const links = Array.from(document.querySelectorAll('a[href]'));
        const imgs = Array.from(document.images);
        const workspaceNav = document.querySelector('.workspace-nav');
        const mobileCustomerNav = document.querySelector('.mobile-customer-nav');
        const workspaceNavVisible = Boolean(workspaceNav)
            && getComputedStyle(workspaceNav).display !== 'none'
            && getComputedStyle(workspaceNav).visibility !== 'hidden';
        const duplicateMobileCustomerNavigation = window.innerWidth <= 760
            && Boolean(mobileCustomerNav)
            && workspaceNavVisible;
        return {
            title: document.title,
            hasMobileCustomerNav: Boolean(mobileCustomerNav),
            workspaceNavVisible,
            duplicateMobileCustomerNavigation,
            h1: Array.from(document.querySelectorAll('h1')).map((node) => node.innerText.trim()),
            h1Count: document.querySelectorAll('h1').length,
            viewportWidth: window.innerWidth,
            documentWidth: document.documentElement.scrollWidth,
            bodyWidth: document.body.scrollWidth,
            documentHeight: document.documentElement.scrollHeight,
            horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
            controlsWithoutName: controls.filter((el) => !hasName(el)).map((el) => ({ tag: el.tagName.toLowerCase(), type: el.getAttribute('type') || '', id: el.id || '', name: el.getAttribute('name') || '' })),
            linksWithoutName: links.filter((el) => !hasName(el)).map((el) => el.href),
            imagesWithoutAlt: imgs.filter((el) => !el.hasAttribute('alt')).map((el) => el.src),
            imageLoadFailures: imgs.filter((el) => !el.complete || el.naturalWidth === 0).map((el) => el.src),
        };
    });
}

async function captureRoute(page, role, route, viewport, results, axeResults, fatalErrors) {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    let response = null;
    try {
        response = await page.goto(new URL(route, BASE_URL).toString(), { waitUntil: 'domcontentloaded', timeout: 20000 });
        await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
        await page.locator('body').waitFor({ state: 'visible', timeout: 5000 });
        await revealWholePage(page);
    } catch (error) {
        fatalErrors.push(role + ' ' + route + ' @ ' + viewport.name + ': navigation: ' + error.message);
    }

    const metrics = await inspectPage(page).catch((error) => ({ inspectError: error.message }));
    const finalPath = new URL(page.url()).pathname;
    const status = response ? response.status() : null;
    const file = safeRole(role) + '__' + slug(route) + '__' + viewport.name + '.png';
    const screenshotPath = path.join(OUTPUT_DIR, file);
    let viewportScreenshot = null;
    try {
        await page.screenshot({ path: screenshotPath, fullPage: true, animations: 'disabled' });
        if (viewport.width <= 760) {
            viewportScreenshot = safeRole(role) + '__' + slug(route) + '__' + viewport.name + '__viewport.png';
            await page.screenshot({ path: path.join(OUTPUT_DIR, viewportScreenshot), fullPage: false, animations: 'disabled' });
        }
    } catch (error) {
        fatalErrors.push(role + ' ' + route + ' @ ' + viewport.name + ': screenshot: ' + error.message);
    }

    const row = { role, route, viewport: viewport.name, status, finalPath, redirected: finalPath !== route, screenshot: file, viewportScreenshot, ...metrics };
    results.push(row);
    if (status !== null && status >= 400) fatalErrors.push(role + ' ' + route + ' @ ' + viewport.name + ': HTTP ' + status);
    if (row.redirected && !(route === '/login' && finalPath === '/login')) fatalErrors.push(role + ' ' + route + ' @ ' + viewport.name + ': redirected to ' + finalPath);
    if (metrics.horizontalOverflow) fatalErrors.push(role + ' ' + route + ' @ ' + viewport.name + ': horizontal overflow (' + metrics.documentWidth + 'px > ' + viewport.width + 'px)');
    if (metrics.duplicateMobileCustomerNavigation) fatalErrors.push(role + ' ' + route + ' @ ' + viewport.name + ': duplicate customer navigation is visible on mobile');

    const shouldRunAxe = viewport.name === 'desktop-1440'
        || (role === 'public' && route === '/login'
            && ['mobile-390', 'small-mobile-360'].includes(viewport.name));
    if (shouldRunAxe) {
        try {
            const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
            axeResults.push({
                role, route, viewport: viewport.name,
                violations: axe.violations.map((v) => ({
                    id: v.id, impact: v.impact, description: v.description, help: v.help,
                    helpUrl: v.helpUrl, nodes: v.nodes.length,
                    details: v.nodes.map((node) => ({
                        target: node.target,
                        html: node.html,
                        failureSummary: node.failureSummary,
                        checks: [...node.any, ...node.all, ...node.none].map((check) => ({
                            id: check.id,
                            message: check.message,
                            data: check.data,
                        })),
                    })),
                })),
            });
        } catch (error) {
            axeResults.push({ role, route, scanError: error.message });
        }
    }
}

async function main() {
    await fs.mkdir(OUTPUT_DIR, { recursive: true });
    const results = [];
    const axeResults = [];
    const fatalErrors = [];
    const browserErrors = [];
    const interactionChecks = [];
    const browser = await chromium.launch({ headless: true });

    try {
        const publicContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        const publicPage = await publicContext.newPage();
        publicPage.on('pageerror', (error) => browserErrors.push('public: ' + error.message));
        for (const route of PUBLIC_ROUTES) {
            let viewports = route === '/' ? VIEWPORTS.concat(EXTRA_VIEWPORTS) : VIEWPORTS;
            if (SMALL_MOBILE_ROUTES.has(route) && !viewports.some((viewport) => viewport.name === SMALL_MOBILE_VIEWPORT.name)) {
                viewports = viewports.concat(SMALL_MOBILE_VIEWPORT);
            }
            for (const viewport of viewports) {
                await captureRoute(publicPage, 'public', route, viewport, results, axeResults, fatalErrors);
            }
        }

        // The login guidance is a native disclosure. Verify the default state,
        // pointer activation and keyboard activation rather than only capturing
        // its collapsed appearance.
        try {
            await publicPage.setViewportSize({ width: 390, height: 844 });
            await publicPage.goto(BASE_URL + '/login', { waitUntil: 'domcontentloaded' });
            const disclosure = publicPage.locator('.auth-story__details');
            const disclosureCount = await disclosure.count();
            if (disclosureCount !== 1) {
                throw new Error('Expected exactly one login help disclosure, found ' + disclosureCount);
            }

            const summary = disclosure.locator('summary');
            const closedByDefault = !(await disclosure.evaluate((element) => element.open));
            const hiddenByDefault = !(await disclosure.locator('.auth-story__steps').isVisible());

            await summary.click();
            const opensByPointer = await disclosure.evaluate((element) => element.open);
            const stepsVisibleWhenOpen = await disclosure.locator('.auth-story__steps').isVisible();

            await summary.focus();
            await publicPage.keyboard.press('Enter');
            const closesByKeyboard = !(await disclosure.evaluate((element) => element.open));

            const check = {
                role: 'public',
                route: '/login',
                feature: 'help-disclosure',
                viewport: 'mobile-390',
                closedByDefault,
                hiddenByDefault,
                opensByPointer,
                stepsVisibleWhenOpen,
                closesByKeyboard,
            };
            interactionChecks.push(check);
            if (Object.values(check).some((value) => value === false)) {
                fatalErrors.push('public /login: help disclosure interaction check failed: ' + JSON.stringify(check));
            }
        } catch (error) {
            fatalErrors.push('public /login: help disclosure interaction check: ' + error.message);
        }

        for (const session of ROLE_SESSIONS) {
            const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
            const page = await context.newPage();
            page.on('pageerror', (error) => browserErrors.push(session.role + ': ' + error.message));
            try {
                await login(page, session.email, session.role);
                for (const route of session.routes) {
                    let viewports = ['/account', '/admin', '/technician/requests'].includes(route)
                        ? VIEWPORTS.concat(EXTRA_VIEWPORTS) : VIEWPORTS;
                    if (SMALL_MOBILE_ROUTES.has(route) && !viewports.some((viewport) => viewport.name === SMALL_MOBILE_VIEWPORT.name)) {
                        viewports = viewports.concat(SMALL_MOBILE_VIEWPORT);
                    }
                    for (const viewport of viewports) {
                        await captureRoute(page, session.role, route, viewport, results, axeResults, fatalErrors);
                    }
                }
            } catch (error) {
                fatalErrors.push(error.message);
            }
            await context.close();
        }
        await publicContext.close();
    } finally {
        await browser.close();
    }

    for (const item of axeResults) {
        for (const violation of item.violations || []) {
            if (['critical', 'serious'].includes(violation.impact)) {
                fatalErrors.push(item.role + ' ' + item.route + ' @ ' + (item.viewport || 'desktop-1440') + ': serious accessibility violation ' + violation.id + ' (' + violation.nodes.length + ' node(s))');
            }
        }
    }

    const report = {
        generatedAt: new Date().toISOString(),
        baseUrl: BASE_URL,
        totalCaptures: results.length,
        routes: results,
        accessibility: axeResults,
        browserErrors,
        fatalErrors,
        overflowCount: results.filter((r) => r.horizontalOverflow).length,
        httpErrorCount: results.filter((r) => r.status !== null && r.status >= 400).length,
        accessibilityViolationGroups: axeResults.reduce((sum, item) => sum + (item.violations || []).length, 0),
        accessibilityViolationNodes: axeResults.reduce((sum, item) => sum + (item.violations || []).reduce((subtotal, violation) => subtotal + violation.nodes, 0), 0),
        accessibilityViolationCount: axeResults.reduce((sum, item) => sum + (item.violations || []).reduce((subtotal, violation) => subtotal + violation.nodes, 0), 0),
        interactionChecks,
    };
    await fs.writeFile(path.join(OUTPUT_DIR, 'report.json'), JSON.stringify(report, null, 2) + '\n');

    const failedPages = results.filter((r) => r.status === null || r.status >= 400 || r.redirected || r.horizontalOverflow);
    const markdown = [
        '# FixPoint browser visual review', '',
        '- Generated: ' + report.generatedAt,
        '- Captures: ' + report.totalCaptures,
        '- Routes with HTTP/navigation/overflow problems: ' + failedPages.length,
        '- Horizontal overflow captures: ' + report.overflowCount,
        '- WCAG axe violation groups (desktop route scans): ' + report.accessibilityViolationGroups,
        '- WCAG affected nodes (desktop route scans): ' + report.accessibilityViolationNodes,
        '- Browser runtime errors: ' + browserErrors.length, '',
        '## Page capture results', '',
        '| Role | Route | Viewport | HTTP | H1 | Overflow | Full-page screenshot | Viewport screenshot |',
        '|---|---|---:|---:|---|---|---|---|',
        ...results.map((r) => '| ' + r.role + ' | ' + r.route + ' | ' + r.viewport + ' | ' + (r.status ?? 'n/a') + ' | ' + (r.h1 || []).join('; ').replace(/\|/g, '\\|') + ' | ' + (r.horizontalOverflow ? 'YES' : 'no') + ' | ' + r.screenshot + ' | ' + (r.viewportScreenshot || '—') + ' |'),
        '', '## Fatal findings', '',
        ...(fatalErrors.length ? fatalErrors.map((item) => '- ' + item) : ['- None']),
        '', '## Accessibility findings', '',
        ...axeResults.flatMap((item) => (item.violations || []).map((v) =>
            '- **' + item.role + ' ' + item.route + ' @ ' + (item.viewport || 'desktop-1440') + '**: ' + v.id + ' (' + v.impact + '), ' + v.nodes + ' node(s). ' + v.help
        )),
        '', '## Interaction checks', '',
        ...interactionChecks.map((item) => '- **' + item.route + ' / ' + item.feature + '**: ' + [
            'closedByDefault', 'hiddenByDefault', 'opensByPointer',
            'stepsVisibleWhenOpen', 'closesByKeyboard',
        ].map((key) => key + '=' + item[key]).join(', ')),
        '', '## Notes', '',
        'Screenshots are captured from the running PHP application backed by a fresh MySQL 8 database seeded from database/seeds/local_data.php. They are not concept mockups. Mobile captures include both full-page and true viewport screenshots so fixed navigation can be checked at its actual screen position.\nA serious/critical axe violation or duplicate customer navigation on mobile fails the workflow.',
        'Axe scans run on all desktop-1440 captures and on public /login at mobile-390 and small-mobile-360. These are automated indicators, not a substitute for manual keyboard and screen-reader review.',
    ].join('\n') + '\n';
    await fs.writeFile(path.join(OUTPUT_DIR, 'report.md'), markdown);

    console.log('Visual captures:', report.totalCaptures);
    console.log('HTTP errors:', report.httpErrorCount);
    console.log('Horizontal overflows:', report.overflowCount);
    console.log('Accessibility violations:', report.accessibilityViolationCount);
    console.log('Browser errors:', browserErrors.length);
    console.log('Interaction checks:', interactionChecks.length);
    console.log('Fatal errors:', fatalErrors.length);
    if (fatalErrors.length > 0) {
        console.error(fatalErrors.join('\n'));
        process.exitCode = 1;
    }
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
