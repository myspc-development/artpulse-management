// assets/js/ead-like-button.js
jQuery(document).ready(function ($) {
    $('.ead-like-button').each(function () {
        var $btn = $(this);
        var liked = $btn.hasClass('liked');
        $btn.attr('data-tooltip', liked ? 'Unlike' : 'Like');
        $btn.attr('aria-label', liked ? 'Unlike this item' : 'Like this item');
    });

    $('.ead-like-button').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var nonce = (typeof EAD_VARS !== 'undefined' && EAD_VARS.likeNonce) ? EAD_VARS.likeNonce : '';

        // Use the global submitRequest for AJAX
        submitRequest('like', { post_id: postId, _wpnonce: nonce });
    });
});
