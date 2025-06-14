jQuery(document).ready(function ($) {
    // Image Upload Functionality
    $('.ead-upload-image-button').on('click', function (e) {
        e.preventDefault();
        const $button = $(this);
        const $container = $button.closest('.ead-image-upload-container');
        const $imageIdInput = $container.find('.ead-image-id-input');
        const $imagePreview = $container.find('.ead-image-preview');
        const $removeButton = $container.find('.ead-remove-image-button');
        const image = wp.media({
            title: eadArtworkSubmissionData.labels.select_image_title,
            multiple: false
        }).open()
            .on('select', function () {
                const uploadedImage = image.state().get('selection').first();
                const imageId = uploadedImage.toJSON().id;
                const imageUrl = uploadedImage.toJSON().url;
                $imageIdInput.val(imageId);
                $imagePreview.css('background-image', 'url(' + imageUrl + ')').addClass('has-image').html('');
                $removeButton.removeClass('hidden');
            });
    });

    $('.ead-remove-image-button').on('click', function (e) {
        e.preventDefault();
        const $button = $(this);
        const $container = $button.closest('.ead-image-upload-container');
        const $imageIdInput = $container.find('.ead-image-id-input');
        const $imagePreview = $container.find('.ead-image-preview');
        const index = $container.data('image-index');
        $imageIdInput.val('');
        $imagePreview.css('background-image', '').removeClass('has-image');
        $imagePreview.html('<span class="placeholder">Image ' + (index + 1) + '</span>');
        $button.addClass('hidden');
    });

    // Form Submission Handling
    $('#ead-artwork-submission-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const artworkId = $form.data('artwork-id');
        const submitButton = $('#ead-submit-artwork-button');
        const messageDiv = $('#ead-artwork-submission-message');

        // Disable the submit button and show message
        submitButton.prop('disabled', true).text(
            artworkId ? eadArtworkSubmissionData.labels.updating : eadArtworkSubmissionData.labels.submitting
        );
        messageDiv.hide().removeClass('success error').text('');

        // Collect form data
        const formData = {};
        $form.find('input[type="text"], input[type="number"], textarea').each(function () {
            const $input = $(this);
            if ($input.attr('name')) {
                formData[$input.attr('name')] = $input.val();
            }
        });

        // Collect checkboxes
        $form.find('input[type="checkbox"]').each(function () {
            const $checkbox = $(this);
            if ($checkbox.attr('name')) {
                formData[$checkbox.attr('name')] = $checkbox.is(':checked');
            }
        });

        // Collect image IDs
        const imageIds = [];
        $form.find('.ead-image-id-input').each(function () {
            const id = $(this).val();
            if (id) {
                imageIds.push(parseInt(id, 10));
            }
        });
        formData['artwork_gallery_images'] = imageIds;

        // REST endpoint URL
        const url = artworkId
            ? eadArtworkSubmissionData.rest_url_update + artworkId
            : eadArtworkSubmissionData.rest_url_submit;
        const method = artworkId ? 'PUT' : 'POST';

        // AJAX call
        $.ajax({
            url: url,
            type: method,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', eadArtworkSubmissionData.nonce);
            },
            data: JSON.stringify(formData),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            success: function (response) {
                messageDiv
                    .addClass('success')
                    .text(
                        artworkId
                            ? eadArtworkSubmissionData.labels.success_update
                            : eadArtworkSubmissionData.labels.success_submit
                    )
                    .show();
                if (!artworkId) {
                    // Reset form
                    $form[0].reset();
                    $('.ead-image-upload-container').each(function (i) {
                        const $container = $(this);
                        $container.find('.ead-image-id-input').val('');
                        $container.find('.ead-image-preview')
                            .css('background-image', '')
                            .removeClass('has-image')
                            .html('<span class="placeholder">Image ' + (i + 1) + '</span>');
                        $container.find('.ead-remove-image-button').addClass('hidden');
                    });
                }
            },
            error: function (jqXHR) {
                let errorMsg = eadArtworkSubmissionData.labels.error_general;
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    errorMsg = jqXHR.responseJSON.message;
                }
                messageDiv.addClass('error').text(errorMsg).show();
            },
            complete: function () {
                submitButton.prop('disabled', false).text(
                    artworkId ? eadArtworkSubmissionData.labels.updating : eadArtworkSubmissionData.labels.submitting
                );
            }
        });
    });
});