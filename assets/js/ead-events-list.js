jQuery(document).ready(function($) {
    // Target container
    const $eventsList = $('#ead-events-list');
    // API endpoint and nonce set via wp_localize_script in your PHP
    const restUrl = typeof eadEventsApi !== 'undefined' ? eadEventsApi.restUrl : false;
    const nonce = typeof eadEventsApi !== 'undefined' ? eadEventsApi.nonce : '';

    function renderEvent(event) {
        // Example fields: adjust as needed for your event meta/REST structure
        const date = (event.meta && event.meta.event_date) ? event.meta.event_date : '';
        const excerpt = (event.excerpt && event.excerpt.rendered) ? event.excerpt.rendered : '';
        const location = [
            event.meta?.country,
            event.meta?.state,
            event.meta?.city
        ].filter(Boolean).join(', ');

        return `
            <div class="ead-event-card">
                <h3>${event.title?.rendered || 'Untitled Event'}</h3>
                <p><strong>Date:</strong> ${date}</p>
                <p><strong>Location:</strong> ${location}</p>
                <div>${excerpt}</div>
                <a href="${event.link}" class="ead-event-view-link">View Details</a>
            </div>
        `;
    }

    function fetchEvents() {
        if (!restUrl) {
            $eventsList.html('<p>Event API endpoint not configured.</p>');
            return;
        }

        $eventsList.html('<p>Loading events...</p>');

        $.ajax({
            url: restUrl,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(response) {
                if (!response || !Array.isArray(response) || response.length === 0) {
                    $eventsList.html('<p>No events found.</p>');
                    return;
                }
                // Map events to cards
                const html = response.map(renderEvent).join('');
                $eventsList.html(html);
            },
            error: function(xhr) {
                $eventsList.html('<p>Could not load events. Please try again later.</p>');
            }
        });
    }

    // Call on page load
    fetchEvents();
});
