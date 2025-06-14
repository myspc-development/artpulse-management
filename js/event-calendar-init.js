document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('event-calendar');
  const filterEl = document.getElementById('calendar-organizer-filter');
  if (!calendarEl || typeof FullCalendar === 'undefined') return;

  const allEvents = artpulseCalendarData.events || [];

  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek'
    },
    events: allEvents,
    eventClick: function (info) {
      info.jsEvent.preventDefault();
      if (info.event.url) {
        window.open(info.event.url, '_blank');
      }
    }
  });

  calendar.render();

  if (filterEl) {
    filterEl.addEventListener('change', function () {
      const selected = this.value;
      const filtered = selected
        ? allEvents.filter(e => e.organizer == selected)
        : allEvents;

      calendar.removeAllEvents();
      calendar.addEventSource(filtered);
    });
  }
});
