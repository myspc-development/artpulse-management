<?php

namespace ArtPulse\Frontend;

/**
 * Handles output of the front-end submission form and wiring up JS validation.
 */
class SubmissionForms
{
    /**
     * Register shortcode for submission form.
     */
    public static function register(): void
    {
        add_shortcode('ap_submission_form', [__CLASS__, 'render_form']);
    }

    /**
     * Render the submission form HTML.
     *
     * Usage: [ap_submission_form post_type="artpulse_event"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML form.
     */
    public static function render_form(array $atts): string
    {
        $atts = shortcode_atts(
            [
                'post_type' => 'artpulse_event',
            ],
            $atts,
            'ap_submission_form'
        );

        // Form classes and data
        $post_type = esc_attr($atts['post_type']);

        ob_start();
        ?>
        <form class="ap-submission-form" data-post-type="<?php echo $post_type; ?>">
            <p>
                <label for="ap-title"><?php esc_html_e('Title*', 'artpulse'); ?></label><br>
                <input id="ap-title" type="text" name="title" data-required="<?php esc_attr_e('Title is required', 'artpulse'); ?>" />
            </p>
            <p>
                <label for="ap-date"><?php esc_html_e('Date*', 'artpulse'); ?></label><br>
                <input id="ap-date" type="date" name="event_date" data-required="<?php esc_attr_e('Date is required', 'artpulse'); ?>" />
            </p>
            <p>
                <label for="ap-location"><?php esc_html_e('Location*', 'artpulse'); ?></label><br>
                <input id="ap-location" type="text" name="event_location" data-required="<?php esc_attr_e('Location is required', 'artpulse'); ?>" />
            </p>
            <p>
                <label for="ap-images"><?php esc_html_e('Images (max 5)', 'artpulse'); ?></label><br>
                <input id="ap-images" type="file" name="images[]" accept="image/*" multiple />
            </p>
            <p>
                <button type="submit"><?php esc_html_e('Submit', 'artpulse'); ?></button>
            </p>
        </form>
        <ul class="ap-submissions-list"></ul>
        <?php
        return ob_get_clean();
    }
}
