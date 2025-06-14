jQuery(function($){
    const settings = window.manageMembersData || {};

    function closeInline(){
        $('.manage-members-inline-form').remove();
    }

    $(document).on('click', '.manage-members-inline', function(e){
        e.preventDefault();
        closeInline();
        const $row = $(this).closest('tr');
        const userId = $(this).data('id');
        const cols = $row.children('td').length;
        const level = $row.find('.column-level').text().trim();
        const end = $row.find('.column-expires').text().trim();
        const renew = $row.find('.column-renew').text().trim().toLowerCase()==='yes';

        const form = $(
            '<tr class="manage-members-inline-form inline-edit-row">' +
                '<td colspan="'+cols+'">' +
                    '<label>Level <select class="mm-level">' +
                        '<option value="basic">Basic</option>' +
                        '<option value="pro">Pro</option>' +
                        '<option value="org">Organization</option>' +
                        '<option value="expired">Expired</option>' +
                    '</select></label> ' +
                    '<label>Expires <input type="date" class="mm-end"></label> ' +
                    '<label><input type="checkbox" class="mm-renew"> Auto-Renew</label> ' +
                    '<button class="button button-primary mm-save">Save</button>' +
                '</td>' +
            '</tr>'
        );
        form.insertAfter($row);
        form.find('.mm-level').val(level.toLowerCase());
        form.find('.mm-end').val(end);
        form.find('.mm-renew').prop('checked', renew);

        form.find('.mm-save').on('click', function(ev){
            ev.preventDefault();
            const payload = {
                membership_level: form.find('.mm-level').val(),
                membership_end_date: form.find('.mm-end').val(),
                membership_auto_renew: form.find('.mm-renew').is(':checked')
            };
            $.ajax({
                url: settings.restUrl + userId,
                method: 'POST',
                beforeSend: function(xhr){
                    xhr.setRequestHeader('X-WP-Nonce', settings.manageMembersNonce);
                },
                data: payload
            }).done(function(){
                $row.find('.column-level').text(payload.membership_level);
                $row.find('.column-expires').text(payload.membership_end_date);
                $row.find('.column-renew').text(payload.membership_auto_renew ? 'Yes' : 'No');
                closeInline();
            });
        });
    });
});
