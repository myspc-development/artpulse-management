export function initMap(events, userCoords = null) {
  const map = L.map('ead-event-map').setView([37.7749, -122.4194], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

  events.forEach((e) => {
    if (e.latitude && e.longitude) {
      L.marker([e.latitude, e.longitude]).addTo(map).bindPopup(`<strong>${e.title}</strong>`);
    }
  });

  if (userCoords) {
    L.marker(userCoords, { icon: L.icon({ iconUrl: '/user.png' }) }).addTo(map);
  }
}
