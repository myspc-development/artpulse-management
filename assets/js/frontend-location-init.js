jQuery(function($){
    if (typeof eadAddress !== 'undefined') {
        // Trigger initial change events so dependent dropdowns load when values exist
        $('#ead_country').trigger('change');
        if ($('#ead_state').val()) {
            $('#ead_state').trigger('change');
        }
    }
});
