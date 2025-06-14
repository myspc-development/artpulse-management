<?php
// Module: ArtPulse Artworks
// Description: Adds artist selection dropdown for artworks

// Add meta box to choose artist on Artwork edit screen
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

// Save selected artist
add_action('save_post_artwork', function ($post_id) {
    update_post_meta($post_id, 'artwork_artist_id', absint($_POST['artwork_artist_id'] ?? 0));
});
