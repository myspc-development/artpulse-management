<?php
namespace EAD\Admin;

class MetaBoxesArtwork {

    public static function register() {
        add_action('add_meta_boxes', [self::class, 'add_artwork_meta_boxes']);
        add_action('save_post', [self::class, 'save_artwork_meta'], 10, 2);
    }

    public static function add_artwork_meta_boxes() {
        add_meta_box(
            'ead_artwork_details',
            __('Artwork Details', 'artpulse-management'),
            [self::class, 'render_artwork_details'],
            'ead_artwork',
            'normal',
            'high'
        );
    }

    public static function render_artwork_details($post) {
        wp_nonce_field('ead_artwork_meta_nonce', 'ead_artwork_meta_nonce_field');

        $fields = self::get_registered_artwork_meta_fields();

        echo '<div class="ead-artwork-meta-box">';
        foreach ($fields as $key => $args) {
            list($type, , ,) = $args;
            $value    = get_post_meta($post->ID, $key, true);
            $label    = ucwords(str_replace('_', ' ', $key));
            $field_id = esc_attr($key . '_' . $post->ID);

            echo '<p>';
            echo '<label for="' . $field_id . '"><strong>' . esc_html($label) . ':</strong></label><br>';

            switch ($type) {
                case 'boolean':
                    $checked = $value ? 'checked' : '';
                    echo '<input type="checkbox" name="' . esc_attr($key) . '" id="' . $field_id . '" value="1" ' . $checked . ' />';
                    break;

                case 'integer':
                    echo '<input type="number" name="' . esc_attr($key) . '" id="' . $field_id . '" value="' . esc_attr($value) . '" style="width:100%;" />';
                    break;

                case 'textarea':
                    echo '<textarea name="' . esc_attr($key) . '" id="' . $field_id . '" rows="3" style="width:100%;">' . esc_textarea($value) . '</textarea>';
                    break;

                default:
                    echo '<input type="text" name="' . esc_attr($key) . '" id="' . $field_id . '" value="' . esc_attr($value) . '" style="width:100%;" />';
                    break;
            }

            echo '</p>';
        }
        echo '</div>';
    }

    public static function save_artwork_meta($post_id, $post) {
        if (
            ! isset($_POST['ead_artwork_meta_nonce_field']) ||
            ! wp_verify_nonce($_POST['ead_artwork_meta_nonce_field'], 'ead_artwork_meta_nonce')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== 'ead_artwork') {
            return;
        }

        $fields = self::get_registered_artwork_meta_fields();
        foreach ($fields as $key => $args) {
            list($type, , $sanitize_callback) = $args;

            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                if ($type === 'boolean') {
                    $value = (bool) $value;
                }

                if (is_callable($sanitize_callback)) {
                    $value = call_user_func($sanitize_callback, $value);
                }

                update_post_meta($post_id, $key, $value);
            } elseif ($type === 'boolean') {
                // Unchecked checkbox
                update_post_meta($post_id, $key, false);
            }
        }
    }

    private static function get_registered_artwork_meta_fields() {
        return [
            'artwork_title'       => ['string',  true, 'sanitize_text_field',       true],
            'artwork_artist'      => ['string',  true, 'sanitize_text_field',       true],
            'artwork_medium'      => ['string',  true, 'sanitize_text_field',       true],
            'artwork_dimensions'  => ['string',  true, 'sanitize_text_field',       true],
            'artwork_year'        => ['integer', true, 'absint',                    true],
            'artwork_materials'   => ['textarea',true, 'sanitize_textarea_field',   true],
            'artwork_price'       => ['string',  true, 'sanitize_text_field',       true],
            'artwork_provenance'  => ['textarea',true, 'sanitize_textarea_field',   true],
            'artwork_edition'     => ['string',  true, 'sanitize_text_field',       true],
            'artwork_tags'        => ['string',  true, 'sanitize_text_field',       true],
            'artwork_description' => ['textarea',true, 'sanitize_textarea_field',   true],
            'artwork_image'       => ['integer', true, 'absint',                    true],
            'artwork_video_url'   => ['string',  true, 'esc_url_raw',              true],
            // 'artwork_featured' is managed by admins only so it should not be
            // required when artworks are submitted from the frontend.
            'artwork_featured'    => ['boolean', false, 'rest_sanitize_boolean',    true],
        ];
    }
}
