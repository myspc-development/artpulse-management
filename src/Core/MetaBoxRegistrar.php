<?php
namespace ArtPulse\Core;

class MetaBoxRegistrar
{
    /**
     * Registers the WordPress hooks for adding and saving the event meta box.
     */
    public static function register()
    {
        // Hook to add the meta box specifically to the 'artpulse_event' CPT edit screen.
        // The callback 'self::add_meta_boxes' will receive the $post object for 'artpulse_event'.
        add_action('add_meta_boxes_artpulse_event', [self::class, 'add_meta_boxes']);

        // Hook to save the meta data when an 'artpulse_event' CPT is saved.
        // This hook is more specific and efficient than the generic 'save_post'.
        // It passes $post_id and $post to the callback.
        add_action('save_post_artpulse_event', [self::class, 'save_meta'], 10, 2);
    }

    /**
     * Callback function to add the actual meta box to the edit screen.
     * This method is called by the 'add_meta_boxes_artpulse_event' action hook.
     *
     * @param \WP_Post $post The current post object (specifically for 'artpulse_event').
     */
    public static function add_meta_boxes($post)
    {
        add_meta_box(
            'ap_event_details_metabox',           // Unique ID for the meta box
            __('Event Details', 'artpulse-management'),      // Title of the meta box, translatable
            [self::class, 'render_event_meta_box'], // Callback function to render the meta box HTML
            'artpulse_event',                     // The CPT slug this meta box appears on
            'normal',                             // Context (where on the screen: 'normal', 'side', 'advanced')
            'high'                                // Priority within the context ('high', 'core', 'default', 'low')
        );
    }

    /**
     * Callback function to render the HTML content of the event meta box.
     *
     * @param \WP_Post $post The current post object.
     */
    public static function render_event_meta_box($post)
    {
        // Retrieve existing meta values for the fields
        $date     = get_post_meta($post->ID, '_ap_event_date', true);
        $location = get_post_meta($post->ID, '_ap_event_location', true);

        // Add a nonce field for security. This should be verified during save.
        // Using a specific action name for the nonce improves security.
        wp_nonce_field('ap_event_details_save_action', 'ap_event_details_nonce');

        // HTML for the Event Date input field
        echo '<p>';
        echo '<label for="ap_event_date_field">' . esc_html__('Event Date:', 'artpulse-management') . '</label><br>';
        echo '<input type="date" id="ap_event_date_field" name="ap_event_date" value="' . esc_attr($date) . '" class="widefat"/>';
        echo '</p>';

        // HTML for the Location input field
        echo '<p>';
        echo '<label for="ap_event_location_field">' . esc_html__('Location:', 'artpulse-management') . '</label><br>';
        echo '<input type="text" id="ap_event_location_field" name="ap_event_location" value="' . esc_attr($location) . '" class="widefat"/>';
        echo '</p>';
    }

    /**
     * Callback function to save the custom meta data when the post is saved.
     *
     * @param int      $post_id The ID of the post being saved.
     * @param \WP_Post $post    The post object (passed by 'save_post_artpulse_event').
     */
    public static function save_meta($post_id, $post)
    {
        // 1. Verify the nonce for security.
        // Ensure the nonce field name matches the one used in render_event_meta_box().
        if (!isset($_POST['ap_event_details_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ap_event_details_nonce'])), 'ap_event_details_save_action')) {
            return;
        }

        // 2. Check if this is an autosave. If so, our form has not been submitted,
        //    so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 3. Check the user's permissions.
        // Since this is hooked to 'save_post_artpulse_event', we are sure it's an 'artpulse_event'.
        // We need to check if the current user has permission to edit this specific post.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // 4. Save or update the meta field for 'Event Date'.
        // Use array_key_exists to allow saving an empty string if the field is cleared by the user.
        if (array_key_exists('ap_event_date', $_POST)) {
            $event_date = sanitize_text_field($_POST['ap_event_date']);
            update_post_meta($post_id, '_ap_event_date', $event_date);
        }

        // 5. Save or update the meta field for 'Location'.
        if (array_key_exists('ap_event_location', $_POST)) {
            $event_location = sanitize_text_field($_POST['ap_event_location']);
            update_post_meta($post_id, '_ap_event_location', $event_location);
        }
    }
}

// To use this class, you would typically register it in your plugin's main file
// or an initialization hook, for example:
//
// add_action('plugins_loaded', function() {
//     if (class_exists('ArtPulse\Core\MetaBoxRegistrar')) {
//         ArtPulse\Core\MetaBoxRegistrar::register();
//     }
// });
//
// Note: If you have other classes (like ArtPulse\Admin\MetaBoxesEvent from previous context)
// also trying to add meta boxes for 'artpulse_event', ensure there are no conflicts or
// redundant meta boxes being added. This class is self-contained for the 'Event Details' meta box.