<?php
namespace EAD\Shortcodes;

/**
 * Favorites List Shortcode with Unlike.
 *
 * Displays the user's liked events, artists, or organizations with unlike toggle.
 *
 * @package EventArtDirectory
 * @subpackage Shortcodes
 */
class FavoritesList {

    public static function register() {
        add_shortcode('ead_favorites', [self::class, 'render']);
    }

    public static function render($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your favorites.', 'event-art-directory') . '</p>';
        }

        $user_id = get_current_user_id();
        $liked = get_user_meta($user_id, 'ead_favorites', true);
        if (empty($liked) || !is_array($liked)) {
            return '<p>' . __('You have no favorites yet.', 'event-art-directory') . '</p>';
        }

        wp_enqueue_script('ead-like-button'); // Ensure JS is loaded

        $output = '<div class="ead-favorites-list">';
        foreach ($liked as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;
            $permalink = get_permalink($post);
            $title = get_the_title($post);
            $like_count = get_post_meta($post_id, '_ead_like_count', true);

            $output .= '<div class="ead-favorite-item">';
            $output .= '<a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a> ';
            $output .= '<button class="ead-like-button liked" data-post-id="' . esc_attr($post_id) . '">❤️ <span class="count">' . esc_html($like_count) . '</span></button>';
            $output .= '</div>';
        }
        $output .= '</div>';

        return $output;
    }
}
