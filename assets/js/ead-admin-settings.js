jQuery(document).ready(function($) {
    $('#ead-test-api').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $result = $('#ead-test-api-result');

        // Prevent multiple clicks
        if ($button.prop('disabled')) {
            return;
        }

        $button.prop('disabled', true).text('Testing...');
        $result.empty(); // Clear previous results

        $.ajax({
            url: eadAdminSettings.ajaxUrl,
            method: 'POST',
            dataType: 'json', // Expect JSON response
            data: {
                action: 'ead_test_geonames_api',
                security: eadAdminSettings.testGeoNamesNonce
            },
            success: function(response) {
                if (response && response.success) {
                    $result.html('<span style="color:green;">' + response.data.message + '</span>');
                } else {
                    // Handle cases where response is missing or invalid
                    var errorMessage = 'An error occurred while testing the API.';
                    if (response && response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    $result.html('<span style="color:red;">' + errorMessage + '</span>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Detailed error handling
                console.error("GeoNames API Test Error:", jqXHR, textStatus, errorThrown);
                var errorMessage = 'An error occurred while testing the API.';
                if (textStatus) {
                    errorMessage += ' Status: ' + textStatus;
                }
                if (errorThrown) {
                    errorMessage += ' Error: ' + errorThrown;
                }
                $result.html('<span style="color:red;">' + errorMessage + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test GeoNames API');
            }
        });
    });
});