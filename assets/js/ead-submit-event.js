jQuery(document).ready(function ($) {
    const $form = $('#ead-submit-event-form');
    const $messageBox = $('#ead-submit-event-message');
    const $spinner = $('#ead-submit-event-loading');
    const $submitButton = $form.find('button[type="submit"]');
    const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
    const MAX_GALLERY_FILES = 5;

    if (!$form.length) {
        return;
    }

    function showMessage(type, text) {
        $messageBox
            .removeClass('notice-success notice-error')
            .addClass('notice notice-' + type)
            .html('<p>' + text + '</p>')
            .fadeIn();
    }

    function displayFieldError($field, message) {
        removeFieldError($field);
        const $errorSpan = $('<span class="ead-form-error"></span>').text(message);
        $field.addClass('ead-input-error').after($errorSpan);
    }

    function removeFieldError($field) {
        $field.removeClass('ead-input-error');
        $field.next('.ead-form-error').remove();
    }

    // ==== Gallery via media modal ====
    $('.ead-upload-image-button').on('click', function (e) {
        e.preventDefault();
        const $container = $(this).closest('.ead-image-upload-container');
        const $idInput = $container.find('.ead-image-id-input');
        const $preview = $container.find('.ead-image-preview');
        const $remove = $container.find('.ead-remove-image-button');

        const frame = wp.media({
            title: eadEventGallery.select_image_title || 'Select Image',
            button: { text: eadEventGallery.use_image_button || 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            const size = attachment.filesizeInBytes || attachment.filesize || 0;
            if (size && size > MAX_FILE_SIZE) {
                showMessage('error', 'File too large: ' + (attachment.filename || attachment.url) + ' (max 2MB)');
                return;
            }
            $idInput.val(attachment.id);
            $preview
                .css('background-image', 'url(' + attachment.url + ')')
                .addClass('has-image')
                .html('');
            $remove.removeClass('hidden');
        });

        frame.open();
    });

    $('.ead-remove-image-button').on('click', function (e) {
        e.preventDefault();
        const $container = $(this).closest('.ead-image-upload-container');
        const $idInput = $container.find('.ead-image-id-input');
        const $preview = $container.find('.ead-image-preview');
        const index = $container.data('image-index');

        $idInput.val('');
        $preview
            .css('background-image', '')
            .removeClass('has-image')
            .html('<span class="placeholder">' + (eadEventGallery.placeholder_prefix || 'Image ') + (index + 1) + '</span>');
        $(this).addClass('hidden');
    });

    // ==== Form submission ====
    $form.on('submit', function (e) {
        e.preventDefault();
        let isValid = true;
        let firstErrorField = null;
        $messageBox.hide();
        $form.find('.ead-input-error').each(function () {
            removeFieldError($(this));
        });
        $form.find('.ead-form-summary-error').remove();

        // Required fields
        $form.find('input[required], select[required], textarea[required]').each(function () {
            const $field = $(this);
            if (!$field.val() || ($field.is('select') && $field.val() === '')) {
                isValid = false;
                displayFieldError($field, 'This field is required.');
                if (!firstErrorField) firstErrorField = $field;
            }
        });

        // Email validation
        const $orgEmail = $('#ead_event_organizer_email');
        if ($orgEmail.val() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($orgEmail.val())) {
            isValid = false;
            displayFieldError($orgEmail, 'Invalid email format.');
            if (!firstErrorField) firstErrorField = $orgEmail;
        }

        // Date range check
        const startDate = $('#ead_event_start_date').val();
        const endDate = $('#ead_event_end_date').val();
        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
            isValid = false;
            displayFieldError($('#ead_event_end_date'), 'End date must be after start date.');
            if (!firstErrorField) firstErrorField = $('#ead_event_end_date');
        }

        // Gather selected image IDs
        const imageIds = [];
        $('.ead-image-id-input').each(function () {
            const val = $(this).val();
            if (val) {
                imageIds.push(val);
            }
        });
        if (imageIds.length > MAX_GALLERY_FILES) {
            isValid = false;
            displayFieldError($('.ead-event-image-upload-area').first(), 'Maximum ' + MAX_GALLERY_FILES + ' images allowed.');
            if (!firstErrorField) firstErrorField = $('.ead-event-image-upload-area').first();
        }

        if (!isValid) {
            if (!$form.find('.ead-form-summary-error').length) {
                $form.prepend('<div class="ead-form-summary-error notice notice-error" style="padding:10px; margin-bottom:15px; border:1px solid red; background-color:#ffe9e9;">' + 'Please correct the errors below.' + '</div>');
            }
            $('html, body').animate({
                scrollTop: (firstErrorField ? firstErrorField.offset().top - 100 : $form.offset().top - 100)
            }, 500);
            return;
        }

        const formData = new FormData(this);
        formData.delete('event_gallery_images');
        imageIds.forEach(id => formData.append('event_gallery_images[]', id));

        $spinner.show();
        $submitButton.prop('disabled', true).text('Submitting...');
        $form.find('.ead-form-summary-error').remove();

        fetch(eadSubmitEvent.restUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-WP-Nonce': eadSubmitEvent.restNonce }
        })
            .then(async (response) => {
                const res = await response.json();
                if (response.ok) {
                    showMessage('success', res.message || 'Event submitted successfully!');
                    $form[0].reset();
                    $('.ead-image-upload-container').each(function (i) {
                        const $c = $(this);
                        $c.find('.ead-image-id-input').val('');
                        $c.find('.ead-image-preview')
                            .css('background-image', '')
                            .removeClass('has-image')
                            .html('<span class="placeholder">' + (eadEventGallery.placeholder_prefix || 'Image ') + (i + 1) + '</span>');
                        $c.find('.ead-remove-image-button').addClass('hidden');
                    });
                } else {
                    showMessage('error', res.message || 'An unexpected error occurred.');
                }
            })
            .catch(() => {
                showMessage('error', 'An unexpected error occurred.');
            })
            .finally(() => {
                $spinner.hide();
                $submitButton.prop('disabled', false).text('Submit Event');
            });
    });

    // Remove error messages dynamically
    $form.on('input change', 'input, select, textarea', function () {
        if ($(this).val()) {
            removeFieldError($(this));
        }
    });
});
