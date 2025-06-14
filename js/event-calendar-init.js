document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('event-calendar');
  const organizerFilter = document.getElementById('calendar-organizer-filter');
  const tagFilter = document.getElementById('calendar-tag-filter');
  const startFilter = document.getElementById('calendar-start-filter');
  const endFilter = document.getElementById('calendar-end-filter');
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

  function applyFilters() {
    const selectedOrg = organizerFilter ? organizerFilter.value : '';
    const selectedTag = tagFilter ? tagFilter.value : '';
    const startDate = startFilter ? startFilter.value : '';
    const endDate = endFilter ? endFilter.value : '';

    const filtered = allEvents.filter(event => {
      const matchOrg = !selectedOrg || event.organizer == selectedOrg;
      const matchTag = !selectedTag || (event.tags && event.tags.includes(selectedTag));
      const matchStart = !startDate || event.start >= startDate;
      const matchEnd = !endDate || event.start <= endDate;
      return matchOrg && matchTag && matchStart && matchEnd;
    });

    calendar.removeAllEvents();
    calendar.addEventSource(filtered);
  }

  if (organizerFilter) organizerFilter.addEventListener('change', applyFilters);
  if (tagFilter) tagFilter.addEventListener('change', applyFilters);
  if (startFilter) startFilter.addEventListener('change', applyFilters);
  if (endFilter) endFilter.addEventListener('change', applyFilters);
});
