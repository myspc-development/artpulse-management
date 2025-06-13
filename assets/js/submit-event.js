jQuery(document).ready(function($) {
    $('#ead-submit-event-form').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const formData = new FormData(this);

        const $messageBox = $('#ead-submit-event-message');
        $messageBox.hide().removeClass('success error').empty();

        $.ajax({
            url: eadSubmitEvent.restUrl,
            method: 'POST',
            processData: false,
            contentType: false,
            data: formData,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', eadSubmitEvent.restNonce);
            },
            success: function(response) {
                if (response.success) {
                    $messageBox
                        .addClass('success')
                        .html('<div class="notice notice-success">' + response.message + '</div>')
                        .fadeIn()
                        .delay(4000)
                        .fadeOut();

                    $form[0].reset();
                } else {
                    const errorMsg = response.message || 'An error occurred.';
                    $messageBox
                        .addClass('error')
                        .html('<div class="notice notice-error">' + errorMsg + '</div>')
                        .fadeIn()
                        .delay(4000)
                        .fadeOut();
                }
            },
            error: function(xhr) {
                let message = 'An unexpected error occurred.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                $messageBox
                    .addClass('error')
                    .html('<div class="notice notice-error">' + message + '</div>')
                    .fadeIn()
                    .delay(4000)
                    .fadeOut();
            }
        });
    });
});
