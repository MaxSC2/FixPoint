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
    { role: 'customer-kovalev', email: 'customer.kovalev@fixpoint.test', routes: ['/', '/account', '/requests', '/requests/create', '/requests/1', '/requests/3'] },
    { role: 'technician-ivanov', email: 'master.ivanov@fixpoint.test', routes: ['/', '/technician/requests', '/technician/requests/1'] },
    { role: 'technician-smirnova', email: 'master.smirnova@fixpoint.test', routes: ['/', '/technician/requests/3'] },
    { role: 'admin', email: 'admin@fixpoint.test', routes: ['/', '/admin', '/admin/requests', '/admin/requests/1/assign', '/admin/users', '/admin/users/1/edit', '/admin/users/2/edit', '/admin/services', '/admin/services/1/edit', '/admin/categories', '/admin/categories/1/edit', '/admin/content/about', '/admin/statistics'] },
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
        const headerLayoutIssue = (() => {
            if (window.innerWidth > 900) return null;
            const brand = document.querySelector('.site-header .brand');
            const actions = document.querySelector('.site-header__actions');
            const toggle = document.querySelector('.site-header .nav-toggle');
            if (!brand || !actions || !toggle) return 'mobile header controls are missing';
            const brandRect = brand.getBoundingClientRect();
            const actionsRect = actions.getBoundingClientRect();
            const toggleRect = toggle.getBoundingClientRect();
            const gap = toggleRect.left - actionsRect.right;
            if (actionsRect.left < brandRect.right - 1) return 'account action overlaps the brand';
            if (gap < -1 || gap > 24) return 'account action and menu toggle are not a compact group (gap ' + Math.round(gap) + 'px)';
            if (getComputedStyle(toggle).marginLeft === 'auto') return 'menu toggle still consumes an independent auto margin';
            return null;
        })();
        const serviceCardIssues = (() => {
            if (window.location.pathname !== '/services') return [];
            const cards = Array.from(document.querySelectorAll('.service-card'));
            const issues = [];
            if (cards.length === 0) return ['service catalogue rendered no cards'];
            if (cards.some((card) => card.tagName !== 'A')) issues.push('service cards are not full-card links');
            if (cards.some((card) => card.querySelectorAll('a[href]').length !== 0)) issues.push('service card contains a nested link');
            if (cards.some((card) => !card.querySelector('.service-card__action'))) issues.push('service card has no visible action label');
            const categories = new Set(cards.map((card) => card.getAttribute('data-category-slug')).filter(Boolean));
            const icons = new Set(cards.map((card) => card.querySelector('.service-card__icon svg')?.outerHTML || ''));
            if (categories.size > 1 && icons.size < Math.min(4, categories.size)) issues.push('category icon mapping is not distinct enough (' + icons.size + ' icons for ' + categories.size + ' categories)');
            return issues;
        })();
        const aboutLayoutIssues = (() => {
            if (window.location.pathname !== '/about') return [];
            const crumbs = document.querySelector('.breadcrumbs');
            const visual = document.querySelector('.about-visual');
            const card = document.querySelector('.about-process-card');
            if (!crumbs || !visual || !card) return ['about page process panel or breadcrumbs are missing'];
            const a = crumbs.getBoundingClientRect();
            const b = visual.getBoundingClientRect();
            const overlapX = Math.min(a.right, b.right) - Math.max(a.left, b.left);
            const overlapY = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
            const issues = [];
            if (overlapX > 1 && overlapY > 1) issues.push('about visual intersects the breadcrumb area');
            if (window.innerWidth <= 900 && b.top - a.bottom < 8) issues.push('about process panel is too close to breadcrumbs');
            if (card.querySelectorAll('.about-process-step').length !== 3) issues.push('about process panel does not show all three stages');
            return issues;
        })();
        const homeHeroPrimaryHref = document.querySelector('.atelier-hero__actions .button--primary')?.getAttribute('href') || null;
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
            headerLayoutIssue,
            serviceCardIssues,
            aboutLayoutIssues,
            homeHeroPrimaryHref,
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
    if (metrics.headerLayoutIssue) fatalErrors.push(role + ' ' + route + ' @ ' + viewport.name + ': ' + metrics.headerLayoutIssue);
    for (const issue of metrics.serviceCardIssues || []) fatalErrors.push(role + ' ' + route + ' @ ' + viewport.name + ': ' + issue);
    for (const issue of metrics.aboutLayoutIssues || []) fatalErrors.push(role + ' ' + route + ' @ ' + viewport.name + ': ' + issue);
    if (route === '/') {
        const expectedHeroHref = {
            'customer-kovalev': '/requests/create',
            'technician-ivanov': '/technician/requests',
            'technician-smirnova': '/technician/requests',
            admin: '/admin',
        }[role];
        if (expectedHeroHref && metrics.homeHeroPrimaryHref !== expectedHeroHref) {
            fatalErrors.push(role + ' / @ ' + viewport.name + ': home CTA points to ' + metrics.homeHeroPrimaryHref + ', expected ' + expectedHeroHref);
        }
    }

    if (viewport.name === 'desktop-1440') {
        try {
            const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
            axeResults.push({
                role, route,
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

async function verifyPublicInteractions(page, fatalErrors) {
    const fail = (stage, message) => fatalErrors.push('public interactions (' + stage + '): ' + message);
    const waitForPath = async (stage, target) => {
        try {
            await page.waitForURL((url) => url.pathname === target, { waitUntil: 'domcontentloaded', timeout: 10000 });
            if (new URL(page.url()).pathname !== target) fail(stage, 'expected ' + target + ', got ' + new URL(page.url()).pathname);
        } catch (error) {
            fail(stage, 'navigation to ' + target + ' did not complete: ' + error.message);
        }
    };

    await page.setViewportSize({ width: 390, height: 844 });
    try {
        await page.goto(BASE_URL + '/', { waitUntil: 'domcontentloaded' });
        await page.locator('body').waitFor({ state: 'visible', timeout: 5000 });
    } catch (error) {
        fail('mobile menu setup', error.message);
        return;
    }

    const toggle = page.locator('[data-nav-toggle]');
    const navigation = page.locator('[data-site-nav]');
    if (!await toggle.isVisible()) {
        fail('mobile menu open', 'mobile navigation toggle is not visible');
    } else {
        try {
            await toggle.click();
            if (await toggle.getAttribute('aria-expanded') !== 'true' || !await navigation.isVisible()) {
                fail('mobile menu open', 'menu did not open or aria-expanded is not true');
            }
            await page.keyboard.press('Escape');
            if (await toggle.getAttribute('aria-expanded') !== 'false' || await navigation.isVisible()) {
                fail('mobile menu Escape', 'Escape did not close navigation');
            }
            await toggle.click();
            const servicesLink = navigation.locator('a[href="/services"]').first();
            if (!await servicesLink.isVisible()) {
                fail('mobile menu services link', 'services link is not visible after opening menu');
            } else {
                await servicesLink.click();
                await waitForPath('mobile menu services link', '/services');
            }
        } catch (error) {
            fail('mobile menu interaction', error.message);
        }
    }

    try {
        await page.goto(BASE_URL + '/services', { waitUntil: 'domcontentloaded' });
        const pointerCard = page.locator('a.service-card').first();
        const pointerHref = await pointerCard.getAttribute('href');
        await pointerCard.scrollIntoViewIfNeeded();
        const pointerBox = await pointerCard.boundingBox();
        if (!pointerHref || !pointerBox) {
            fail('service card pointer', 'first full-card link is missing or has no visible area');
        } else {
            await page.mouse.click(pointerBox.x + pointerBox.width / 2, pointerBox.y + pointerBox.height / 2);
            await waitForPath('service card pointer', new URL(pointerHref, BASE_URL).pathname);
        }
    } catch (error) {
        fail('service card pointer', error.message);
    }

    try {
        await page.goto(BASE_URL + '/services', { waitUntil: 'domcontentloaded' });
        const keyboardCard = page.locator('a.service-card').nth(1);
        const keyboardHref = await keyboardCard.getAttribute('href');
        if (!keyboardHref) {
            fail('service card keyboard', 'second service card has no href');
        } else {
            await keyboardCard.scrollIntoViewIfNeeded();
            await keyboardCard.focus();
            await page.keyboard.press('Enter');
            await waitForPath('service card keyboard', new URL(keyboardHref, BASE_URL).pathname);
        }
    } catch (error) {
        fail('service card keyboard', error.message);
    }
}

async function verifyRoleHomeAction(page, role, expectedPath, fatalErrors) {
    try {
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto(BASE_URL + '/', { waitUntil: 'domcontentloaded' });
        const action = page.locator('.atelier-hero__actions .button--primary');
        const href = await action.getAttribute('href');
        if (href !== expectedPath) {
            fatalErrors.push(role + ' home CTA target mismatch: ' + href + ', expected ' + expectedPath);
            return;
        }
        await action.click();
        await page.waitForURL((url) => url.pathname === expectedPath, { timeout: 10000 });
        if (new URL(page.url()).pathname !== expectedPath) {
            fatalErrors.push(role + ' home CTA did not navigate to ' + expectedPath);
        }
    } catch (error) {
        fatalErrors.push(role + ' home CTA interaction failed: ' + error.message);
    }
}

async function main() {
    await fs.mkdir(OUTPUT_DIR, { recursive: true });
    const results = [];
    const axeResults = [];
    const fatalErrors = [];
    const browserErrors = [];
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
        await verifyPublicInteractions(publicPage, fatalErrors);

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
                const expectedHomeTarget = {
                    'customer-kovalev': '/requests/create',
                    'technician-ivanov': '/technician/requests',
                    'technician-smirnova': '/technician/requests',
                    admin: '/admin',
                }[session.role];
                if (expectedHomeTarget) {
                    await verifyRoleHomeAction(page, session.role, expectedHomeTarget, fatalErrors);
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
                fatalErrors.push(item.role + ' ' + item.route + ': serious accessibility violation ' + violation.id + ' (' + violation.nodes.length + ' node(s))');
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
            '- **' + item.role + ' ' + item.route + '**: ' + v.id + ' (' + v.impact + '), ' + v.nodes + ' node(s). ' + v.help
        )),
        '', '## Notes', '',
        'Screenshots are captured from the running PHP application backed by a fresh MySQL 8 database seeded from database/seeds/local_data.php. They are not concept mockups. Mobile captures include both full-page and true viewport screenshots so fixed navigation can be checked at its actual screen position.\nA serious/critical axe violation or duplicate customer navigation on mobile fails the workflow.',
        'Axe results are automated indicators, not a substitute for manual keyboard and screen-reader review.',
    ].join('\n') + '\n';
    await fs.writeFile(path.join(OUTPUT_DIR, 'report.md'), markdown);

    console.log('Visual captures:', report.totalCaptures);
    console.log('HTTP errors:', report.httpErrorCount);
    console.log('Horizontal overflows:', report.overflowCount);
    console.log('Accessibility violations:', report.accessibilityViolationCount);
    console.log('Browser errors:', browserErrors.length);
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
