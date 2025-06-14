let map;
let markerLayer;

export function initEventMap(events) {
  if (!map) {
    map = L.map('ead-event-map').setView([20, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    markerLayer = L.layerGroup().addTo(map);
  }

  markerLayer.clearLayers();

  events.forEach(event => {
    if (event.latitude && event.longitude) {
      const marker = L.marker([event.latitude, event.longitude]).addTo(markerLayer);
      marker.bindPopup(`<strong>${event.title}</strong><br>${event.start}`);
    }
  });
}
