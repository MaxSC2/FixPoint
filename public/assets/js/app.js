(() => {
    'use strict';

    const root = document.documentElement;
    root.classList.remove('no-js');
    root.classList.add('js');

    const toggle = document.querySelector('[data-nav-toggle]');
    const navigation = document.querySelector('[data-site-nav]');

    if (toggle && navigation) {
        const closeNavigation = () => {
            toggle.setAttribute('aria-expanded', 'false');
            navigation.classList.remove('is-open');
        };

        toggle.addEventListener('click', () => {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!isExpanded));
            navigation.classList.toggle('is-open', !isExpanded);
        });

        navigation.addEventListener('click', (event) => {
            if (event.target instanceof HTMLAnchorElement) {
                closeNavigation();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeNavigation();
                toggle.focus();
            }
        });

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Node)) {
                return;
            }
            if (!toggle.contains(event.target) && !navigation.contains(event.target)) {
                closeNavigation();
            }
        });

        window.addEventListener('resize', () => {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                closeNavigation();
            }
        });
    }
})();
