(function(global, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory(require('jquery'));
    } else {
        global.EadAddressCommon = factory(global.jQuery);
    }
}(this, function($) {
    function initStateCityDropdowns(opts) {
        const countrySel = opts.countrySelector || '#ead_country';
        const stateSel   = opts.stateSelector || '#ead_state';
        const citySel    = opts.citySelector || '#ead_city';
        const stateOpts  = opts.stateSelect2 || {};
        const cityOpts   = opts.citySelect2 || {};

        $(stateSel).select2($.extend(true, {
            placeholder: 'Select a state/province',
            width: '100%',
            ajax: {
                url: opts.ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    const country = $(countrySel).val();
                    if (!country) {
                        return {};
                    }
                    return {
                        action: 'ead_load_states',
                        country_code: country,
                        term: params.term,
                        security: opts.statesNonce
                    };
                },
                processResults: function(data) {
                    return { results: data.results || data.data || [] };
                },
                error: function(xhr, status, error) {
                    console.error('States AJAX error:', error);
                }
            }
        }, stateOpts));

        $(citySel).select2($.extend(true, {
            placeholder: 'Start typing a city',
            width: '100%',
            minimumInputLength: 2,
            ajax: {
                url: opts.ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    const country = $(countrySel).val();
                    const state   = $(stateSel).val();
                    if (!country || !state) {
                        return {};
                    }
                    return {
                        action: 'ead_search_cities',
                        country_code: country,
                        state_code: state,
                        term: params.term,
                        security: opts.citiesNonce,
                        use_cache: true
                    };
                },
                processResults: function(data) {
                    return { results: data.results || data.data || [] };
                },
                error: function(xhr, status, error) {
                    console.error('Cities AJAX error:', error);
                }
            }
        }, cityOpts));

        $(countrySel).on('change', function() {
            if ($(this).val()) {
                $(stateSel).prop('disabled', false).val(null).trigger('change');
            } else {
                $(stateSel).prop('disabled', true).val(null).trigger('change');
                $(citySel).prop('disabled', true).val(null).trigger('change');
            }
        });

        $(stateSel).on('change', function() {
            if ($(this).val()) {
                $(citySel).prop('disabled', false);
            } else {
                $(citySel).prop('disabled', true).val(null).trigger('change');
            }
        });
    }

    return { initStateCityDropdowns };
}));
