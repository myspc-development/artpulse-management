(function () {
    'use strict';

    const parseData = (el) => {
        if (!el || !el.dataset.apEvents) {
            return {};
        }

        try {
            return JSON.parse(el.dataset.apEvents);
        } catch (error) {
            return {};
        }
    };

    const initFavoriteButtons = (root) => {
        root.querySelectorAll('[data-ap-event-favorite]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                button.classList.toggle('is-active');
            });
        });
    };

    const initGrid = (root) => {
        const data = parseData(root);
        const list = root.querySelector('[data-ap-events-target="grid"]');
        if (!list) {
            return;
        }

        list.querySelectorAll('li').forEach((item, index) => {
            requestAnimationFrame(() => {
                item.style.transitionDelay = `${index * 60}ms`;
                item.classList.add('is-visible');
            });
        });

        initFavoriteButtons(root);
    };

    const initTabs = (context) => {
        const nav = context.querySelectorAll('[data-ap-events-tab]');
        const panes = context.querySelectorAll('[data-ap-events-pane]');
        nav.forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.getAttribute('data-ap-events-tab');
                nav.forEach((btn) => btn.classList.toggle('is-active', btn === button));
                panes.forEach((pane) => {
                    const matches = pane.getAttribute('data-ap-events-pane') === target;
                    pane.classList.toggle('is-hidden', !matches);
                });
            });
        });
    };

    const init = () => {
        document.querySelectorAll('.ap-events--grid').forEach(initGrid);
        document.querySelectorAll('.ap-events--tabs').forEach(initTabs);
    };

    if ('complete' === document.readyState || 'interactive' === document.readyState) {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
