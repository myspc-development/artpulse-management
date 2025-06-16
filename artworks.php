<?php
// Module: ArtPulse Artworks
// Description: Registers artwork CPT with artist linkage and display

// 1. Register Artwork CPT
add_action('init', function () {
    register_post_type('artwork', [
        'label'       => 'Artworks',
        'public'      => true,
        'has_archive' => true,
        'supports'    => ['title', 'editor', 'thumbnail'],
        'menu_icon'   => 'dashicons-format-image',
        'show_in_rest'=> true
    ]);

    register_taxonomy('artwork_style', 'artwork', [
        'label'        => 'Style',
        'public'       => true,
        'hierarchical' => true,
        'show_in_rest' => true,
    ]);
});

// 2. Meta Box for Artist Link
add_action('add_meta_boxes', function () {
    add_meta_box('artwork_artist_meta', 'Artist', function ($post) {
        $value   = ead_get_meta($post->ID, 'artwork_artist_id');
        $artists = get_posts(['post_type' => 'artist', 'numberposts' => -1]);

        echo '<select name="artwork_artist_id" class="widefat">';
        echo '<option value="">— Select Artist —</option>';
        foreach ($artists as $artist) {
            $selected = $value == $artist->ID ? 'selected' : '';
            echo '<option value="' . $artist->ID . '" ' . $selected . '>' . esc_html($artist->post_title) . '</option>';
        }
        echo '</select>';
    }, 'artwork', 'side');

    add_meta_box('artwork_info_meta', 'Artwork Info', function ($post) {
        $price        = ead_get_meta($post->ID, 'artwork_price');
        $medium       = ead_get_meta($post->ID, 'artwork_medium');
        $availability = ead_get_meta($post->ID, 'artwork_availability');
        ?>
        <p><label>Price:<br><input type="text" name="artwork_price" value="<?php echo esc_attr($price); ?>" class="widefat"></label></p>
        <p><label>Medium:<br><input type="text" name="artwork_medium" value="<?php echo esc_attr($medium); ?>" class="widefat"></label></p>
        <p><label>Availability:<br><input type="text" name="artwork_availability" value="<?php echo esc_attr($availability); ?>" class="widefat"></label></p>
        <?php
    }, 'artwork', 'normal', 'default');

    add_meta_box('artwork_gallery_meta', 'Gallery Images', function ($post) {
        $ids = ead_get_meta($post->ID, 'artwork_gallery_images');
        if (!is_array($ids)) {
            $ids = $ids ? array_map('intval', explode(',', $ids)) : [];
        }
        echo '<input type="hidden" id="ead_artwork_image_ids" name="ead_artwork_image_ids" value="' . esc_attr(implode(',', $ids)) . '" />';
        echo '<p><button type="button" class="button ead-upload-images">Select Images</button></p>';
        echo '<div id="ead-image-preview">';
        foreach ($ids as $id) {
            $thumb = wp_get_attachment_image_url($id, 'thumbnail');
            if ($thumb) {
                echo '<img src="' . esc_url($thumb) . '" style="width:75px;height:75px;object-fit:cover;margin:4px;" />';
            }
        }
        echo '</div>';
    }, 'artwork', 'normal');
});

add_action('save_post_artwork', function ($post_id) {
    update_post_meta($post_id, 'artwork_artist_id', absint($_POST['artwork_artist_id'] ?? 0));
    update_post_meta($post_id, 'artwork_price', sanitize_text_field($_POST['artwork_price'] ?? ''));
    update_post_meta($post_id, 'artwork_medium', sanitize_text_field($_POST['artwork_medium'] ?? ''));
    update_post_meta($post_id, 'artwork_availability', sanitize_text_field($_POST['artwork_availability'] ?? ''));

    if (isset($_POST['ead_artwork_image_ids'])) {
        $ids = array_filter(array_map('intval', explode(',', $_POST['ead_artwork_image_ids'])));
        update_post_meta($post_id, 'artwork_gallery_images', $ids);
        if ( ! has_post_thumbnail( $post_id ) && ! empty( $ids[0] ) ) {
            set_post_thumbnail( $post_id, $ids[0] );
        }
    }
});

