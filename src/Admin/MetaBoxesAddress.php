<?php
namespace ArtPulse\Admin;

class MetaBoxesAddress {

    public static function register($post_types) {
        if (!is_array($post_types)) {
            $post_types = [$post_types];
        }

        foreach ($post_types as $post_type) {
            add_action("add_meta_boxes_{$post_type}", function($post) use ($post_type) {
                add_meta_box(
                    'ead_address_' . $post_type,
                    __('Address', 'artpulse-management'),
                    [self::class, 'render_address_meta_box'],
                    $post_type,
                    'normal',
                    'default'
                );
            });

            add_action("save_post_{$post_type}", function($post_id, $post) use ($post_type) {
                self::save_address_meta($post_id, $post);
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
            echo '<input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
            echo '</td></tr>';
        }
        echo '</table>';
    }

    public static function save_address_meta($post_id, $post) {
        if (!isset($_POST['ead_address_meta_nonce_field']) || !wp_verify_nonce($_POST['ead_address_meta_nonce_field'], 'ead_address_meta_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $fields = array_keys(self::get_address_meta_fields());

        foreach ($fields as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            update_post_meta($post_id, $field, $value);
        }
    }

    private static function get_address_meta_fields() {
        return [
            'street_address' => __('Street Address', 'artpulse-management'),
            'city'           => __('City', 'artpulse-management'),
            'state'          => __('State', 'artpulse-management'),
            'postcode'       => __('Postcode', 'artpulse-management'),
            'country'        => __('Country', 'artpulse-management'),
        ];
    }
}
