jQuery(document).ready(function($) {
    $('#ead-event-rsvp-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $message = $('#ead-rsvp-message');
        var email = $('#ead-rsvp-email').val();
        var nonce = $form.find('input[name="ead_event_rsvp_nonce"]').val();
        var post_id = $form.find('input[name="post_id"]').val();

        $message.hide().removeClass('success error');
        $form.find('button[type="submit"]').prop('disabled', true).text('Submitting...');

        $.ajax({
            url: eadEventRsvp.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ead_event_rsvp',
                email: email,
                post_id: post_id,
                ead_event_rsvp_nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $message.text(response.data.message).addClass('success').show();
                    $form[0].reset();
                } else {
                    $message.text(response.data.message).addClass('error').show();
                }
            },
            error: function() {
                $message.text('An unexpected error occurred. Please try again.').addClass('error').show();
            },
            complete: function() {
                $form.find('button[type="submit"]').prop('disabled', false).text('RSVP');
            }
        });
    });
});
