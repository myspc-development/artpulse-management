// assets/js/ead-org-meta-box.js

jQuery(document).ready(function($) {
    let file_frame;

    // Generic media uploader for fields using the data-field attribute
    $('.ead-media-upload').on('click', function(e) {
        e.preventDefault();

        const field     = $(this).data('field');
        const input     = $('#ead_org_mb_' + field);
        const uploader  = wp.media({
            title: eadOrgMetaBox.title || 'Choose Image',
            button: { text: eadOrgMetaBox.button || 'Use this image' },
            multiple: false
        });

        uploader.on('select', function() {
            const attachment = uploader.state().get('selection').first().toJSON();
            input.val(attachment.id);
            input.siblings('img').attr('src', attachment.url).show();
        });

        uploader.open();
    });

    $('.ead-media-remove').on('click', function(e) {
        e.preventDefault();

        const field = $(this).data('field');
        const input = $('#ead_org_mb_' + field);
        input.val('');
        input.siblings('img').attr('src', '').hide();
    });

    // --- Logo Upload ---
    $('.ead-org-logo-upload').on('click', function(e) {
        e.preventDefault();

        // If the media frame already exists, reopen it.
        if (file_frame) {
            file_frame.open();
            return;
        }

        // Create the media frame.
        file_frame = wp.media({
            title: eadOrgMetaBox.title || 'Select Logo',
            button: {
                text: eadOrgMetaBox.button || 'Use this logo'
            },
            multiple: false  // Set to true to allow multiple files to be selected
        });

        // When an image is selected, run a callback.
        file_frame.on('select', function() {
            // We set multiple to false so only get one image.
            const attachment = file_frame.state().get('selection').first().toJSON();

            // Do something with attachment.id and/or attachment.url here
            $('#ead_org_logo_id').val(attachment.id);

            const preview = $('<img>').attr({
                src: attachment.url,
                alt: 'Logo Preview',
                style: 'max-width:100px; display:block; margin-bottom:8px;'
            });
            $('#ead_org_logo_id').prev('img').remove();
            $('#ead_org_logo_id').before(preview);
        });

        // Finally, open the modal
        file_frame.open();
    });

    // --- Banner Upload ---
    $('.ead-org-banner-upload').on('click', function(e) {
        e.preventDefault();

        // If the media frame already exists, reopen it.
        if (file_frame) {
            file_frame.open();
            return;
        }

        // Create the media frame.
        file_frame = wp.media({
            title: eadOrgMetaBox.title || 'Select Banner',
            button: {
                text: eadOrgMetaBox.button || 'Use this banner'
            },
            multiple: false  // Set to true to allow multiple files to be selected
        });

        // When an image is selected, run a callback.
        file_frame.on('select', function() {
            // We set multiple to false so only get one image.
            const attachment = file_frame.state().get('selection').first().toJSON();

            // Do something with attachment.id and/or attachment.url here
            $('#ead_org_banner_id').val(attachment.id);

            const preview = $('<img>').attr({
                src: attachment.url,
                alt: 'Banner Preview',
                style: 'max-width:200px; display:block; margin-bottom:8px;'
            });
            $('#ead_org_banner_id').prev('img').remove();
            $('#ead_org_banner_id').before(preview);
        });

        // Finally, open the modal
        file_frame.open();
    });

    // --- Address Select2 (Country, State, City) ---
    $('#ead_country').select2({
        placeholder: 'Select a country',
        width: '100%'
    });

    $('#ead_state').select2({
        placeholder: 'Select a state/province',
        width: '100%',
        ajax: {
            url: EAD_VARS.ajaxUrl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                const country = $('#ead_country').val();
                if (!country) {
                    console.warn('Country not selected; skipping states AJAX.');
                    return {};
                }
                return {
                    action: 'ead_load_states',
                    country_code: country,
                    security: EAD_VARS.statesNonce
                };
            },
            processResults: function(data) {
                return { results: data.results || [] };
            },
            error: function(xhr, status, error) {
                console.error('States AJAX error:', error);
            }
        }
    });

    $('#ead_country').on('change', function() {
        if ($(this).val()) {
            $('#ead_state').prop('disabled', false).val(null).trigger('change');
        } else {
            $('#ead_state').prop('disabled', true).val(null).trigger('change');
            $('#ead_city').prop('disabled', true).val(null).trigger('change');
        }
    });

    $('#ead_city').select2({
        placeholder: 'Start typing a city',
        width: '100%',
        minimumInputLength: 2,
        ajax: {
            url: EAD_VARS.ajaxUrl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                const country = $('#ead_country').val();
                const state = $('#ead_state').val();
                if (!country || !state) {
                    console.warn('Country or state not selected; skipping cities AJAX.');
                    return {};
                }
                return {
                    action: 'ead_search_cities',
                    country_code: country,
                    state_code: state,
                    term: params.term,
                    security: EAD_VARS.citiesNonce,
                    use_cache: true
                };
            },
            processResults: function(data) {
                return { results: data.results || [] };
            },
            error: function(xhr, status, error) {
                console.error('Cities AJAX error:', error);
            }
        }
    });

    $('#ead_state').on('change', function() {
        if ($(this).val()) {
            $('#ead_city').prop('disabled', false);
        } else {
            $('#ead_city').prop('disabled', true).val(null).trigger('change');
        }
    });
});