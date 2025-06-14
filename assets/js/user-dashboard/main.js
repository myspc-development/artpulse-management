import { loadEventCalendar, renderCalendar } from './calendar.js';
import { initEventMap } from './map.js';
import { showLoader, hideLoader, showToast } from './ui.js';
import { openEventModal } from './rsvp.js';

jQuery(document).ready(function ($) {
  const restUrl = eadUserDashboard.restUrl;
  const nonce = eadUserDashboard.nonce;

  loadEventCalendar(restUrl, nonce).done(events => {
    renderCalendar(events);
    initEventMap(events);
  });

  $(document).on('click', '.ead-event', function () {
    const eventData = $(this).data('event');
    openEventModal(eventData);
  });
});
