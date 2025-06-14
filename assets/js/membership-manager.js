jQuery(document).ready(function ($) {
    // Membership levels for dropdown
    var levels = ['basic', 'pro', 'org', 'expired'];

    // When Edit is clicked
    $('.artpulse-edit-member').on('click', function () {
        var $row = $(this).closest('tr');
        // Prevent double edit
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

        // Level dropdown
        var levelSelect = $('<select></select>');
        $.each(levels, function (i, lvl) {
            levelSelect.append($('<option></option>')
                .attr('value', lvl)
                .text(lvl.charAt(0).toUpperCase() + lvl.slice(1))
                .prop('selected', orig.level === lvl));
        });
        $levelCell.html(levelSelect);

        // Expiry date input
        var dateInput = $('<input type="date">').val(orig.expiry.substr(0, 10));
        $expiryCell.html(dateInput);

        // Auto-renew toggle
        var autoSelect = $('<select><option value="1">ON</option><option value="0">OFF</option></select>')
            .val(orig.auto);
        $autoCell.html(autoSelect);

        // Replace Edit button with Save/Cancel
        var $actions = $row.find('td:last');
        $actions.html(
            '<button class="button button-primary artpulse-save-member">Save</button>' +
            '<button class="button artpulse-cancel-member" style="margin-left:8px;">Cancel</button>'
        );
    });

    // Save handler
    $(document).on('click', '.artpulse-save-member', function () {
        var $row = $(this).closest('tr');
        var userId = $row.data('user-id');
        var newLevel = $row.find('[data-field="level"] select').val();
        var newExpiry = $row.find('[data-field="expiry"] input').val();
        var newAuto = $row.find('[data-field="auto_renew"] select').val();

        // TODO: AJAX to save changes (for now, just update table)
        $row.find('[data-field="level"]').text(newLevel.charAt(0).toUpperCase() + newLevel.slice(1));
        $row.find('[data-field="expiry"]').text(newExpiry);
        $row.find('[data-field="auto_renew"]').text(newAuto == 1 ? 'ON' : 'OFF');
        $row.removeClass('editing');
        $row.find('td:last').html('<button class="button button-primary artpulse-edit-member">Edit</button>');

        // --- Real AJAX example ---
        /*
        $.post(ajaxurl, {
            action: 'artpulse_update_membership',
            user_id: userId,
            level: newLevel,
            expiry: newExpiry,
            auto_renew: newAuto,
            _ajax_nonce: ART_PULSE_NONCE // Add this via wp_localize_script
        }, function (response) {
            // Handle success/fail here
        });
        */
    });

    // Cancel handler
    $(document).on('click', '.artpulse-cancel-member', function () {
        var $row = $(this).closest('tr');
        // Just reload the page for simplicity (or restore old text)
        location.reload();
    });
});
