<?php
use ArtPulse\Frontend\Shared\PortfolioWidgetRegistry;

get_header();

while (have_posts()) : the_post();
    $post_id   = get_the_ID();
    $post_type = get_post_type($post_id);
    $widgets   = PortfolioWidgetRegistry::public_widgets($post_id);

    $socials_raw = (string) get_post_meta($post_id, '_ap_socials', true);
    $socials = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $socials_raw)));

    $location_meta = get_post_meta($post_id, '_ap_location', true);
    $location = [
        'lat'     => 0.0,
        'lng'     => 0.0,
        'address' => (string) get_post_meta($post_id, '_ap_address', true),
    ];
    if (is_array($location_meta)) {
        $location['lat']     = isset($location_meta['lat']) ? (float) $location_meta['lat'] : 0.0;
        $location['lng']     = isset($location_meta['lng']) ? (float) $location_meta['lng'] : 0.0;
        $location['address'] = $location_meta['address'] ?? $location['address'];
    }

    $media = [
        'logo_id'     => (int) get_post_meta($post_id, '_ap_logo_id', true),
        'cover_id'    => (int) get_post_meta($post_id, '_ap_cover_id', true),
        'gallery_ids' => array_values(array_filter(array_map('intval', (array) get_post_meta($post_id, '_ap_gallery_ids', true)))),
    ];

    if (!$media['cover_id']) {
        $media['cover_id'] = (int) get_post_thumbnail_id($post_id);
    }

    $meta = [
        'tagline' => (string) get_post_meta($post_id, '_ap_tagline', true),
        'about'   => wp_kses_post(get_post_meta($post_id, '_ap_about', true)),
        'website' => esc_url(get_post_meta($post_id, '_ap_website', true)),
        'phone'   => sanitize_text_field(get_post_meta($post_id, '_ap_phone', true)),
        'email'   => sanitize_email(get_post_meta($post_id, '_ap_email', true)),
        'socials' => array_map('esc_url', $socials),
    ];

    $portfolio_context = [
        'post_id'   => $post_id,
        'post_type' => $post_type,
        'meta'      => $meta,
        'media'     => $media,
        'location'  => $location,
    ];

    foreach ($widgets as $key => $config) {
        if (empty($config['enabled'])) {
            continue;
        }

        $template = trailingslashit(ARTPULSE_PLUGIN_DIR) . 'templates/portfolio/widgets/' . sanitize_key($key) . '.php';
        if (!file_exists($template)) {
            continue;
        }

        $portfolio_widget       = $config;
        $portfolio_widget_key   = sanitize_key($key);
        $portfolio_widget_scope = $portfolio_context;

        include $template;
    }
endwhile;

get_footer();
