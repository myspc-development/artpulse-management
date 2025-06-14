document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('event-calendar');
  const filterEl = document.getElementById('event-organizer-filter');
  if (!el || typeof FullCalendar === 'undefined') return;

  const allEvents = Array.isArray(eventCalendarData?.events)
    ? eventCalendarData.events
    : [];

  const calendar = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth',
    events: allEvents,
  });

  calendar.render();

  if (filterEl) {
    filterEl.addEventListener('change', () => {
      const selected = filterEl.value;
      const events = selected
        ? allEvents.filter((e) => e.organizer === selected)
        : allEvents;
      calendar.removeAllEvents();
      calendar.addEventSource(events);
    });
  }
});
