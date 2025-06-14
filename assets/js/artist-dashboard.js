jQuery(document).ready(function($) {
    function moderateEvent(eventId, action) {
        if (typeof eadDashboardApi === 'undefined' || !eadDashboardApi.restUrl) {
            alert('Event moderation endpoint not available.');
            return;
        }

        const url   = eadDashboardApi.restUrl + 'moderate-event';
        const nonce = eadDashboardApi.nonce;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({ action_type: action, event_id: eventId })
        })
        .then(response => response.json())
        .then(response => {
            if (response && response.success) {
                $('.ead-artist-dashboard').prepend('<div class="notice notice-success">' + response.message + '</div>');
            } else {
                $('.ead-artist-dashboard').prepend('<div class="notice notice-error">' + (response.message || 'Failed to moderate event.') + '</div>');
            }
        })
        .catch(() => {
            alert('Failed to moderate event.');
        })
        .finally(() => {
            setTimeout(() => {
                $('.ead-artist-dashboard .notice').remove();
                location.reload();
            }, 3000);
        });
    }

    $('.ead-approve-event').on('click', function(e) {
        e.preventDefault();
        const eventId = $(this).data('event-id');
        if (confirm('Are you sure you want to approve this event?')) {
            moderateEvent(eventId, 'approve');
        }
    });

    $('.ead-delete-event').on('click', function(e) {
        e.preventDefault();
        const eventId = $(this).data('event-id');
        if (confirm('Are you sure you want to delete this event?')) {
            moderateEvent(eventId, 'delete');
        }
    });
});