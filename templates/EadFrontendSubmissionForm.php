<?php
// templates/ead-frontend-submission-form.php

// Enqueue form styles and scripts when this template is loaded.
if ( defined( 'EAD_PLUGIN_DIR_URL' ) ) {
    $plugin_url = EAD_PLUGIN_DIR_URL;
    $version    = defined( 'EAD_PLUGIN_VERSION' ) ? EAD_PLUGIN_VERSION : '1.0.0';

    wp_enqueue_style( 'ead-submit-event-css', $plugin_url . 'assets/css/ead-submit-event.css', [], $version );
    wp_enqueue_style( 'select2', $plugin_url . 'assets/select2/css/select2.min.css' );
    wp_enqueue_script( 'select2', $plugin_url . 'assets/select2/js/select2.min.js', [ 'jquery' ], null, true );
    wp_enqueue_script( 'ead-address', $plugin_url . 'assets/js/ead-address.js', [ 'jquery', 'select2' ], $version, true );
    wp_enqueue_script( 'ead-frontend-location', $plugin_url . 'assets/js/frontend-location-init.js', [ 'ead-address' ], $version, true );

    $settings               = get_option( 'artpulse_plugin_settings', [] );
    $gmaps_api_key          = isset( $settings['google_maps_api_key'] ) ? $settings['google_maps_api_key'] : '';
    $gmaps_places_enabled   = ! empty( $settings['enable_google_places_api'] );
    $geonames_enabled       = ! empty( $settings['enable_geonames_api'] );

    wp_localize_script(
        'ead-address',
        'eadAddress',
        [
            'countriesJson'     => $plugin_url . 'data/countries.json',
            'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
            'statesNonce'       => wp_create_nonce( 'ead_load_states' ),
            'citiesNonce'       => wp_create_nonce( 'ead_search_cities' ),
            'gmapsApiKey'       => $gmaps_api_key,
            'gmapsPlacesEnabled' => $gmaps_places_enabled,
            'geonamesEnabled'   => $geonames_enabled,
        ]
    );

    if ( $gmaps_places_enabled && $gmaps_api_key ) {
        wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $gmaps_api_key . '&libraries=places', [], null, true );
    }
}

