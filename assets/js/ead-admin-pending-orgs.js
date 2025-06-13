jQuery(document).ready(function($){
    // Approve organization (AJAX)
    $('.ead-org-approve-btn').on('click', function(e){
        e.preventDefault();
        if (!confirm('Approve this organization?')) return;
        var $btn = $(this),
            orgId = $btn.data('org-id');
        $btn.prop('disabled', true).text('Approving...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ead_ajax_approve_org',
                org_id: orgId,
                security: EAD_PendingOrgs.nonce
            },
            success: function(resp){
                if (resp.success) {
                    $btn.closest('tr').fadeOut('slow');
                } else {
                    alert(resp.data || 'Failed to approve.');
                }
            },
            error: function(){
                alert('Server error.');
            },
            complete: function(){
                $btn.prop('disabled', false).text('Approve');
            }
        });
    });

    // Reject organization (AJAX)
    $('.ead-org-reject-btn').on('click', function(e){
        e.preventDefault();
        if (!confirm('Reject (move to trash) this organization?')) return;
        var $btn = $(this),
            orgId = $btn.data('org-id');
        $btn.prop('disabled', true).text('Rejecting...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ead_ajax_reject_org',
                org_id: orgId,
                security: EAD_PendingOrgs.nonce
            },
            success: function(resp){
                if (resp.success) {
                    $btn.closest('tr').fadeOut('slow');
                } else {
                    alert(resp.data || 'Failed to reject.');
                }
            },
            error: function(){
                alert('Server error.');
            },
            complete: function(){
                $btn.prop('disabled', false).text('Reject');
            }
        });
    });
});
