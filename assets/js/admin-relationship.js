jQuery(document).ready(function($) {
    $('select.ap-related-posts').each(function() {
        var $select = $(this);
        var postType = $select.data('post-type') || 'post';

        $select.select2({
            ajax: {
                url: apAdminRelationship.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term,
                        post_type: postType,
                        action: 'ap_search_posts',
                        nonce: apAdminRelationship.nonce
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            },
            placeholder: 'Search posts',
            minimumInputLength: 1,
            allowClear: true,
            width: 'resolve'
        });
    });
});
