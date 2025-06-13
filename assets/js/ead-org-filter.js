// assets/js/ead-org-filter.js

jQuery(document).ready(function($) {
    // Handle filter form submission
    $('#ead-org-filterbar').on('submit', function(e) {
        e.preventDefault();
        loadOrganizations(1); // Reset to page 1
    });

    // Handle AJAX pagination clicks
    $(document).on('click', '.ead-org-pagination .ajax-page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page') || 1;
        loadOrganizations(page);
    });

    // Main AJAX loader function using fetch & FormData
    function loadOrganizations(page) {
        const $form = $('#ead-org-filterbar');
        const $submitBtn = $form.find('button[type=\"submit\"]');
        const $directory = $('.ead-org-directory');
        const formData = new FormData($form[0]);
        formData.append('action', 'ead_filter_organizations');
        formData.append('nonce', (typeof EAD_VARS !== 'undefined' && EAD_VARS.orgFilterNonce) ? EAD_VARS.orgFilterNonce : '');
        formData.append('paged', page);

        // Show spinner overlay
        const spinner = $('<div class=\"ead-spinner-overlay\"><div class=\"ead-spinner\"></div></div>');
        $('body').append(spinner);

        $submitBtn.prop('disabled', true).text('Loading...');
        $directory.fadeTo(200, 0.3);

        fetch(EAD_VARS.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(response => {
            if (response.success && response.data && response.data.html) {
                $directory.fadeOut(200, function() {
                    $directory.replaceWith(response.data.html);
                    $('.ead-org-directory').hide().fadeIn(400);
                    $('html, body').animate({
                        scrollTop: $('.ead-org-directory').offset().top - 100
                    }, 400);
                });
            } else {
                $directory.html('<p>No organizations found.</p>').fadeTo(200, 1);
            }
        })
        .catch(error => {
            let errorMsg = 'Error loading organizations.';
            $directory.html('<p>' + errorMsg + '</p>').fadeTo(200, 1);
        })
        .finally(() => {
            $submitBtn.prop('disabled', false).text('Search');
            $('.ead-spinner-overlay').remove();
        });
    }

    // Reset to page 1 on filter change (optional but recommended)
    $('#ead-org-filterbar input, #ead-org-filterbar select').on('change', function() {
        $('input[name=\"paged\"]').val(1);
    });
});
