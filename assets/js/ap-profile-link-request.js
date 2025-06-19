jQuery(function($){
    // Bulk select
    $('#ap-check-all').on('change', function(){
        $('.ap-row-check').prop('checked', this.checked);
    });

    // Bulk actions
    $('#ap-bulk-approve, #ap-bulk-deny').on('click', function(e){
        e.preventDefault();
        var ids = $('.ap-row-check:checked').map(function(){ return $(this).val(); }).get();
        if(ids.length === 0) return alert('Select at least one row!');
        var action_type = this.id === 'ap-bulk-approve' ? 'approve' : 'deny';
        apSendProfileLinkAjax(ids, action_type);
    });

    // Inline row action
    $('.ap-approve, .ap-deny').on('click', function(e){
        e.preventDefault();
        var id = $(this).data('id');
        var action_type = $(this).hasClass('ap-approve') ? 'approve' : 'deny';
        apSendProfileLinkAjax([id], action_type);
    });

    function apSendProfileLinkAjax(ids, action_type) {
        $.post(ApProfileLinkAjax.ajax_url, {
            action: 'ap_bulk_profile_link_action',
            ids: ids,
            action_type: action_type,
            nonce: ApProfileLinkAjax.nonce
        }, function(resp){
            if(resp.success && resp.data.results) {
                $.each(resp.data.results, function(id, status){
                    $('#status-'+id).text(status);
                });
            } else {
                alert('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error'));
            }
        });
    }
});