// Generate the security nonce and get event types.
$nonce = wp_create_nonce('ead_frontend_submit');
$types = get_terms(['taxonomy' => 'ead_event_type', 'hide_empty' => false]);
?>
<div class="ead-event-form">
  <form id="ead-event-submission-form" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">

    <!-- Event Details -->
    <fieldset class="ead-card">
      <legend>Event Details</legend>
      <div class="ead-form-group ead-two-col">
        <div>
          <label for="ead-title">Title <span class="ead-error-message">*</span></label>
          <input type="text" name="event_title" id="ead-title" required maxlength="120">
        </div>
        <div>
          <label for="ead-type">Type <span class="ead-error-message">*</span></label>
          <select name="event_type" id="ead-type" required>
            <option value="">Select Type</option>
            <?php foreach ($types as $type) : ?>
              <option value="<?php echo esc_attr($type->slug); ?>"><?php echo esc_html($type->name); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </fieldset>

    <!-- Organizer Details -->
    <fieldset class="ead-card">
      <legend>Organizer Details</legend>
      <div class="ead-form-group ead-two-col">
        <div>
          <label for="ead-organizer-name">Organizer Name <span class="ead-error-message">*</span></label>
          <input type="text" name="organizer_name" id="ead-organizer-name" required maxlength="100">
        </div>
        <div>
          <label for="ead-organizer-email">Organizer Email <span class="ead-error-message">*</span></label>
          <input type="email" name="organizer_email" id="ead-organizer-email" required maxlength="100">
        </div>
      </div>
    </fieldset>

    <!-- Date & Time -->
    <fieldset class="ead-card">
      <legend>Date & Time</legend>
      <div class="ead-form-group ead-two-col">
        <div>
          <label for="ead-start-date">Start Date <span class="ead-error-message">*</span></label>
          <input type="date" name="event_start_date" id="ead-start-date" required>
        </div>
        <div>
          <label for="ead-end-date">End Date <span class="ead-error-message">*</span></label>
          <input type="date" name="event_end_date" id="ead-end-date" required>
        </div>
      </div>
    </fieldset>

    <!-- Description -->
    <fieldset class="ead-card">
      <legend>Description</legend>
      <div class="ead-form-group">
        <label for="ead-description">Description</label>
        <textarea name="event_description" id="ead-description" rows="4"></textarea>
      </div>
    </fieldset>

    <!-- Gallery Images -->
    <fieldset class="ead-card">
      <legend>Gallery Images</legend>
      <div class="ead-form-group">
        <label for="ead-gallery">Images (jpg/png/gif)</label>
        <!-- Drag and drop area -->
        <div id="ead-gallery-dropzone" class="ead-dropzone">
          <span>Drag &amp; drop images here or click to select</span>
          <input type="file" name="event_gallery[]" id="ead-gallery" multiple accept="image/*" class="ead-hidden-file-input">
        </div>
        <div id="ead-image-preview"></div>
      </div>
    </fieldset>

    <!-- Location -->
    <fieldset class="ead-card">
      <legend>Location</legend>
      <div class="ead-form-group">
        <label for="ead_country">Country</label>
        <select id="ead_country" name="event_country" required></select>
      </div>
      <div class="ead-form-group">
        <label for="ead_state">State</label>
        <select id="ead_state" name="event_state" disabled required></select>
      </div>
      <div class="ead-form-group">
        <label for="ead_city">City</label>
        <select id="ead_city" name="event_city" disabled required></select>
      </div>
      <div class="ead-form-group">
        <label for="ead-map">Click on the map to select location:</label>
        <div id="ead-map" class="ead-map-display"></div>
        <input type="hidden" name="lat" id="ead-lat">
        <input type="hidden" name="lng" id="ead-lng">
      </div>
    </fieldset>

    <div class="ead-card ead-form-center">
      <button type="submit" class="ead-btn-primary">Submit Event</button>
      <div id="ead-event-submission-result"></div>
    </div>
  </form>
</div>

<script>
document.getElementById('ead-event-submission-form').addEventListener('submit', function(e){
    var form = this;
    var errors = [];
    // Basic JS validation before AJAX submission
    if (!form.event_title.value.trim()) errors.push("Event title is required.");
    if (!form.event_type.value.trim()) errors.push("Event type is required.");
    if (!form.event_start_date.value.trim()) errors.push("Start date is required.");
    if (!form.event_end_date.value.trim()) errors.push("End date is required.");

    var email = form.organizer_email.value.trim();
    if (!form.organizer_name.value.trim()) errors.push("Organizer name is required.");
    if (!email) {
        errors.push("Organizer email is required.");
    } else if (!/^[^@]+@[^@]+\.[^@]+$/.test(email)) {
        errors.push("Organizer email is not valid.");
    }

    // Optionally: check dates (must be in correct order)
    var start = form.event_start_date.value.trim();
    var end = form.event_end_date.value.trim();
    if (start && end && (new Date(start) > new Date(end))) {
        errors.push("Start date cannot be after end date.");
    }

    // Optionally: image type checks (basic, not exhaustive)
    var images = form['event_gallery[]'] || form.event_gallery;
    if (images && images.files.length) {
        for (let file of images.files) {
            if (!file.type.match(/^image\/(jpeg|png|gif)$/)) {
                errors.push("Gallery images must be JPG, PNG, or GIF.");
                break;
            }
        }
    }

    // Show errors and block submit if any
    if (errors.length) {
        e.preventDefault();
        document.getElementById('ead-event-submission-result').innerHTML = "<div class='ead-error-message'>" + errors.join('<br>') + "</div>";
        return false;
    }
    // Otherwise, let AJAX submission proceed (your AJAX script will handle success/failure display)
});
</script>
