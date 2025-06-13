jQuery(document).ready(function($){
    const restUrl = eadUserDashboard.restUrl;
    const nonce = eadUserDashboard.nonce;

    function renderEvent(event){
        const location = [
            event.venue?.city,
            event.venue?.state,
            event.venue?.country
        ].filter(Boolean).join(', ');
        return `<div class="ead-event-card">
            <h3>${event.title}</h3>
            <p>${location}</p>
            <a href="${event.link}">${event.link}</a>
        </div>`;
    }

    function fetchEvents() {
        const city = $('#ead-filter-city').val();
        const state = $('#ead-filter-state').val();
        const country = $('#ead-filter-country').val();
        const type = $('#ead-filter-type').val();
        const startDate = $('#ead-filter-start-date').val();
        const endDate = $('#ead-filter-end-date').val();
        const sort = $('#ead-filter-sort').val();

        const data = {
            city,
            state,
            country,
            event_type: type,
            start_date: startDate,
            end_date: endDate,
            sort
        };

        $.ajax({
            url: restUrl + '/events',
            data,
            method: 'GET',
            beforeSend: function () {
                $('#ead-user-events').html('<p>Loading events...</p>');
            },
            success: function (response) {
                const eventsHTML = response.map(renderEvent).join('');
                $('#ead-user-events').html(eventsHTML || '<p>No events found.</p>');
            },
            error: function () {
                $('#ead-user-events').html('<p>Error loading events. Please try again later.</p>');
            }
        });
    }

    $('#ead-filter-submit').on('click', function(e){
        e.preventDefault();
        fetchEvents();
    });

    function fetchRecommendations() {
        $.ajax({
            url: restUrl + '/recommendations',
            method: 'GET',
            headers: {
                'X-WP-Nonce': nonce
            },
            success: function (response) {
                const html = response.map(renderEvent).join('');
                $('#ead-user-recommendations').html(html || '<p>No recommendations yet.</p>');
            },
            error: function () {
                $('#ead-user-recommendations').html('<p>Unable to load recommendations.</p>');
            }
        });
    }

    function fetchUserSummary() {
        $.ajax({
            url: restUrl + '/summary',
            method: 'GET',
            headers: {
                'X-WP-Nonce': nonce
            },
            success: function (data) {
                $('#ead-user-fav-count').text(data.favorites || 0);
            },
            error: function () {
                $('#ead-user-fav-count').text('—');
            }
        });
    }

    fetchEvents();
    fetchRecommendations();
    fetchUserSummary();

    $('.ead-tab-button').on('click', function () {
        const tab = $(this).data('tab');

        $('.ead-tab-button').removeClass('active');
        $(this).addClass('active');

        $('.ead-tab-content').removeClass('active');
        $('#ead-tab-' + tab).addClass('active');
    });
});
