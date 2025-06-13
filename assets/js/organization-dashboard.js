jQuery(document).ready(function($) {
    const $refreshBtn = $('#ead-refresh-metrics');
    const $metricsWidgets = $('#ead-organization-metrics-widgets');
    const $eventAnalytics = $('#ead-event-analytics');
    const $rsvpsContainer = $('#ead-my-event-rsvps');

    // Show skeleton while loading
    function showSkeleton() {
        if ($metricsWidgets.length) {
            let skeleton = '';
            for (let i = 0; i < 10; i++) {
                skeleton += '<div class="ead-metric-widget ead-metric-skeleton">&nbsp;</div>';
            }
            $metricsWidgets.html(skeleton);
        }
        if ($eventAnalytics.length) {
            $eventAnalytics.html('<p>Loading...</p>');
        }
    }

    // Show error
    function showError(msg) {
        if ($metricsWidgets.length) {
            $metricsWidgets.html('<div class="ead-metric-widget-error">' + (msg || 'Failed to load metrics.') + '</div>');
        }
        if ($eventAnalytics.length) {
            $eventAnalytics.html('');
        }
    }

    // Generate HTML for all widgets
    function renderWidgets(data) {
        // Destructure (fallback to zero)
        const {
            events_count = 0,
            pending_events = 0,
            draft_events = 0,
            featured_events = 0,
            upcoming_events = 0,
            expired_events = 0,
            artworks_count = 0,
            pending_reviews = 0,
            total_rsvps = 0,
            bookings_count = 0
        } = data;

        return `
            <div class="ead-metric-widget"><div class="ead-metric-label">Published Events</div><div class="ead-metric-value">${events_count}</div></div>
            <div class="ead-metric-widget"><div class="ead-metric-label">Pending Events</div><div class="ead-metric-value">${pending_events}</div></div>
            <div class="ead-metric-widget"><div class="ead-metric-label">Draft Events</div><div class="ead-metric-value">${draft_events}</div></div>
            <div class="ead-metric-widget"><div class="ead-metric-label">Featured Events</div><div class="ead-metric-value">${featured_events}</div></div>
            <div class="ead-metric-widget"><div class="ead-metric-label">Upcoming Events</div><div class="ead-metric-value">${upcoming_events}</div></div>
            <div class="ead-metric-widget"><div class="ead-metric-label">Expired Events</div><div class="ead-metric-value">${expired_events}</div></div>
            <div class="ead-metric-widget"><div class="ead-metric-label">Artworks</div><div class="ead-metric-value">${artworks_count}</div></div>
            <div class="ead-metric-widget"><div class="ead-metric-label">Pending Reviews</div><div class="ead-metric-value">${pending_reviews}</div></div>
            <div class="ead-metric-widget"><div class="ead-metric-label">Total RSVPs</div><div class="ead-metric-value">${total_rsvps}</div></div>
            <div class="ead-metric-widget"><div class="ead-metric-label">Bookings</div><div class="ead-metric-value">${bookings_count}</div></div>
        `;
    }

    function renderEventsTable(list) {
        if (!Array.isArray(list) || !list.length) {
            return '<p>No analytics found.</p>';
        }
        let rows = '';
        list.forEach(ev => {
            rows += `<tr><td>${ev.title}</td><td>${ev.views}</td><td>${ev.clicks}</td></tr>`;
        });
        return `<table><thead><tr><th>Event</th><th>Views</th><th>Clicks</th></tr></thead><tbody>${rows}</tbody></table>`;
    }

    // Load metrics from REST endpoint
    function loadMetrics() {
        if (!$metricsWidgets.length) return;
        if (typeof eadOrganizationDashboardApi === 'undefined' || !eadOrganizationDashboardApi.restUrl) {
            showError('Metrics endpoint not available.');
            return;
        }
        const nonce = eadOrganizationDashboardApi.nonce || '';
        showSkeleton();

        fetch(eadOrganizationDashboardApi.restUrl + 'organizations/dashboard', {
            method: 'GET',
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(response => response.json())
        .then(data => {
            let d = data;
            if (typeof data.data === 'object') d = data.data;
            if (typeof d.events_count !== 'undefined') {
                $metricsWidgets.html(renderWidgets(d));
                if ($eventAnalytics.length) {
                    $eventAnalytics.html(renderEventsTable(d.event_analytics || []));
                }
            } else {
                showError('No metrics data found.');
            }
        })
        .catch(() => {
            showError();
        });
    }

    // Initial load
    loadMetrics();

    // Load RSVPs via AJAX
    function loadRsvps() {
        if (!$rsvpsContainer.length) return;
        if (typeof eadOrganizationDashboardApi === 'undefined' || !eadOrganizationDashboardApi.ajaxUrl) {
            $rsvpsContainer.html('<p>RSVP endpoint not available.</p>');
            return;
        }
        const body = new URLSearchParams();
        body.append('action', 'ead_get_my_rsvps');

        fetch(eadOrganizationDashboardApi.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data && data.data.html) {
                $rsvpsContainer.html(data.data.html);
            } else {
                $rsvpsContainer.html('<p>No RSVPs found.</p>');
            }
        })
        .catch(() => {
            $rsvpsContainer.html('<p>Failed to load RSVPs.</p>');
        });
    }

    loadRsvps();

    // Add RSVP via AJAX
    const $rsvpForm = $('#ead-dashboard-rsvp-form');
    const $rsvpMsg = $('#ead-dashboard-rsvp-message');

    if ($rsvpForm.length) {
        $rsvpForm.on('submit', function(e) {
            e.preventDefault();
            if (typeof eadOrganizationDashboardApi === 'undefined' || !eadOrganizationDashboardApi.ajaxUrl) return;

            const body = new URLSearchParams();
            body.append('action', 'ead_admin_add_rsvp');
            body.append('email', $('#ead-dashboard-rsvp-email').val());
            body.append('event_id', $('#ead-dashboard-rsvp-event').val());
            body.append('ead_event_rsvp_nonce', $rsvpForm.find('input[name="ead_event_rsvp_nonce"]').val());

            $rsvpMsg.removeClass('success error').text('');
            fetch(eadOrganizationDashboardApi.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    $rsvpMsg.addClass('success').text(data.data.message || 'Saved');
                    $rsvpForm[0].reset();
                    loadRsvps();
                } else {
                    $rsvpMsg.addClass('error').text(data.data && data.data.message ? data.data.message : 'Error');
                }
            })
            .catch(() => {
                $rsvpMsg.addClass('error').text('Error');
            });
        });
    }

    // Refresh on button click
    if ($refreshBtn.length) {
        $refreshBtn.on('click', function(e) {
            e.preventDefault();
            loadMetrics();
        });
    }
});
