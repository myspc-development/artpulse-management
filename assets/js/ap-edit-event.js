jQuery(document).ready(function ($) {
    $('#ap-edit-event-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const nonceField = form.find('input[name="nonce"]');
        const nonceValue = nonceField.val() || (typeof APEditEvent !== 'undefined' ? APEditEvent.nonce : '');
        const data = {
            action: 'ap_save_event',
            nonce: nonceValue,
            post_id: form.data('post-id'),
            title: form.find('[name=\"title\"]').val(),
            content: form.find('[name=\"content\"]').val(),
            date: form.find('[name=\"date\"]').val(),
            location: form.find('[name=\"location\"]').val(),
            event_type: form.find('[name=\"event_type\"]').val()
        };
        $.post(APEditEvent.ajax_url, data, function (res) {
            if (res.success) {
                form.find('.ap-edit-event-error').text('Saved!').css('color', 'green');
            } else {
                form.find('.ap-edit-event-error').text(res.data.message || 'Error saving.');
            }
        });
    });
});

$('#ap-delete-event-btn').on('click', function (e) {
    e.preventDefault();
    if (!confirm('Are you sure you want to delete this event?')) return;

    const form = $('#ap-edit-event-form');
    const nonceField = form.find('input[name="nonce"]');
    const nonceValue = nonceField.val() || (typeof APEditEvent !== 'undefined' ? APEditEvent.nonce : '');

    $.post(APEditEvent.ajax_url, {
        action: 'ap_delete_event',
        nonce: nonceValue,
        post_id: $(this).data('post-id')
    }, function (res) {
        if (res.success) {
            alert('Event deleted.');
            window.location.href = '/events'; // Redirect after deletion
        } else {
            alert(res.data.message || 'Failed to delete.');
        }
    });
});
