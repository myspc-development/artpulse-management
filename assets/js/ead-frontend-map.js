// assets/js/ead-frontend-map.js
// Uses Leaflet.js to render a map of events/orgs with secure AJAX.

document.addEventListener('DOMContentLoaded', function () {
    const mapContainer = document.getElementById('ead-map');
    if (!mapContainer) return;

    const useGoogle = typeof EAD_SINGLE_MAP !== 'undefined' && EAD_SINGLE_MAP.gmapsApiKey && typeof google === 'object';
    let map;

    if (useGoogle) {
        map = new google.maps.Map(mapContainer, { center: { lat: 0, lng: 0 }, zoom: 2 });
    } else {
        map = L.map('ead-map').setView([0, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
    }

    if (typeof EAD_SINGLE_MAP !== 'undefined' && EAD_SINGLE_MAP.lat && EAD_SINGLE_MAP.lng) {
        const lat = parseFloat(EAD_SINGLE_MAP.lat);
        const lng = parseFloat(EAD_SINGLE_MAP.lng);
        if (!isNaN(lat) && !isNaN(lng)) {
            if (useGoogle) {
                new google.maps.Marker({ position: { lat, lng }, map: map });
                map.setCenter({ lat, lng });
                map.setZoom(13);
            } else {
                L.marker([lat, lng]).addTo(map);
                map.setView([lat, lng], 13);
            }
            return;
        }
    }

    // Fetch events/orgs from REST API (using global EAD_VARS)
    if (typeof EAD_VARS === 'undefined' || !EAD_VARS.ajaxUrl) {
        console.error('Map endpoint not available.');
        return;
    }
    const nonce = EAD_VARS.eventsMapNonce || '';

    fetch(EAD_VARS.ajaxUrl + 'events?per_page=100', {
        method: 'GET',
        headers: {
            'X-WP-Nonce': nonce
        }
    })
    .then(resp => resp.json())
    .then(data => {
        if (!data.events) return;
        const bounds = useGoogle ? new google.maps.LatLngBounds() : [];
        data.events.forEach(event => {
            if (!event.event_lat || !event.event_lng) return;
            if (useGoogle) {
                const pos = { lat: parseFloat(event.event_lat), lng: parseFloat(event.event_lng) };
                const marker = new google.maps.Marker({ position: pos, map: map });
                const info = new google.maps.InfoWindow({ content: `<strong>${event.title}</strong><br>${event.start_date} - ${event.end_date}` });
                marker.addListener('click', () => info.open(map, marker));
                bounds.extend(pos);
            } else {
                const marker = L.marker([event.event_lat, event.event_lng]).addTo(map);
                marker.bindPopup(`<strong>${event.title}</strong><br>${event.start_date} - ${event.end_date}`);
                bounds.push([event.event_lat, event.event_lng]);
            }
        });
        if (useGoogle) {
            if (!bounds.isEmpty()) map.fitBounds(bounds);
        } else {
            if (bounds.length) map.fitBounds(bounds);
        }
    })
    .catch(error => {
        console.error('Failed to load map data:', error);
    });
});
