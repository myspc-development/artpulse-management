// assets/js/ead-test-api.js

jQuery(document).ready(function($) {
    $('#ead-test-api').on('click', function(e) {
        e.preventDefault();
        $('#ead-test-api-result').html('<em>Testing...</em>');

        const formData = new FormData();
        formData.append('action', 'ead_test_geonames_api');
        formData.append('security', (typeof EAD_VARS !== 'undefined' && EAD_VARS.testApiNonce) ? EAD_VARS.testApiNonce : '');

        fetch(EAD_VARS.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                $('#ead-test-api-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
            } else {
                $('#ead-test-api-result').html('<div class="notice notice-error"><p>' + (response.data ? response.data.message : 'Test failed.') + '</p></div>');
            }
        })
        .catch(() => {
            $('#ead-test-api-result').html('<div class="notice notice-error"><p>AJAX request failed.</p></div>');
        });
    });
});
