document.addEventListener('DOMContentLoaded', () => {
  const events = typeof eventCalendarData !== 'undefined' ? eventCalendarData : [];
  const el = document.getElementById('event-calendar');
  if (!el || typeof FullCalendar === 'undefined') return;

  const calendar = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth',
    events: events,
  });

  calendar.render();
});
