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

    $(document).on('click', '#mm-add-member', function(e){
        e.preventDefault();
        closeInline();
        const $tbody = $('.wp-list-table tbody');
        const cols = $tbody.find('tr:first td').length || 7;
        const form = $(
            '<tr class="manage-members-inline-form inline-add-row">' +
                '<td colspan="'+cols+'">' +
                    '<label>Name <input type="text" class="mm-name"></label> ' +
                    '<label>Email <input type="email" class="mm-email"></label> ' +
                    '<label>Level <select class="mm-level">' +
                        '<option value="basic">Basic</option>' +
                        '<option value="pro">Pro</option>' +
                        '<option value="org">Organization</option>' +
                    '</select></label> ' +
                    '<label>Expires <input type="date" class="mm-end"></label> ' +
                    '<label><input type="checkbox" class="mm-renew"> Auto-Renew</label> ' +
                    '<button class="button button-primary mm-add-save">Add</button>' +
                '</td>' +
            '</tr>'
        );
        $('#mm-add-row-container').html(form);
        form.find('.mm-add-save').on('click', function(ev){
            ev.preventDefault();
            const payload = {
                name: form.find('.mm-name').val(),
                email: form.find('.mm-email').val(),
                membership_level: form.find('.mm-level').val(),
                membership_end_date: form.find('.mm-end').val(),
                membership_auto_renew: form.find('.mm-renew').is(':checked')
            };
            $.ajax({
                url: settings.restUrl,
                method: 'POST',
                beforeSend: function(xhr){
                    xhr.setRequestHeader('X-WP-Nonce', settings.manageMembersNonce);
                },
                data: payload
            }).done(function(){
                location.reload();
            });
        });
    });

    $(document).on('click', '.manage-members-view', function(e){
        e.preventDefault();
        const userId = $(this).data('id');
        $.ajax({
            url: settings.restUrl + userId,
            method: 'GET',
            beforeSend: function(xhr){
                xhr.setRequestHeader('X-WP-Nonce', settings.manageMembersNonce);
            }
        }).done(function(data){
            alert('Name: ' + data.name + '\nEmail: ' + data.email + '\nLevel: ' + data.membership_level);
        });
    });

    $(document).on('click', '.manage-members-upgrade', function(e){
        e.preventDefault();
        const $row = $(this).closest('tr');
        const userId = $(this).data('id');
        const level = $row.find('.column-level').text().trim().toLowerCase();
        const map = { basic: 'pro', pro: 'org', org: 'org', expired: 'basic' };
        const newLevel = map[level] || level;
        $.ajax({
            url: settings.restUrl + userId,
            method: 'POST',
            beforeSend: function(xhr){
                xhr.setRequestHeader('X-WP-Nonce', settings.manageMembersNonce);
            },
            data: { membership_level: newLevel }
        }).done(function(){
            $row.find('.column-level').text(newLevel);
        });
    });
});
