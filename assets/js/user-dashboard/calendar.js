import { openEventModal, openBulkRSVPModal } from './rsvp.js';
import { initEventMap } from './map.js';

export function renderCalendar(events) {
  const calendarEl = document.getElementById('ead-event-calendar');
  calendarEl.innerHTML = '';

  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,listWeek'
    },
    selectable: true,
    selectOverlap: false,
    selectMirror: true,
    events: events.map(e => ({
      ...e,
      color: e.rsvped ? '#0073aa' : '#cccccc',
      extendedProps: e
    })),
    eventClick: function(info) {
      info.jsEvent.preventDefault();
      openEventModal(info.event.extendedProps);
    },
    select: function(info) {
      const selected = calendar.getEvents().filter(e =>
        e.start >= info.start && e.start < info.end
      );
      if (selected.length) {
        openBulkRSVPModal(selected);
      }
    }
  });
  calendar.render();
  initEventMap(events);
  return calendar;
}

export function loadEventCalendar(restUrl, nonce) {
  return jQuery.ajax({
    url: restUrl + '/calendar',
    method: 'GET',
    headers: { 'X-WP-Nonce': nonce }
  });
}
