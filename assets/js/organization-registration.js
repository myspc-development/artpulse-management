jQuery(document).ready(function($) {
    // Initialize address select2 fields if the helper is loaded
    if (typeof eadAddress !== 'undefined') {
        // ead-address.js automatically attaches Select2 and related handlers
    }

    const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
    const $form = $('.ead-organization-form');
    const $submitButton = $form.find('button[type="submit"]');
    const $spinner = $('#ead-submit-organization-loading'); // Optional: add spinner element with this ID

    function displayFieldError($field, message) {
        removeFieldError($field);
        const $errorSpan = $('<span class="ead-form-error"></span>').text(message);
        $field.addClass('ead-input-error').after($errorSpan);
    }

    function removeFieldError($field) {
        $field.removeClass('ead-input-error');
        $field.next('.ead-form-error').remove();
    }

    function handleImagePreview(inputId, previewId) {
        $('#' + inputId).on('change', function(event) {
            const input = event.target;
            const file = input.files[0];
            const $preview = $('#' + previewId);
            const $inputField = $(input);

            removeFieldError($inputField);

            if (file) {
                if (file.size > MAX_FILE_SIZE) {
                    displayFieldError($inputField, (EAD_OrgReg?.text_file_too_large?.replace('%s', '2MB') || 'File exceeds 2MB limit.'));
                    input.value = '';
                    $preview.hide().attr('src', '#');
                    return;
                }

                if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                    displayFieldError($inputField, (EAD_OrgReg?.text_invalid_file_type || 'Invalid file type (JPG, PNG, GIF only).'));
                    input.value = '';
                    $preview.hide().attr('src', '#');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    $preview.attr('src', e.target.result).show();
                };
                reader.readAsDataURL(file);
            } else {
                $preview.hide().attr('src', '#');
            }
        });
    }

    handleImagePreview('ead_org_logo_id', 'org-logo-image-preview');
    handleImagePreview('ead_org_banner_id', 'org-banner-image-preview');


    if ($form.length) {
        $form.on('submit', function(e) {
            e.preventDefault();

            let isValid = true;
            let firstErrorField = null;

            // Remove any existing errors
            $form.find('.ead-input-error').each(function() {
                removeFieldError($(this));
            });

            $form.find('.ead-form-summary-error').remove();

            // Validate required fields
            $form.find('input[required], select[required], textarea[required]').each(function() {
                const $field = $(this);

                if (!$field.val() || ($field.is('select') && $field.val() === "")) {
                    isValid = false;
                    displayFieldError($field, (EAD_OrgReg?.text_required_field || 'This field is required.'));
                    if (!firstErrorField) firstErrorField = $field;
                }
            });

            // Additional email & URL validations
            const $venueEmail = $('#ead_org_venue_email');
            if ($venueEmail.val() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($venueEmail.val())) {
                isValid = false;
                displayFieldError($venueEmail, (EAD_OrgReg?.text_invalid_email || 'Invalid email format.'));
                if (!firstErrorField) firstErrorField = $venueEmail;
            }

            const $contactEmail = $('#ead_org_primary_contact_email');
            if ($contactEmail.val() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($contactEmail.val())) {
                isValid = false;
                displayFieldError($contactEmail, (EAD_OrgReg?.text_invalid_email || 'Invalid email format.'));
                if (!firstErrorField) firstErrorField = $contactEmail;
            }

            const $orgWebsite = $('#ead_org_website_url');
            if ($orgWebsite.val() && !/^https?:\/\/.*/.test($orgWebsite.val())) {
                isValid = false;
                displayFieldError($orgWebsite, (EAD_OrgReg?.text_invalid_url || 'Invalid URL format.'));
                if (!firstErrorField) firstErrorField = $orgWebsite;
            }

            if (!isValid) {
                $('html, body').animate({
                    scrollTop: (firstErrorField ? firstErrorField.offset().top - 100 : $form.offset().top - 100)
                }, 500);

                $form.prepend('<div class="ead-form-summary-error notice notice-error" style="padding:10px; margin-bottom:15px; border:1px solid red; background-color:#ffe9e9;">' +
                    (EAD_OrgReg?.text_fill_required_fields || 'Please correct the errors below.') +
                    '</div>');
                return;
            }

            // All validations passed
            $submitButton.prop('disabled', true).text(EAD_OrgReg?.text_submitting || 'Submitting...');
            if ($spinner.length) $spinner.show();

            const formData = new FormData(this);

            // Prepare address data
            const addressData = {
                ead_country: formData.get('ead_country'),
                ead_state: formData.get('ead_state'),
                ead_city: formData.get('ead_city'),
                ead_suburb: formData.get('ead_suburb'),
                ead_street: formData.get('ead_street'),
                ead_postcode: formData.get('ead_postcode'),
                ead_latitude: formData.get('ead_latitude'), // if present
                ead_longitude: formData.get('ead_longitude'), // if present
            };

            formData.append('address_data', JSON.stringify(addressData));

            formData.delete('ead_country');
            formData.delete('ead_state');
            formData.delete('ead_city');
            formData.delete('ead_suburb');
            formData.delete('ead_street');
            formData.delete('ead_postcode');

            if (formData.has('ead_latitude')) {
                formData.delete('ead_latitude');
            }

            if (formData.has('ead_longitude')) {
                formData.delete('ead_longitude');
            }

            // Prepare venue opening hours data
            const openingHoursData = {};
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(day => {
                openingHoursData[`ead_org_venue_${day}_start_time`] = formData.get(`ead_org_venue_${day}_start_time`);
                openingHoursData[`ead_org_venue_${day}_end_time`] = formData.get(`ead_org_venue_${day}_end_time`);
                openingHoursData[`ead_org_venue_${day}_closed`] = formData.get(`ead_org_venue_${day}_closed`) === '1' ? 1 : 0;

                formData.delete(`ead_org_venue_${day}_start_time`);
                formData.delete(`ead_org_venue_${day}_end_time`);
                formData.delete(`ead_org_venue_${day}_closed`);
            });

            formData.append('opening_hours', JSON.stringify(openingHoursData));

            // Collect gallery image IDs selected via media library
            formData.delete('ead_org_gallery_images');
            const imageIds = [];
            $('.ead-image-id-input').each(function(){
                const val = $(this).val();
                if (val) {
                    imageIds.push(val);
                }
            });
            imageIds.forEach(id => formData.append('ead_org_gallery_images[]', id));

            fetch(EAD_VARS.restUrl, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': EAD_VARS.registrationNonce
                },
                body: formData
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Submission failed.');
                }

                // Success: Show in-page message (NO REDIRECT)
                $form.replaceWith(
                    '<div class="ead-org-registration-success">' +
                        '<h2>' + (EAD_OrgReg?.text_thank_you_title || 'Thank you!') + '</h2>' +
                        '<p>' + (EAD_OrgReg?.text_registration_submitted || 'Your organization registration has been submitted and is awaiting approval.') + '</p>' +
                    '</div>'
                );
            })
            .catch(error => {
                console.error(error);
                alert(error.message || (EAD_OrgReg?.text_registration_error || 'Registration failed.'));
            })
            .finally(() => {
                $submitButton.prop('disabled', false).text(EAD_OrgReg?.text_submit_button || 'Register Organization');
                if ($spinner.length) $spinner.hide();
            });
        });

        // Real-time error removal
        $form.on('input change', 'input, select, textarea', function() {
            if ($(this).val()) {
                removeFieldError($(this));
            }
        });
    }
});