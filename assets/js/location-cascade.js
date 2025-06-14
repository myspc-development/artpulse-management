jQuery(function($) {
  const countries = LocationData.countries;
  const states = LocationData.states;
  const cities = LocationData.cities;

  function populateSelect($el, items, placeholder) {
    $el.empty().append(`<option value="">${placeholder}</option>`);
    $.each(items, (key, val) => {
      $el.append(`<option value="${key}">${val}</option>`);
    });
    $el.trigger('change.select2');
  }

  const $country = $('#country'), $state = $('#state'), $city = $('#city');

  $country.select2().on('change', function () {
    const c = $(this).val();
    populateSelect($state, states[c] || {}, 'Select a state');
    $city.empty();
  });

  $state.select2().on('change', function () {
    const c = $country.val();
    const s = $(this).val();
    const cityList = (cities[c] && cities[c][s]) ? cities[c][s] : [];
    $city.empty().select2({
      data: cityList.map(x => ({ id: x, text: x })),
      placeholder: 'Select a city'
    });
  });

  populateSelect($country, countries, 'Select a country');
});
