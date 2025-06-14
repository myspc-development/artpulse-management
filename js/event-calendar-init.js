document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('event-calendar');
  if (!calendarEl || typeof FullCalendar === 'undefined') return;

  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek'
    },
    events: artpulseCalendarData.events || [],
    eventClick: function (info) {
      info.jsEvent.preventDefault();
      if (info.event.url) {
        window.open(info.event.url, '_blank');
      }
    }
  });

  calendar.render();
});
