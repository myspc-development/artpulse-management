import { initCalendar } from './calendar.js';
import { initMap } from './map.js';
import { openEventModal } from './rsvp.js';

document.addEventListener('DOMContentLoaded', () => {
  const restUrl = eadUserDashboard.restUrl;

  fetch(restUrl + '/calendar')
    .then((res) => res.json())
    .then((events) => {
      initCalendar(events);
      initMap(events);
    });

  jQuery(document).on('click', '.ead-event', function () {
    const eventData = jQuery(this).data('event');
    openEventModal(eventData);
  });
});
