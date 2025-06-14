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
});

// 2. Meta Box for Artist Link
add_action('add_meta_boxes', function () {
    add_meta_box('artwork_artist_meta', 'Artist', function ($post) {
        $value   = get_post_meta($post->ID, 'artwork_artist_id', true);
        $artists = get_posts(['post_type' => 'artist', 'numberposts' => -1]);

        echo '<select name="artwork_artist_id" class="widefat">';
        echo '<option value="">— Select Artist —</option>';
        foreach ($artists as $artist) {
            $selected = $value == $artist->ID ? 'selected' : '';
            echo '<option value="' . $artist->ID . '" ' . $selected . '>' . esc_html($artist->post_title) . '</option>';
        }
        echo '</select>';
    }, 'artwork', 'side');
});

add_action('save_post_artwork', function ($post_id) {
    update_post_meta($post_id, 'artwork_artist_id', absint($_POST['artwork_artist_id'] ?? 0));
});

// 3. Shortcode: [artwork_card id="123"]
add_shortcode('artwork_card', function ($atts) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post = get_post($atts['id']);
    if (!$post || $post->post_type !== 'artwork') return '';

    $artist_id   = get_post_meta($post->ID, 'artwork_artist_id', true);
    $artist_name = $artist_id ? get_the_title($artist_id) : '—';

    ob_start();
    ?>
    <div class="artwork-card border p-4 rounded shadow space-y-2">
        <?php if (has_post_thumbnail($post)) {
            echo get_the_post_thumbnail($post->ID, 'large', ['class' => 'rounded']);
        } ?>
        <h3 class="text-xl font-bold"><?php echo esc_html($post->post_title); ?></h3>
        <p class="text-sm text-gray-600 mb-2">Artist: <?php echo esc_html($artist_name); ?></p>
        <div class="text-gray-800 text-sm"><?php echo wpautop($post->post_content); ?></div>
    </div>
    <?php
    return ob_get_clean();
});
