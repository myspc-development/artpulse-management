<?php
// Module: ArtPulse Artists
// Description: Registers artist CPT with metadata and frontend display

// 1. Register Artist CPT
add_action('init', function () {
    register_post_type('artist', [
        'label' => 'Artists',
        'public' => true,
        'has_archive' => true,
        'supports' => ['title', 'editor', 'thumbnail'],
        'menu_icon' => 'dashicons-id',
        'show_in_rest' => true
    ]);
});

// 2. Add Meta Box (website, bio links)
add_action('add_meta_boxes', function () {
    add_meta_box('artist_meta_box', 'Artist Details', 'render_artist_meta_box', 'artist', 'normal', 'default');
});

function render_artist_meta_box($post) {
    $website = get_post_meta($post->ID, 'artist_website', true);
    $social = get_post_meta($post->ID, 'artist_social', true);
    echo '<p><label>Website:<br><input type="url" name="artist_website" value="' . esc_attr($website) . '" class="widefat"></label></p>';
    echo '<p><label>Social Media / Linktree:<br><input type="url" name="artist_social" value="' . esc_attr($social) . '" class="widefat"></label></p>';
}

add_action('save_post', function ($post_id) {
    if (get_post_type($post_id) !== 'artist') return;
    update_post_meta($post_id, 'artist_website', sanitize_text_field($_POST['artist_website'] ?? ''));
    update_post_meta($post_id, 'artist_social', sanitize_text_field($_POST['artist_social'] ?? ''));
});

// 3. Shortcode [artist_card id="123"]
add_shortcode('artist_card', function ($atts) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post = get_post($atts['id']);
    if (!$post || $post->post_type !== 'artist') return '';

    $website = get_post_meta($post->ID, 'artist_website', true);
    $social = get_post_meta($post->ID, 'artist_social', true);

    ob_start();
    ?>
    <div class="artist-card border p-4 rounded shadow space-y-2">
        <?php if (has_post_thumbnail($post)) {
            echo get_the_post_thumbnail($post->ID, 'medium', ['class' => 'rounded']);
        } ?>
        <h3 class="text-xl font-bold"><?php echo esc_html($post->post_title); ?></h3>
        <div class="text-sm text-gray-700"><?php echo wpautop($post->post_content); ?></div>
        <div class="flex gap-3 mt-2">
            <?php if ($website): ?><a href="<?php echo esc_url($website); ?>" class="underline text-blue-600" target="_blank">Website</a><?php endif; ?>
            <?php if ($social): ?><a href="<?php echo esc_url($social); ?>" class="underline text-blue-600" target="_blank">Social</a><?php endif; ?>
        </div>

        <?php
        $artworks = get_posts([
            'post_type'      => 'artwork',
            'meta_key'       => 'artwork_artist_id',
            'meta_value'     => $post->ID,
            'posts_per_page' => -1
        ]);

        if ($artworks) {
            echo '<ul class="list-disc ml-5">';
            foreach ($artworks as $art) {
                echo '<li><a href="' . get_permalink($art) . '" class="text-blue-600 underline">' . esc_html($art->post_title) . '</a></li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
});