// 3. Shortcode: [artwork_card id="123"]
add_shortcode('artwork_card', function ($atts) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post = get_post($atts['id']);
    if (!$post || $post->post_type !== 'artwork') return '';

    $artist_id   = ead_get_meta($post->ID, 'artwork_artist_id');
    $artist_name = $artist_id ? get_the_title($artist_id) : '—';
    $gallery_ids = ead_get_meta($post->ID, 'artwork_gallery_images');
    if (!is_array($gallery_ids)) {
        $gallery_ids = $gallery_ids ? array_map('intval', explode(',', $gallery_ids)) : [];
    }

    wp_enqueue_style('lightbox2', 'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css', [], '2.11.4');
    wp_enqueue_script('lightbox2', 'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js', [], '2.11.4', true);

    ob_start();
    ?>
    <div class="artwork-card border p-4 rounded shadow space-y-2">
        <?php if (has_post_thumbnail($post)) {
            echo get_the_post_thumbnail($post->ID, 'large', ['class' => 'rounded']);
        } ?>
        <h3 class="text-xl font-bold"><?php echo esc_html($post->post_title); ?></h3>
        <p class="text-sm text-gray-600 mb-2">Artist: <?php echo esc_html($artist_name); ?></p>
        <div class="text-gray-800 text-sm"><?php echo wpautop( (string) $post->post_content ); ?></div>
        <?php if ($gallery_ids) : ?>
            <div class="artwork-lightbox mt-2">
                <?php foreach ($gallery_ids as $gid) :
                    $url = wp_get_attachment_image_url($gid, 'large');
                    if ($url) : ?>
                        <a href="<?php echo esc_url($url); ?>" data-lightbox="artwork-<?php echo esc_attr($post->ID); ?>">
                            <?php echo wp_get_attachment_image($gid, 'thumbnail', false, ['class' => 'rounded']); ?>
                        </a>
                    <?php endif; endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

add_action('wp_enqueue_scripts', function () {
    wp_register_script(
        'artwork-gallery-filter',
        plugins_url('assets/js/artwork-gallery-filter.js', __FILE__),
        ['jquery'],
        EAD_MANAGEMENT_VERSION,
        true
    );
});

// AJAX handler for dynamic artwork filtering
add_action('wp_ajax_filter_artworks', 'ead_filter_artworks_ajax');
add_action('wp_ajax_nopriv_filter_artworks', 'ead_filter_artworks_ajax');

function ead_filter_artworks_ajax() {
    $artist = absint($_POST['artist'] ?? 0);
    $medium = sanitize_text_field($_POST['medium'] ?? '');
    $style  = sanitize_text_field($_POST['style'] ?? '');

    $meta = [];
    if ($artist) {
        $meta[] = [
            'key'   => 'artwork_artist_id',
            'value' => $artist,
        ];
    }
    if ($medium) {
        $meta[] = [
            'key'   => 'artwork_medium',
            'value' => $medium,
        ];
    }

    $args = [
        'post_type'      => 'artwork',
        'posts_per_page' => -1,
        'meta_query'     => $meta,
    ];
    if ($style) {
        $args['tax_query'] = [[
            'taxonomy' => 'artwork_style',
            'field'    => 'slug',
            'terms'    => $style,
        ]];
    }

    $query = new WP_Query($args);

    ob_start();
    while ($query->have_posts()) {
        $query->the_post();
        $price      = ead_get_meta(get_the_ID(), 'artwork_price');
        $artist_id  = ead_get_meta(get_the_ID(), 'artwork_artist_id');
        $medium_val = ead_get_meta(get_the_ID(), 'artwork_medium');
        $style_terms = wp_get_post_terms(get_the_ID(), 'artwork_style', ['fields' => 'slugs']);
        $style_slug = $style_terms ? $style_terms[0] : '';
        ?>
        <div class="artwork-gallery-card border p-2 rounded shadow text-center" data-artist-id="<?php echo esc_attr($artist_id); ?>" data-medium="<?php echo esc_attr($medium_val); ?>" data-style="<?php echo esc_attr($style_slug); ?>">
            <?php if (has_post_thumbnail()) {
                echo '<a href="' . get_permalink() . '">' . get_the_post_thumbnail(null, 'medium', ['class' => 'rounded mx-auto']) . '</a>';
            } ?>
            <h4 class="font-medium mt-2 text-sm"><?php echo get_the_title(); ?></h4>
            <?php if ($price) {
                echo '<p class="text-xs text-gray-600">Price: ' . esc_html($price) . '</p>';
            } ?>
        </div>
        <?php
    }
    wp_reset_postdata();

    wp_send_json_success(ob_get_clean());
}

add_shortcode('artwork_gallery', function ($atts) {
    $atts = shortcode_atts([
        'artist' => '',
        'ajax'   => false,
    ], $atts);

    $ajax_enabled = filter_var($atts['ajax'], FILTER_VALIDATE_BOOLEAN);

    global $wpdb;

    $args = [
        'post_type'      => 'artwork',
        'posts_per_page' => -1,
    ];
    if ($atts['artist']) {
        $args['meta_query'] = [[
            'key'   => 'artwork_artist_id',
            'value' => $atts['artist'],
        ]];
    }

    $query = new WP_Query($args);
    if (!$query->have_posts()) {
        return '<p>No artworks found.</p>';
    }

    // Collect dropdown data
    $mediums = $wpdb->get_col(
        "SELECT DISTINCT pm.meta_value
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = 'artwork_medium'
         AND p.post_type = 'artwork'
         AND pm.meta_value <> ''
         ORDER BY pm.meta_value ASC"
    );

    $artists = get_posts([
        'post_type'   => 'artist',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC',
    ]);

    $styles = get_terms([
        'taxonomy'   => 'artwork_style',
        'hide_empty' => false,
    ]);

    wp_enqueue_script('artwork-gallery-filter');

    wp_localize_script(
        'artwork-gallery-filter',
        'ARTWORK_GALLERY',
        [
            'ajaxEnabled' => $ajax_enabled,
            'ajaxurl'     => admin_url('admin-ajax.php'),
        ]
    );

    ob_start();
    ?>
    <div class="flex flex-wrap gap-4 items-center mb-4">
        <label class="text-sm font-medium">Artist:
            <select id="filter-artist" class="ml-2 border px-2 py-1 rounded">
                <option value="">All Artists</option>
                <?php foreach ($artists as $artist) : ?>
                    <option value="<?php echo esc_attr($artist->ID); ?>"><?php echo esc_html($artist->post_title); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="text-sm font-medium">Medium:
            <select id="filter-medium" class="ml-2 border px-2 py-1 rounded">
                <option value="">All Mediums</option>
                <?php foreach ($mediums as $medium) : ?>
                    <option value="<?php echo esc_attr($medium); ?>"><?php echo esc_html($medium); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="text-sm font-medium">Style:
            <select id="filter-style" class="ml-2 border px-2 py-1 rounded">
                <option value="">All Styles</option>
                <?php foreach ($styles as $style) : ?>
                    <option value="<?php echo esc_attr($style->slug); ?>"><?php echo esc_html($style->name); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div id="artwork-grid" data-ajax="<?php echo $ajax_enabled ? '1' : '0'; ?>" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
    <?php
    while ($query->have_posts()) {
        $query->the_post();
        $price      = ead_get_meta(get_the_ID(), 'artwork_price');
        $artist_id  = ead_get_meta(get_the_ID(), 'artwork_artist_id');
        $medium     = ead_get_meta(get_the_ID(), 'artwork_medium');
        $style_terms = wp_get_post_terms(get_the_ID(), 'artwork_style', ['fields' => 'slugs']);
        $style_slug = $style_terms ? $style_terms[0] : '';
        ?>
        <div class="artwork-gallery-card border p-2 rounded shadow text-center" data-artist-id="<?php echo esc_attr($artist_id); ?>" data-medium="<?php echo esc_attr($medium); ?>" data-style="<?php echo esc_attr($style_slug); ?>">
            <?php if (has_post_thumbnail()) {
                echo '<a href="' . get_permalink() . '">' . get_the_post_thumbnail(null, 'medium', ['class' => 'rounded mx-auto']) . '</a>';
            } ?>
            <h4 class="font-medium mt-2 text-sm"><?php echo get_the_title(); ?></h4>
            <?php if ($price) {
                echo '<p class="text-xs text-gray-600">Price: ' . esc_html($price) . '</p>';
            } ?>
        </div>
        <?php
    }
    ?>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
});
