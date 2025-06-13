jQuery(document).ready(function($) {
    // Handler: REST API-based featured update
    $('.ead-update-featured-btn').on('click', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const postId = $btn.data('post-id');
        const featured = $btn.data('featured');
        const expiration = $btn.data('expiration');
        const priority = $btn.data('priority');
        const nonce = (typeof EAD_VARS !== 'undefined' && EAD_VARS.featuredNonce) ? EAD_VARS.featuredNonce : '';

        $btn.prop('disabled', true).text('Updating...');

        $.ajax({
            url: eadFeatured.restUrl + '/organization/' + postId + '/featured',
            method: 'POST',
            data: {
                featured: featured,
                expiration: expiration,
                priority: priority,
                _wpnonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Featured status updated!');
                } else {
                    alert('Failed to update: ' + response.message);
                }
            },
            error: function(xhr) {
                let errorMsg = 'An unexpected error occurred.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Update Featured');
            }
        });
    });

    // Handler: AJAX action-based featured update
    $('.ead-update-featured-ajax').on('click', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const postId = $btn.data('post-id');
        const featured = $btn.data('featured');
        const expiration = $btn.data('expiration');
        const priority = $btn.data('priority');

        $btn.prop('disabled', true).text('Updating...');

        $.ajax({
            url: eadFeatured.ajaxUrl,
            method: 'POST',
            data: {
                action: 'ead_update_featured_status',
                nonce: eadFeatured.nonce,
                post_id: postId,
                featured: featured,
                expiration: expiration,
                priority: priority
            },
            success: function(response) {
                if (response.success) {
                    alert('Featured status updated!');
                } else {
                    alert('Failed to update: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            },
            error: function(xhr) {
                let errorMsg = 'An unexpected error occurred.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMsg = xhr.responseJSON.data.message;
                }
                alert(errorMsg);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Update Featured');
            }
        });
    });
});
