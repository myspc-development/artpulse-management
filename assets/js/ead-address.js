jQuery(document).ready(function($) {
    // Utility to allow custom text entry for Select2
    function allowCustomEntry(selectId) {
        $(selectId).on('select2:close', function() {
            let enteredValue = $(this).data('select2').dropdown.$search ?
                $(this).data('select2').dropdown.$search.val() : '';
            if (enteredValue && !$(this).find("option[value='" + enteredValue + "']").length) {
                let newOption = new Option(enteredValue, enteredValue, true, true);
                $(this).append(newOption).trigger('change');
            }
        });
    }

    // --- COUNTRY: Local JSON via Select2 ---
    $('#ead_country').select2({
        ajax: {
            url: eadAddress.countriesJson,
            dataType: 'json',
            delay: 150,
            processResults: function(data, params) {
                let results = [];
                params.term = params.term || '';
                data.forEach(function(obj) {
                    if (
                        obj.name.toLowerCase().indexOf(params.term.toLowerCase()) !== -1 ||
                        obj.code.toLowerCase().indexOf(params.term.toLowerCase()) !== -1
                    ) {
                        results.push({ id: obj.code, text: obj.name });
                    }
                });
                return { results: results };
            }
        },
        placeholder: 'Select or type a country',
        allowClear: true,
        minimumInputLength: 1,
        tags: true,
        width: '100%'
    });
    allowCustomEntry('#ead_country');

    // --- Handle state/city enable/disable logic ---
    $('#ead_country').on('change', function() {
        let selectedCountry = $(this).val();
        $('#ead_state').val(null).trigger('change');
        $('#ead_city').val(null).trigger('change');
        $('#ead_state').prop('disabled', !selectedCountry);
        $('#ead_city').prop('disabled', true);
    });

    // --- STATE: AJAX via backend ---
    $('#ead_state').select2({
        ajax: {
            url: eadAddress.ajaxUrl,
            dataType: 'json',
            delay: 150,
            data: function(params) {
                return {
                    action: 'ead_load_states',
                    country_code: $('#ead_country').val(),
                    term: params.term || '',
                    security: eadAddress.statesNonce
                };
            },
            processResults: function(data) {
                let items = [];
                if (data.success && Array.isArray(data.data)) {
                    items = data.data;
                } else if (Array.isArray(data)) {
                    items = data;
                } else if (Array.isArray(data.results)) {
                    items = data.results;
                }
                return {
                    results: items.map(function(item) {
                        return { id: item.code || item.name, text: item.name };
                    })
                };
            },
            cache: true
        },
        placeholder: 'Select or type a state/province',
        allowClear: true,
        minimumInputLength: 1,
        tags: true,
        width: '100%'
    });
    allowCustomEntry('#ead_state');

    $('#ead_state').on('change', function() {
        let selectedState = $(this).val();
        $('#ead_city').val(null).trigger('change');
        $('#ead_city').prop('disabled', !selectedState);
    });

    // --- CITY: AJAX via backend ---
    $('#ead_city').select2({
        ajax: {
            url: eadAddress.ajaxUrl,
            dataType: 'json',
            delay: 150,
            data: function(params) {
                return {
                    action: 'ead_search_cities',
                    country_code: $('#ead_country').val(),
                    state_code: $('#ead_state').val(),
                    term: params.term || '',
                    security: eadAddress.citiesNonce,
                    use_cache: true
                };
            },
            processResults: function(data) {
                let items = [];
                if (data.success && Array.isArray(data.data)) {
                    items = data.data;
                } else if (Array.isArray(data)) {
                    items = data;
                } else if (Array.isArray(data.results)) {
                    items = data.results;
                }
                return {
                    results: items.map(function(item) {
                        return { id: item.name, text: item.name };
                    })
                };
            },
            cache: true
        },
        placeholder: 'Select or type a city',
        allowClear: true,
        minimumInputLength: 1,
        tags: true,
        width: '100%'
    });
    allowCustomEntry('#ead_city');

    // --- Initial State On Load (editing forms) ---
    (function() {
        if (!$('#ead_country').val()) {
            $('#ead_state').prop('disabled', true);
            $('#ead_city').prop('disabled', true);
        } else if (!$('#ead_state').val()) {
            $('#ead_city').prop('disabled', true);
        }
        // ARIA for accessibility
        $('#ead_country, #ead_state, #ead_city').attr('aria-required', 'true');
    })();

    // --- GOOGLE PLACES AUTOCOMPLETE & MAP PICKER ---
    if (eadAddress.gmapsPlacesEnabled && typeof google === 'object' && typeof google.maps === 'object') {
        let autocomplete, map, marker;

        function initMapPicker() {
            $("#ead-map").show();
            let lat = parseFloat($('#ead_latitude').val()) || 0;
            let lng = parseFloat($('#ead_longitude').val()) || 0;
            let zoom = (lat !== 0 && lng !== 0) ? 15 : 2;

            map = new google.maps.Map(document.getElementById('ead-map'), {
                center: { lat: lat, lng: lng },
                zoom: zoom
            });

            marker = new google.maps.Marker({
                position: { lat: lat, lng: lng },
                map: map,
                draggable: true
            });

            google.maps.event.addListener(marker, 'dragend', function(event) {
                $('#ead_latitude').val(event.latLng.lat());
                $('#ead_longitude').val(event.latLng.lng());
            });

            map.addListener('click', function(e) {
                marker.setPosition(e.latLng);
                $('#ead_latitude').val(e.latLng.lat());
                $('#ead_longitude').val(e.latLng.lng());
            });
        }

        function initAutocomplete() {
            autocomplete = new google.maps.places.Autocomplete(
                document.getElementById('ead_street'),
                { types: ['address'] }
            );
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (place.geometry && place.geometry.location) {
                    $('#ead_latitude').val(place.geometry.location.lat());
                    $('#ead_longitude').val(place.geometry.location.lng());
                    if (map && marker) {
                        map.setCenter(place.geometry.location);
                        map.setZoom(16);
                        marker.setPosition(place.geometry.location);
                    }
                }
            });
        }

        // Geolocate button
        $('#ead-geolocate-btn').on('click', function(e) {
            e.preventDefault();
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    let lat = position.coords.latitude;
                    let lng = position.coords.longitude;
                    $('#ead_latitude').val(lat);
                    $('#ead_longitude').val(lng);
                    if (map && marker) {
                        let latlng = new google.maps.LatLng(lat, lng);
                        map.setCenter(latlng);
                        map.setZoom(16);
                        marker.setPosition(latlng);
                    }
                }, function() {
                    alert('Unable to retrieve your location.');
                });
            } else {
                alert('Geolocation not supported by your browser.');
            }
        });

        // Initialize autocomplete and map after script is ready
        initAutocomplete();
        initMapPicker();
    }
});
