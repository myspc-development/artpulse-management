jQuery(function($){
    $(document).on('click', '.ead-track-click', function(){
        var postId = $(this).data('post-id');
        if(!postId){ return; }
        $.post(eadAnalytics.ajaxUrl, {
            action: 'ead_track_click',
            nonce: eadAnalytics.nonce,
            post_id: postId
        });
    });
});
