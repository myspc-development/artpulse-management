// assets/js/ead-reviews.js

jQuery(document).ready(function($) {
    function moderateReview(reviewId, action) {
        const nonce = (typeof EAD_VARS !== 'undefined' && EAD_VARS.reviewsNonce) ? EAD_VARS.reviewsNonce : '';
        submitRequest(
            `moderate-review/${reviewId}`,
            { action: action, _wpnonce: nonce }
        );
        setTimeout(() => location.reload(), 1000);
    }

    $('.ead-approve-review').on('click', function(e) {
        e.preventDefault();
        const reviewId = $(this).data('review-id');
        if (confirm('Are you sure you want to approve this review?')) {
            moderateReview(reviewId, 'approve');
        }
    });

    $('.ead-delete-review').on('click', function(e) {
        e.preventDefault();
        const reviewId = $(this).data('review-id');
        if (confirm('Are you sure you want to delete this review?')) {
            moderateReview(reviewId, 'delete');
        }
    });
});
