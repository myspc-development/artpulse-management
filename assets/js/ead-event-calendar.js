document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('event-calendar');
  const filterEl = document.getElementById('event-organizer-filter');
  if (!el || typeof FullCalendar === 'undefined') return;

  const restUrl = eventCalendarData?.restUrl;
  const nonce = eventCalendarData?.nonce || '';
  let allEvents = Array.isArray(eventCalendarData?.events)
    ? eventCalendarData.events
    : [];

  const calendar = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth',
    events: [],
  });

  calendar.render();

  function render(events) {
    calendar.removeAllEvents();
    calendar.addEventSource(events);
  }

  function fetchEvents() {
    if (!restUrl) {
      render(allEvents);
      return;
    }

    fetch(restUrl, { headers: { 'X-WP-Nonce': nonce } })
      .then((res) => res.json())
      .then((data) => {
        allEvents = Array.isArray(data) ? data : [];
        render(allEvents);
      })
      .catch(() => render(allEvents));
  }

  fetchEvents();

  if (filterEl) {
    filterEl.addEventListener('change', () => {
      const selected = filterEl.value;
      const events = selected
        ? allEvents.filter((e) => e.organizer === selected)
        : allEvents;
      render(events);
    });
  }
});
