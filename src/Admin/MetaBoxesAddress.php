<?php
namespace ArtPulse\Admin;

class MetaBoxesAddress {

    public static function register($post_types) {
        if (!is_array($post_types)) {
            $post_types = [$post_types];
        }

        foreach ($post_types as $post_type) {
            // Ensure the post type exists before adding hooks
            if (!post_type_exists($post_type)) {
                // Optionally log an error if the post type doesn't exist
                // error_log("ArtPulse Address Meta Box: Post type '{$post_type}' does not exist.");
                continue;
            }

            add_action("add_meta_boxes_{$post_type}", function($post) use ($post_type) {
                add_meta_box(
                    'ead_address_meta_box_' . $post_type, // Unique ID per post type
                    __('Address', 'artpulse-management'),
                    [self::class, 'render_address_meta_box'],
                    $post_type, // This is correct, uses the passed post_type
                    'normal',
                    'default'
                );
            });

            add_action("save_post_{$post_type}", function($post_id, $post) use ($post_type) {
                // Check if the current post type matches the one this hook is for
                // This check is important because save_post_{$post_type} can sometimes be too broad
                // if not careful with hook priorities or if other plugins interfere.
                // However, WordPress core usually handles this correctly for this specific hook.
                if ($post->post_type === $post_type) {
                    self::save_address_meta($post_id, $post);
                }
            }, 10, 2);
        }
    }

    public static function render_address_meta_box($post) {
        wp_nonce_field('ead_address_meta_nonce', 'ead_address_meta_nonce_field');

        $fields = self::get_address_meta_fields();

        echo '<table class="form-table">';
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, $key, true);
            echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            echo '<input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
            echo '</td></tr>';
        }
        echo '</table>';
    }

    public static function save_address_meta($post_id, $post) { // $post parameter is available
        if (!isset($_POST['ead_address_meta_nonce_field']) || !wp_verify_nonce($_POST['ead_address_meta_nonce_field'], 'ead_address_meta_nonce')) {
            return;
        }

        // Check if it's an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions (important!)
        // The post type check is implicitly handled by the save_post_{$post_type} hook,
        // but an explicit check for capability is good practice.
        if (isset($post->post_type) && 'page' == $post->post_type) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }


        $fields = array_keys(self::get_address_meta_fields());

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                update_post_meta($post_id, $field, $value);
            } else {
                // If a field is not set (e.g., checkbox unchecked, though not used here),
                // you might want to delete the meta or save an empty value.
                // For text fields, saving an empty string if not set is often fine.
                // update_post_meta($post_id, $field, '');
            }
        }
    }

    private static function get_address_meta_fields() {
        return [
            'street_address' => __('Street Address', 'artpulse-management'),
            'city'           => __('City', 'artpulse-management'),
            'state'          => __('State / Province', 'artpulse-management'),
            'postcode'       => __('Postcode / Zip Code', 'artpulse-management'),
            'country'        => __('Country', 'artpulse-management'),
        ];
    }
}