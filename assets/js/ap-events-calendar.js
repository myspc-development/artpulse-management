(function () {
    'use strict';

    const config = window.APEventsConfig || {};

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

    const formatRange = (start, end) => {
        if (!start) {
            return '';
        }

        const startDate = new Date(start);
        const endDate = end ? new Date(end) : null;

        const options = { dateStyle: 'medium', timeStyle: 'short' };
        try {
            if (endDate) {
                return `${startDate.toLocaleString(undefined, options)} – ${endDate.toLocaleString(undefined, options)}`;
            }
            return startDate.toLocaleString(undefined, options);
        } catch (error) {
            return startDate.toISOString();
        }
    };

    const buildTooltipContent = (event) => {
        const parts = [];
        parts.push(`<strong>${event.title || ''}</strong>`);
        parts.push(`<span class="ap-events-tooltip__time">${formatRange(event.start, event.end)}</span>`);
        if (event.location) {
            parts.push(`<span class="ap-events-tooltip__location">${event.location}</span>`);
        }
        if (event.cost) {
            parts.push(`<span class="ap-events-tooltip__cost">${event.cost}</span>`);
        }
        if (config.icsUrl) {
            const url = new URL(config.icsUrl, window.location.href);
            url.searchParams.set('single', '1');
            url.searchParams.set('event_id', event.id);
            parts.push(`<a class="ap-events-tooltip__ics" href="${url.toString()}">${window.wp?.i18n?.__("Add to calendar", "artpulse-management") || 'Add to calendar'}</a>`);
        }
        return `<div class="ap-events-tooltip">${parts.join('')}</div>`;
    };

    const initCalendar = (root) => {
        const data = parseData(root);
        const calendarEl = root.querySelector('[data-ap-events-target="calendar"]');
        if (!calendarEl) {
            return;
        }

        const events = Array.isArray(data.events) ? data.events : [];

        if (window.FullCalendar && window.FullCalendar.Calendar) {
            const calendar = new window.FullCalendar.Calendar(calendarEl, {
                initialView: data.view || 'dayGridMonth',
                initialDate: data.initialDate || undefined,
                events,
                height: 'auto',
                headerToolbar: {
                    start: 'prev,next today',
                    center: 'title',
                    end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
                },
                eventClick: (info) => {
                    info.jsEvent.preventDefault();
                    const url = info.event.extendedProps.url || info.event.url;
                    if (url) {
                        window.open(url, '_self');
                    }
                },
                eventDidMount: (info) => {
                    const el = info.el;
                    el.setAttribute('tabindex', '0');
                    el.setAttribute('role', 'button');
                    el.setAttribute('aria-label', `${info.event.title} ${formatRange(info.event.start, info.event.end)}`);
                    el.dataset.tooltip = buildTooltipContent({
                        id: info.event.extendedProps.id || info.event.id,
                        title: info.event.title,
                        start: info.event.start ? info.event.start.toISOString() : '',
                        end: info.event.end ? info.event.end.toISOString() : '',
                        location: info.event.extendedProps.location,
                        cost: info.event.extendedProps.cost
                    });
                }
            });

            calendar.render();
        } else {
            // Fallback rendering for when FullCalendar is unavailable.
            const list = document.createElement('ul');
            list.className = 'ap-events__fallback-list';
            events.forEach((event) => {
                const item = document.createElement('li');
                const link = document.createElement('a');
                link.href = event.url || '#';
                link.textContent = `${event.title} – ${formatRange(event.start, event.end)}`;
                item.appendChild(link);
                list.appendChild(item);
            });
            calendarEl.appendChild(list);
        }
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

    const initTooltips = () => {
        document.body.addEventListener('focusin', (event) => {
            const trigger = event.target.closest('[data-tooltip]');
            if (!trigger) {
                return;
            }
            const existing = document.querySelector('.ap-events-tooltip.is-visible');
            if (existing) {
                existing.remove();
            }
            const tooltip = document.createElement('div');
            tooltip.className = 'ap-events-tooltip is-visible';
            tooltip.innerHTML = trigger.dataset.tooltip;
            document.body.appendChild(tooltip);
            const rect = trigger.getBoundingClientRect();
            tooltip.style.left = `${rect.left + window.scrollX}px`;
            tooltip.style.top = `${rect.bottom + window.scrollY + 8}px`;
        });

        document.body.addEventListener('focusout', (event) => {
            if (!event.relatedTarget || !event.relatedTarget.closest('.ap-events-tooltip')) {
                const tooltip = document.querySelector('.ap-events-tooltip.is-visible');
                if (tooltip) {
                    tooltip.remove();
                }
            }
        });
    };

    const init = () => {
        document.querySelectorAll('.ap-events--calendar').forEach(initCalendar);
        document.querySelectorAll('.ap-events--tabs').forEach(initTabs);
        initTooltips();
    };

    if ('complete' === document.readyState || 'interactive' === document.readyState) {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
