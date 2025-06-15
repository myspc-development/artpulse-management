<?php
namespace EAD\Reviews;

/**
 * Class Reviews
 *
 * Handles the user reviews and ratings functionality.
 *
 * @package EAD\Reviews
 */
class Reviews {

    /**
     * Initialize the Reviews system.
     */
    public static function init() {
        add_shortcode('ead_reviews_form', [self::class, 'render_review_form']);
        add_shortcode('ead_reviews_table', [self::class, 'render_reviews_table']); // NEW: shortcode for table
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * Enqueue frontend JS and CSS assets.
     */
    public static function enqueue_assets() {
        $plugin_url = EAD_PLUGIN_DIR_URL;
        wp_enqueue_style('ead-reviews', $plugin_url . 'assets/css/ead-reviews.css', [], '1.0.0');
        wp_enqueue_script('ead-reviews', $plugin_url . 'assets/js/ead-reviews.js', ['jquery'], '1.0.0', true);

        wp_localize_script('ead-reviews', 'eadReviewsApi', [
            'restUrl' => esc_url_raw(rest_url('artpulse/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Render the review submission form via shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function render_review_form($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to leave a review.', 'artpulse-management') . '</p>';
        }

        ob_start();
        ?>
        <div id="ead-reviews-form" class="ead-reviews-form">
            <h3><?php esc_html_e('Leave a Review', 'artpulse-management'); ?></h3>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('ead_submit_review', 'ead_review_nonce'); ?>
                <div class="form-group">
                    <label for="ead-review-rating"><?php esc_html_e('Rating (1-5)', 'artpulse-management'); ?></label>
                    <input type="number" id="ead-review-rating" name="ead_review_rating" min="1" max="5" required>
                </div>
                <div class="form-group">
                    <label for="ead-review-text"><?php esc_html_e('Review', 'artpulse-management'); ?></label>
                    <textarea id="ead-review-text" name="ead_review_text" rows="5" required></textarea>
                </div>
                <button type="submit" class="ead-submit-review">Submit Review</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a reviews table with moderation links (admin only).
     *
     * @return string
     */
    public static function render_reviews_table() {
        if (!current_user_can('edit_others_posts')) {
            return '<p>' . esc_html__('You do not have permission to view this table.', 'artpulse-management') . '</p>';
        }

        // Fetch reviews
        $args = [
            'post_type'      => 'ead_review', // Adjust this CPT slug if different
            'post_status'    => ['publish', 'pending', 'draft'],
            'posts_per_page' => -1,
        ];
        $reviews = get_posts($args);

        ob_start();

        if (empty($reviews)) {
            echo '<p>' . esc_html__('No reviews found.', 'artpulse-management') . '</p>';
            return ob_get_clean();
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Reviewer', 'artpulse-management') . '</th>';
        echo '<th>' . esc_html__('Rating', 'artpulse-management') . '</th>';
        echo '<th>' . esc_html__('Review', 'artpulse-management') . '</th>';
        echo '<th>' . esc_html__('Status', 'artpulse-management') . '</th>';
        echo '<th>' . esc_html__('Actions', 'artpulse-management') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($reviews as $review) {
            $rating = get_post_meta($review->ID, 'ead_review_rating', true);
            $content = (string) $review->post_content;
            $status = ucfirst($review->post_status);
            $author = get_userdata($review->post_author);

            echo '<tr>';
            echo '<td>' . esc_html($author->display_name ?? __('Unknown', 'artpulse-management')) . '</td>';
            echo '<td>' . intval($rating) . '</td>';
            echo '<td>' . esc_html($content) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>';
            echo '<a href="#" class="button ead-approve-review" data-review-id="' . esc_attr($review->ID) . '">' . esc_html__('Approve', 'artpulse-management') . '</a> ';
            echo '<a href="#" class="button ead-delete-review" data-review-id="' . esc_attr($review->ID) . '">' . esc_html__('Delete', 'artpulse-management') . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        return ob_get_clean();
    }
}
?>
