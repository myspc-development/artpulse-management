(function () {
    'use strict';

    function initDirectory(container) {
        const filterControls = Array.from(container.querySelectorAll('.ap-directory-filter__control'));
        const results = container.querySelector('.ap-directory-results');
        if (!results || filterControls.length === 0) {
            return;
        }

        const sections = Array.from(results.querySelectorAll('.ap-directory-section'));
        const emptyState = results.querySelector('.ap-directory-empty');
        let activeLetter = container.getAttribute('data-active-letter') || 'All';

        function updateHistory(letter) {
            if (!window.history || !window.history.replaceState) {
                return;
            }

            const url = new URL(window.location.href);
            if (letter === 'All') {
                url.searchParams.delete('letter');
            } else {
                url.searchParams.set('letter', letter);
            }

            window.history.replaceState({}, document.title, url.toString());
        }

        function hasCards(section) {
            return section ? section.querySelector('.ap-artist-card') !== null : false;
        }

        function toggleSections(letter) {
            results.setAttribute('aria-busy', 'true');

            let hasVisibleCards = false;
            let targetSection = null;

            sections.forEach((section) => {
                const sectionLetter = section.getAttribute('data-letter');
                const shouldShow = letter === 'All' || sectionLetter === letter;

                if (shouldShow) {
                    section.removeAttribute('hidden');
                    if (!targetSection && sectionLetter === letter) {
                        targetSection = section;
                    }
                    hasVisibleCards = hasVisibleCards || hasCards(section);
                } else {
                    section.setAttribute('hidden', '');
                }
            });

            if (letter !== 'All' && !targetSection) {
                hasVisibleCards = false;
            }

            if (emptyState) {
                if (hasVisibleCards) {
                    emptyState.setAttribute('hidden', '');
                } else {
                    emptyState.removeAttribute('hidden');
                }
            }

            requestAnimationFrame(() => {
                results.setAttribute('aria-busy', 'false');
            });
        }

        function setLetter(letter, { updateUrl = true, force = false } = {}) {
            if (!force && letter === activeLetter) {
                return;
            }

            activeLetter = letter;
            container.setAttribute('data-active-letter', letter);

            filterControls.forEach((control) => {
                const controlLetter = control.getAttribute('data-letter');
                const isActive = controlLetter === letter;
                control.classList.toggle('is-active', isActive);
                control.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            toggleSections(letter);

            if (updateUrl) {
                updateHistory(letter);
            }
        }

        filterControls.forEach((control) => {
            control.addEventListener('click', (event) => {
                event.preventDefault();
                const letter = control.getAttribute('data-letter') || 'All';
                setLetter(letter);
            });
        });

        setLetter(activeLetter, { updateUrl: false, force: true });
    }

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        const directories = document.querySelectorAll('.ap-artists-directory');
        directories.forEach((directory) => {
            initDirectory(directory);
        });
    });
})();
