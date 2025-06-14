jQuery(document).ready(function($){
    $('#ead-admin-add-event-form').on('submit', function(e){
        e.preventDefault();

        var form = this;
        var formData = new FormData(form);

        // Add the security nonce (if not already present)
        if (typeof EADAdminEvent !== 'undefined') {
            formData.append('security', EADAdminEvent.nonce);
        }
        formData.append('action', 'ead_admin_add_event');

        $('#ead-admin-event-message').html(EADAdminEvent.processing);

        $.ajax({
            url: EADAdminEvent.ajaxUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(resp){
                if (resp.success) {
                    $('#ead-admin-event-message').html('<div class="notice notice-success">' + EADAdminEvent.success + '</div>');
                    form.reset();
                } else {
                    $('#ead-admin-event-message').html('<div class="notice notice-error">' + (resp.data && resp.data.message ? resp.data.message : EADAdminEvent.error) + '</div>');
                }
            },
            error: function(){
                $('#ead-admin-event-message').html('<div class="notice notice-error">' + EADAdminEvent.error + '</div>');
            }
        });
    });
});
