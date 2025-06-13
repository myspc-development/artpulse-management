jQuery(document).ready(function($){
    const restUrl = eadUserDashboard.restUrl;
    const nonce = eadUserDashboard.nonce;

    function showLoader() {
        $('#ead-loader').show();
    }

    function hideLoader() {
        $('#ead-loader').hide();
    }

    function showToast(message, isError = false) {
        const toast = $('#ead-toast');
        toast.text(message);
        toast.css('background', isError ? '#d63638' : '#0073aa');
        toast.fadeIn(200);
        setTimeout(() => {
            toast.fadeOut(300);
        }, 3000);
    }

    function loadEventCalendar() {
        $.ajax({
            url: eadUserDashboard.restUrl + '/calendar',
            method: 'GET',
            headers: { 'X-WP-Nonce': eadUserDashboard.nonce },
            success: function (events) {
                const calendarEl = document.getElementById('ead-event-calendar');
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,listWeek'
                    },
                    events: events,
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();
                        window.open(info.event.url, '_blank');
                    }
                });
                calendar.render();
            }
        });
    }

    function renderEvent(event) {
        const location = [event.venue?.city, event.venue?.state, event.venue?.country]
            .filter(Boolean).join(', ');

        const isFavorite = eadUserDashboard.favorites?.includes(event.id);
        const heartIcon = isFavorite ? '❤️' : '🤍';
        const isRSVP = eadUserDashboard.rsvps?.includes(event.id);
        const rsvpLabel = isRSVP ? '✅ Going' : '📅 RSVP';

        return `
        <div class="ead-event-card" data-event-id="${event.id}">
            <h3>${event.title}</h3>
            <p>${location}</p>
            <a href="${event.link}">${event.link}</a>
            <div class="ead-event-actions">
                <button class="ead-rsvp-btn" data-id="${event.id}" data-rsvped="${isRSVP}">
                    ${rsvpLabel}
                </button>
                <button class="ead-favorite-btn" data-id="${event.id}" data-favorited="${isFavorite}">
                    ${heartIcon}
                </button>
            </div>
        </div>
        `;
    }

    function fetchSubmissions() {
        $.ajax({
            url: restUrl + '/submissions',
            method: 'GET',
            headers: { 'X-WP-Nonce': nonce },
            success: function(items) {
                if (!items.length) {
                    $('#ead-submission-list').html('<p>No pending submissions.</p>');
                    return;
                }

                const html = items.map(item => `
                    <div class="ead-submission-card" data-id="${item.id}">
                        <img src="${item.thumb}" alt="${item.title}" />
                        <h4>${item.title}</h4>
                        <p>By ${item.author} on ${item.date}</p>
                        <button class="approve-submission" data-action="approve">✅ Approve</button>
                        <button class="reject-submission" data-action="reject">❌ Reject</button>
                    </div>
                `).join('');

                $('#ead-submission-list').html(html);
            }
        });
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
                showLoader();
            },
            success: function (response) {
                const html = response.length
                    ? response.map(renderEvent).join('')
                    : '<p class="ead-empty-state">No events found matching your filters.</p>';
                $('#ead-user-events').html(html);

                $('.ead-rsvp-btn').on('click', function () {
                    const postId = parseInt($(this).data('id'));
                    const isGoing = $(this).data('rsvped');
                    toggleRSVP(postId, isGoing);
                });

                $('.ead-favorite-btn').on('click', function () {
                    const postId = parseInt($(this).data('id'));
                    const isFavorited = $(this).data('favorited');

                    toggleFavorite(postId, isFavorited);
                });
            },
            error: function () {
                $('#ead-user-events').html('<p>Error loading events. Please try again later.</p>');
            }
        }).always(function(){
            hideLoader();
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

    function fetchFavorites() {
        $('#ead-tab-favorites').html('<p>Loading your favorites...</p>');

        $.ajax({
            url: restUrl + '/favorites',
            method: 'GET',
            headers: {
                'X-WP-Nonce': nonce
            },
            success: function (events) {
                const html = events.length
                    ? events.map(renderEvent).join('')
                    : '<p>You haven\u2019t favorited any events yet.</p>';

                $('#ead-tab-favorites').html(html);

                $('.ead-rsvp-btn').on('click', function () {
                    const postId = parseInt($(this).data('id'));
                    const isGoing = $(this).data('rsvped');
                    toggleRSVP(postId, isGoing);
                });
                $('.ead-favorite-btn').on('click', function () {
                    const postId = parseInt($(this).data('id'));
                    const isFavorited = $(this).data('favorited');
                    toggleFavorite(postId, isFavorited);
                });
            },
            error: function () {
                $('#ead-tab-favorites').html('<p>Error loading favorites.</p>');
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

    function loadActivityChart() {
        $.ajax({
            url: eadUserDashboard.restUrl + '/activity',
            method: 'GET',
            headers: { 'X-WP-Nonce': eadUserDashboard.nonce },
            success: function (res) {
                const ctx = document.getElementById('ead-activity-chart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: res.labels,
                        datasets: [{
                            label: 'RSVPs per Month',
                            data: res.data,
                            backgroundColor: '#0073aa'
                        }]
                    },
                    options: {
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
        });
    }

function loadUserBadges() {
        $.ajax({
            url: eadUserDashboard.restUrl + '/badges',
            method: 'GET',
            headers: { 'X-WP-Nonce': eadUserDashboard.nonce },
            success: function (badges) {
                const container = $('#ead-profile-badges');
                if (!badges.length) {
                    container.html('<p>No badges yet. Start exploring!</p>');
                    return;
                }

                const html = badges.map(badge => `
                    <div class="ead-badge">
                        <span class="ead-badge-icon">${badge.label}</span>
                        <span class="ead-badge-desc">${badge.desc}</span>
                    </div>
                `).join('');

                container.html(html);
            }
        });
}

    function updateDashboardStats() {
        fetchUserSummary();
        loadUserBadges();
        loadActivityChart();
    }

    function loadSubmissionStats() {
        $.ajax({
            url: eadUserDashboard.restUrl + '/submissions/stats',
            method: 'GET',
            headers: { 'X-WP-Nonce': eadUserDashboard.nonce },
            success: function (res) {
                $('#widget-pending').text(res.pending);
                $('#widget-approved').text(res.approved);
                $('#widget-rejected').text(res.rejected);

                const ctx = document.getElementById('ead-submission-chart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: res.labels,
                        datasets: [{
                            label: 'Approved Submissions',
                            data: res.data,
                            fill: true,
                            tension: 0.3,
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.2)'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }
        });
    }

    function fetchFavorites() {
        $.ajax({
            url: restUrl + '/favorites',
            method: 'GET',
            headers: {
                'X-WP-Nonce': nonce
            },
            beforeSend: showLoader,
            success: function (ids) {
                if (!ids.length) {
                    $('#ead-tab-favorites').html('<p class="ead-empty-state">You haven\u2019t favorited any events yet.</p>');
                    return;
                }

                const requests = ids.map(id => $.ajax({ url: restUrl + '/events/' + id, method: 'GET' }));

                $.when.apply($, requests).done(function () {
                    const events = Array.from(arguments).map(res => res[0]);
                    const html = events.map(renderEvent).join('');
                    $('#ead-tab-favorites').html(html);
                    $('.ead-rsvp-btn').on('click', function () {
                        const postId = parseInt($(this).data('id'));
                        const isGoing = $(this).data('rsvped');
                        toggleRSVP(postId, isGoing);
                    });
                    $('.ead-favorite-btn').on('click', function () {
                        const postId = parseInt($(this).data('id'));
                        const isFavorited = $(this).data('favorited');
                        toggleFavorite(postId, isFavorited);
                    });
                }).fail(function(){
                    $('#ead-tab-favorites').html('<p class="ead-empty-state">You haven\u2019t favorited any events yet.</p>');
                });
            },
            error: function () {
                $('#ead-tab-favorites').html('<p class="ead-empty-state">You haven\u2019t favorited any events yet.</p>');
            },
            complete: hideLoader
        });
    }

    function toggleRSVP(postId, isGoing) {
        const btn = $(`.ead-rsvp-btn[data-id="${postId}"]`);
        btn.prop('disabled', true);

        $.ajax({
            url: eadUserDashboard.restUrl + '/rsvp',
            method: 'POST',
            data: { event_id: postId },
            headers: { 'X-WP-Nonce': eadUserDashboard.nonce },
            success: function (res) {
                const going = res.status === 'added';
                btn.data('rsvped', going);
                btn.text(going ? '✅ Going' : '📅 RSVP');
                eadUserDashboard.rsvps = res.rsvps;
                showToast(going ? 'RSVP confirmed!' : 'RSVP removed.');
            },
            error: function () {
                showToast('RSVP failed.', true);
            },
            complete: function () {
                btn.prop('disabled', false);
            }
        });
    }

    function toggleFavorite(postId, isFavorited) {
        const method = isFavorited ? 'DELETE' : 'POST';
        const btn = $(`.ead-favorite-btn[data-id="${postId}"]`);
        btn.prop('disabled', true);

        $.ajax({
            url: restUrl + '/favorites',
            method: method,
            data: { post_id: postId },
            headers: {
                'X-WP-Nonce': nonce
            },
            beforeSend: function () {
                showLoader();
            },
            success: function (res) {
                const newFavorited = !isFavorited;

                btn.data('favorited', newFavorited);
                btn.html(newFavorited ? '❤️' : '🤍');

                eadUserDashboard.favorites = res.favorites;
                fetchUserSummary();
                fetchFavorites();
                showToast('Favorites updated!');
            },
            error: function () {
                showToast('Error updating favorites.', true);
            },
            complete: function () {
                btn.prop('disabled', false);
                hideLoader();
            }
        });
    }

    function fetchNotifications() {
        $('#ead-tab-notifications').html('<p>Loading...</p>');

        $.ajax({
            url: restUrl + '/notifications',
            method: 'GET',
            headers: { 'X-WP-Nonce': nonce },
            success: function (msgs) {
                if (!msgs.length) {
                    $('#ead-tab-notifications').html('<p>You have no new messages.</p>');
                    return;
                }

                const html = msgs.map(msg => `
                    <div class="ead-message-card">
                        <h4>${msg.title}</h4>
                        <p class="ead-message-meta">${msg.date}</p>
                        <div class="ead-message-body">${msg.content}</div>
                    </div>
                `).join('');

                $('#ead-tab-notifications').html(html);
            },
            error: function () {
                $('#ead-tab-notifications').html('<p>Error loading messages.</p>');
            }
        });
    }

    $('#ead-profile-form').on('submit', function (e) {
        e.preventDefault();

        const data = {
            display_name: $('#ead-profile-name').val(),
            city: $('#ead-profile-city').val(),
            country: $('#ead-profile-country').val(),
            newsletter: $('#ead-profile-newsletter').is(':checked'),
        };

        $.ajax({
            url: eadUserDashboard.restUrl + '/profile',
            method: 'POST',
            data,
            headers: { 'X-WP-Nonce': eadUserDashboard.nonce },
            beforeSend: showLoader,
            complete: hideLoader,
            success: function () {
                showToast('Profile updated successfully!');
            },
            error: function () {
                showToast('Error saving profile.', true);
            }
        });
    });

    $('#ead-password-form').on('submit', function (e) {
        e.preventDefault();

        const current = $('#ead-password-current').val();
        const newPass = $('#ead-password-new').val();
        const confirm = $('#ead-password-confirm').val();

        if (newPass !== confirm) {
            showToast('New passwords do not match.', true);
            return;
        }

        $.ajax({
            url: eadUserDashboard.restUrl + '/change-password',
            method: 'POST',
            data: {
                current_password: current,
                new_password: newPass,
                confirm_password: confirm,
            },
            headers: { 'X-WP-Nonce': eadUserDashboard.nonce },
            beforeSend: showLoader,
            complete: hideLoader,
            success: function () {
                showToast('Password updated!');
                $('#ead-password-form')[0].reset();
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON?.error || 'Error updating password.';
                showToast(errorMsg, true);
            }
        });
    });

    $('#ead-upload-form').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);

        $.ajax({
            url: eadUserDashboard.restUrl + '/upload',
            method: 'POST',
            headers: { 'X-WP-Nonce': eadUserDashboard.nonce },
            processData: false,
            contentType: false,
            data: formData,
            beforeSend: showLoader,
            complete: hideLoader,
            success: function () {
                $('#ead-upload-feedback').html('<p>Upload successful!</p>');
                $('#ead-upload-form')[0].reset();
            },
            error: function () {
                $('#ead-upload-feedback').html('<p>Upload failed. Try again.</p>');
            }
        });
    });

    $('#ead-user-autocomplete').on('input', function () {
        const query = $(this).val();
        if (query.length < 2) return $('#ead-user-suggestions').hide();

        $.ajax({
            url: eadUserDashboard.restUrl + '/users/search',
            data: { term: query },
            headers: { 'X-WP-Nonce': eadUserDashboard.nonce },
            success: function (users) {
                const suggestions = users.map(user => `
                    <div class="ead-suggestion" data-id="${user.id}">${user.name}</div>
                `).join('');

                $('#ead-user-suggestions').html(suggestions).show();
            }
        });
    });

    $(document).on('click', '.ead-suggestion', function () {
        const name = $(this).text();
        const id = $(this).data('id');
        $('#ead-user-autocomplete').val(name);
        $('#ead-user-id').val(id);
        $('#ead-user-suggestions').hide();
    });

    $(document).on('click', '.approve-submission, .reject-submission', function () {
        const postId = $(this).closest('.ead-submission-card').data('id');
        const action = $(this).data('action');

        $.ajax({
            url: `${eadUserDashboard.restUrl}/submission/${postId}`,
            method: 'POST',
            data: { action },
            headers: { 'X-WP-Nonce': eadUserDashboard.nonce },
            success: function () {
                showToast(`Submission ${action}d.`);
                fetchSubmissions();
            }
        });
    });

    fetchEvents();
    fetchRecommendations();
    updateDashboardStats();
    fetchFavorites();
    loadSubmissionStats();

    $('.ead-tab-button').on('click', function () {
        const tab = $(this).data('tab');

        $('.ead-tab-button').removeClass('active');
        $(this).addClass('active');

        $('.ead-tab-content').removeClass('active');
        $('#ead-tab-' + tab).addClass('active');

        if (tab === 'favorites') {
            fetchFavorites();
        } else if (tab === 'notifications') {
            fetchNotifications();
        } else if (tab === 'profile') {
            fetchUserSummary();
            loadActivityChart();
            loadUserBadges();
        } else if (tab === 'dashboard') {
            updateDashboardStats();
        } else if (tab === 'submissions') {
            fetchSubmissions();
            loadSubmissionStats();
        } else if (tab === 'calendar') {
            loadEventCalendar();
        }
    });
});
