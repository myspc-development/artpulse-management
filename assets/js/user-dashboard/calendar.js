import { openEventModal } from './rsvp.js';

export function initCalendar(events) {
  const calendarEl = document.getElementById('ead-event-calendar');
  if (!calendarEl) return;

  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    events: events,
    eventClick: function (info) {
      openEventModal(info.event.extendedProps);
    },
  });

  calendar.render();
}
