jQuery(document).ready(function($) {
    let mediaFrame;

    $('#artist_select_image').on('click', function(e) {
        e.preventDefault();

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: 'Select Profile Image',
            multiple: false
        });

        mediaFrame.on('select', function() {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            $('#artist_portrait').val(attachment.id);
            $('#artist-profile-image-preview').attr('src', attachment.url).show();
            $('#artist_remove_image').show();
        });

        mediaFrame.open();
    });

    $('#artist_remove_image').on('click', function(e) {
        e.preventDefault();
        $('#artist_portrait').val('');
        $('#artist-profile-image-preview').attr('src', '').hide();
        $(this).hide();
    });

    // Default artist email to registration email if empty
    const $regEmailInput = $('#registration_email');
    const $artistEmailInput = $('#artist_email');
    if ($artistEmailInput.length && $regEmailInput.length && !$artistEmailInput.val()) {
        $artistEmailInput.val($regEmailInput.val());
    }
    $regEmailInput.on('change', function() {
        if (!$artistEmailInput.val()) {
            $artistEmailInput.val($(this).val());
        }
    });

    // Basic form validation
    const $form = $('.ap-artist-registration-form');
    if ($form.length) {
        $form.on('submit', function(e) {
            e.preventDefault();
            let isValid = true;
            let firstErrorField = null;

            $form.find('.ead-input-error').removeClass('ead-input-error');
            $form.find('.ead-form-error').remove();

            // Basic required validation (customize for your fields)
            $form.find('input[required], select[required], textarea[required]').each(function() {
                const $field = $(this);
                if (!$field.val()) {
                    isValid = false;
                    $field.addClass('ead-input-error')
                          .after('<span class="ead-form-error">This field is required.</span>');
                    if (!firstErrorField) firstErrorField = $field;
                }
            });

            // Email validation (registration and artist contact)
            const $regEmail = $('#registration_email');
            const $artistEmail = $('#artist_email');
            const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;

            if ($regEmail.val() && !emailRegex.test($regEmail.val())) {
                isValid = false;
                $regEmail.addClass('ead-input-error')
                        .after('<span class="ead-form-error">Invalid email format.</span>');
                if (!firstErrorField) firstErrorField = $regEmail;
            }

            if ($artistEmail.val() && !emailRegex.test($artistEmail.val())) {
                isValid = false;
                $artistEmail.addClass('ead-input-error')
                           .after('<span class="ead-form-error">Invalid email format.</span>');
                if (!firstErrorField) firstErrorField = $artistEmail;
            }

            if (!isValid) {
                $('html, body').animate({
                    scrollTop: (firstErrorField ? firstErrorField.offset().top - 100 : $form.offset().top - 100)
                }, 500);
                return;
            }

            const submitBtn = $form.find('button[type="submit"]');
            submitBtn.prop('disabled', true).text('Registering...');

            const formData = new FormData(this);

            fetch(eadArtistRegistration.restUrl, {
                method: 'POST',
                headers: { 'X-WP-Nonce': eadArtistRegistration.nonce },
                body: formData
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Registration failed.');
                }

                if (data.confirmation_url) {
                    window.location.href = data.confirmation_url;
                } else {
                    $form.replaceWith('<div class="ead-artist-registration-success"><p>' + (data.message || 'Registered.') + '</p></div>');
                }
            })
            .catch(err => {
                alert(err.message || 'Submission failed.');
                submitBtn.prop('disabled', false).text('Register as Artist');
            });
        });

        // Remove error state on input change
        $form.on('input change', 'input, select, textarea', function() {
            $(this).removeClass('ead-input-error').next('.ead-form-error').remove();
        });
    }
});
