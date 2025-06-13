// assets/js/ead-org-map-ajax.js


// Example usage with EAD_VARS and fetch for organization map data
document.addEventListener('DOMContentLoaded', () => {
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

    function fetchOrgMapData() {
        if (typeof EAD_VARS === 'undefined' || !EAD_VARS.ajaxUrl) {
            console.error('Map endpoint not available.');
            return;
        }
        const nonce = EAD_VARS.orgMapNonce || '';

        fetch(EAD_VARS.ajaxUrl + 'organization-map', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': nonce
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.organizations) return;
            const bounds = useGoogle ? new google.maps.LatLngBounds() : [];
            data.organizations.forEach(org => {
                if (!org.org_lat || !org.org_lng) return;
                if (useGoogle) {
                    const pos = { lat: parseFloat(org.org_lat), lng: parseFloat(org.org_lng) };
                    const marker = new google.maps.Marker({ position: pos, map: map });
                    const info = new google.maps.InfoWindow({ content: `<strong>${org.title}</strong>` });
                    marker.addListener('click', () => info.open(map, marker));
                    bounds.extend(pos);
                } else {
                    const marker = L.marker([org.org_lat, org.org_lng]).addTo(map);
                    marker.bindPopup(`<strong>${org.title}</strong>`);
                    bounds.push([org.org_lat, org.org_lng]);
                }
            });
            if (useGoogle) {
                if (!bounds.isEmpty()) map.fitBounds(bounds);
            } else if (bounds.length) {
                map.fitBounds(bounds);
            }
        })
        .catch(error => {
            console.error('Failed to load organization map data:', error);
        });
    }

    // Fetch on load
    fetchOrgMapData();
});
