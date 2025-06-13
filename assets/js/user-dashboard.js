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

    function fetchEvents(){
        const city = $('#ead-filter-city').val();
        const state = $('#ead-filter-state').val();
        const country = $('#ead-filter-country').val();
        const type = $('#ead-filter-type').val();
        const data = {
            city: city,
            state: state,
            country: country,
            event_type: type
        };
        $.ajax({
            url: restUrl,
            data: data,
            method: 'GET',
            beforeSend: function(xhr){
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(res){
                if(!res || !Array.isArray(res) || res.length === 0){
                    $('#ead-user-events').html('<p>No events found.</p>');
                    return;
                }
                const html = res.map(renderEvent).join('');
                $('#ead-user-events').html(html);
            },
            error: function(){
                $('#ead-user-events').html('<p>Error loading events.</p>');
            }
        });
    }

    $('#ead-filter-submit').on('click', function(e){
        e.preventDefault();
        fetchEvents();
    });

    fetchEvents();

    $('.ead-tab-button').on('click', function () {
        const tab = $(this).data('tab');

        $('.ead-tab-button').removeClass('active');
        $(this).addClass('active');

        $('.ead-tab-content').removeClass('active');
        $('#ead-tab-' + tab).addClass('active');
    });
});
