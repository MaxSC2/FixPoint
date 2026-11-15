(() => {
    'use strict';

    const root = document.documentElement;
    root.classList.remove('no-js');
    root.classList.add('js');

    const toggle = document.querySelector('[data-nav-toggle]');
    const navigation = document.querySelector('[data-site-nav]');
    const toggleLabel = toggle?.querySelector('[data-nav-label]');

    if (toggle && navigation) {
        const closeNavigation = () => {
            toggle.setAttribute('aria-expanded', 'false');
            if (toggleLabel) toggleLabel.textContent = 'Открыть меню';
            navigation.classList.remove('is-open');
        };

        toggle.addEventListener('click', () => {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!isExpanded));
            if (toggleLabel) toggleLabel.textContent = isExpanded ? 'Открыть меню' : 'Закрыть меню';
            navigation.classList.toggle('is-open', !isExpanded);
        });

        navigation.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('a')) {
                closeNavigation();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                closeNavigation();
                toggle.focus();
            }
        });

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Node)) return;
            if (!toggle.contains(event.target) && !navigation.contains(event.target)) {
                closeNavigation();
            }
        });

        window.addEventListener('resize', () => {
            if (window.matchMedia('(min-width: 901px)').matches) closeNavigation();
        });
    }

    // Password visibility is an explicit, reversible control.
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const targetId = button.getAttribute('aria-controls');
        const input = targetId ? document.getElementById(targetId) : null;
        const label = button.querySelector('[data-password-toggle-label]');
        if (!(input instanceof HTMLInputElement)) return;

        button.addEventListener('click', () => {
            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(shouldShow));
            button.setAttribute('aria-label', shouldShow ? 'Скрыть пароль' : 'Показать пароль');
            if (label) label.textContent = shouldShow ? 'Скрыть' : 'Показать';
        });
    });

    // Quick device-type choices keep free-text entry available as a fallback.
    const deviceTypeInput = document.querySelector('#request-device-type');
    const deviceTypeOptions = Array.from(document.querySelectorAll('[data-device-type]'));
    if (deviceTypeInput && deviceTypeOptions.length > 0) {
        const syncDeviceType = () => {
            deviceTypeOptions.forEach((option) => {
                const selected = option.dataset.deviceType === deviceTypeInput.value.trim();
                option.classList.toggle('is-selected', selected);
                option.setAttribute('aria-pressed', String(selected));
            });
        };
        syncDeviceType();
        deviceTypeInput.addEventListener('input', syncDeviceType);
        deviceTypeOptions.forEach((option) => {
            option.addEventListener('click', () => {
                deviceTypeInput.value = option.dataset.deviceType || '';
                deviceTypeInput.dispatchEvent(new Event('input', { bubbles: true }));
                syncDeviceType();
                deviceTypeInput.focus();
            });
        });
    }

    // Progressive manufacturer picker: native text entry remains the fallback.
    const brandInput = document.querySelector('#request-device-brand');
    const brandSearch = document.querySelector('#request-brand-search');
    const brandOptions = document.querySelector('#request-brand-options');

    if (brandInput && brandSearch && brandOptions) {
        const options = Array.from(brandOptions.querySelectorAll('[data-brand]'));
        const normalize = (value) => value.toLocaleLowerCase('ru').trim();
        const setSelected = (value) => {
            options.forEach((option) => {
                const selected = option.dataset.brand === value;
                option.classList.toggle('is-selected', selected);
                option.setAttribute('aria-pressed', String(selected));
            });
        };

        setSelected(brandInput.value.trim());
        brandInput.addEventListener('input', () => setSelected(brandInput.value.trim()));

        brandSearch.addEventListener('input', () => {
            const query = normalize(brandSearch.value);
            options.forEach((option) => {
                option.hidden = !normalize(option.dataset.brand || '').includes(query);
            });
        });

        options.forEach((option) => {
            option.setAttribute('aria-pressed', String(option.dataset.brand === brandInput.value.trim()));
            option.addEventListener('click', () => {
                brandInput.value = option.dataset.brand || '';
                brandInput.dispatchEvent(new Event('input', { bubbles: true }));
                setSelected(brandInput.value);
                brandInput.focus();
            });
        });
    }

    // Reveal content as it enters the viewport. The page remains fully readable
    // when JavaScript, IntersectionObserver, or motion preferences require fallback.
    const revealTargets = Array.from(document.querySelectorAll('[data-reveal]'));
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!('IntersectionObserver' in window) || reduceMotion) {
        revealTargets.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -36px 0px'
    });

    revealTargets.forEach((element) => revealObserver.observe(element));
})();
