import { showToast } from './ui.js';

export function openEventModal(event) {
  jQuery('#ead-modal-title').text(event.title);
  jQuery('#ead-modal-date').text(`Date: ${event.start}`);
  jQuery('#ead-modal-link').attr('href', event.url);
  jQuery('#ead-rsvp-btn')
    .text(event.rsvped ? 'Cancel RSVP' : 'RSVP')
    .data('id', event.id)
    .data('rsvped', event.rsvped);

  jQuery('#ead-event-modal').fadeIn(200);
}

export function openBulkRSVPModal(events) {
  const listHtml = events
    .map(e => `<li data-id="${e.id}">${e.title} (${e.startStr || e.start})</li>`)
    .join('');
  jQuery('#ead-bulk-event-list').html(listHtml);
  jQuery('#ead-bulk-rsvp-modal').fadeIn(200);
}
