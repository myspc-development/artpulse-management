jQuery(document).ready(function ($) {
    const settings = window.MembershipManagerVars || {};
    if (!$('.artpulse-edit-member').length) {
        return;
    }

    var levels = ['basic', 'pro', 'org', 'expired'];

    $('.artpulse-edit-member').on('click', function () {
        var $row = $(this).closest('tr');
        if ($row.hasClass('editing')) return;

        $row.addClass('editing');
        var $levelCell = $row.find('[data-field="level"]');
        var $expiryCell = $row.find('[data-field="expiry"]');
        var $autoCell = $row.find('[data-field="auto_renew"]');
        var orig = {
            level: $levelCell.text().trim().toLowerCase(),
            expiry: $expiryCell.text().trim(),
            auto: $autoCell.text().trim() === 'ON' ? 1 : 0,
        };

        var levelSelect = $('<select></select>');
        $.each(levels, function (i, lvl) {
            levelSelect.append($('<option></option>')
                .attr('value', lvl)
                .text(lvl.charAt(0).toUpperCase() + lvl.slice(1))
                .prop('selected', orig.level === lvl));
        });
        $levelCell.html(levelSelect);

        var dateInput = $('<input type="date">').val(orig.expiry.substr(0, 10));
        $expiryCell.html(dateInput);

        var autoSelect = $('<select><option value="1">ON</option><option value="0">OFF</option></select>')
            .val(orig.auto);
        $autoCell.html(autoSelect);

        var $actions = $row.find('td:last');
        $actions.html(
            '<button class="button button-primary artpulse-save-member">Save</button>' +
            '<button class="button artpulse-cancel-member" style="margin-left:8px;">Cancel</button>'
        );
    });

    $(document).on('click', '.artpulse-save-member', function () {
        var $row = $(this).closest('tr');
        var userId = $row.data('user-id');
        var newLevel = $row.find('[data-field="level"] select').val();
        var newExpiry = $row.find('[data-field="expiry"] input').val();
        var newAuto = $row.find('[data-field="auto_renew"] select').val();

        $.ajax({
            url: settings.restUrl + userId,
            method: 'POST',
            beforeSend: function(xhr){
                xhr.setRequestHeader('X-WP-Nonce', settings.nonce);
            },
            data: {
                membership_level: newLevel,
                membership_end_date: newExpiry,
                membership_auto_renew: newAuto
            }
        }).done(function(res){
            if(res && res.success){
                $row.find('[data-field="level"]').text(newLevel.charAt(0).toUpperCase() + newLevel.slice(1));
                $row.find('[data-field="expiry"]').text(newExpiry);
                $row.find('[data-field="auto_renew"]').text(newAuto == 1 ? 'ON' : 'OFF');
                $row.removeClass('editing');
                $row.find('td:last').html('<button class="button button-primary artpulse-edit-member">Edit</button>');
                showMessage('Member updated!', 'success');
            }else{
                showMessage('Failed to update member.', 'error');
            }
        }).fail(function(){
            showMessage('Error contacting server.', 'error');
        });
    });

    $(document).on('click', '.artpulse-cancel-member', function () {
        location.reload();
    });

    function showMessage(msg, type){
        var $msg = $('#membership-message');
        if(!$msg.length) return;
        $msg.text(msg).removeClass('notice-success notice-error');
        if(type === 'success'){
            $msg.addClass('notice-success');
        }else{
            $msg.addClass('notice-error');
        }
        $msg.show();
    }
});
