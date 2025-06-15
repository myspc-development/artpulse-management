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
            list($type, $required, $sanitize_callback) = $args;
            $value = get_post_meta($post->ID, $key, true);
            $label = ucwords(str_replace('_', ' ', $key));
            $field_id = esc_attr($key . '_' . $post->ID);

            echo '<p>';
            echo '<label for="' . $field_id . '"><strong>' . esc_html__($label, 'artpulse-management') . ($required ? ' *' : '') . ':</strong></label><br>';

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
                case 'url':
                    echo '<input type="url" name="' . esc_attr($key) . '" id="' . $field_id . '" value="' . esc_attr($value) . '" style="width:100%;" />';
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
            !isset($_POST['ead_artwork_meta_nonce_field']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ead_artwork_meta_nonce_field'])), 'ead_artwork_meta_nonce')
        ) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'ead_artwork') return;

        $fields = self::get_registered_artwork_meta_fields();
        foreach ($fields as $key => $args) {
            list($type, , $sanitize_callback) = $args;
            $value = isset($_POST[$key]) ? $_POST[$key] : ($type === 'boolean' ? '0' : '');

            // Type-based sanitization
            if ($type === 'boolean') {
                $value = $value === '1' ? 1 : 0;
            } elseif ($type === 'integer') {
                $value = intval($value);
            } elseif ($type === 'url') {
                $value = esc_url_raw($value);
            } elseif ($type === 'textarea') {
                $value = is_callable($sanitize_callback) ? call_user_func($sanitize_callback, $value) : wp_kses_post($value);
            } else {
                $value = is_callable($sanitize_callback) ? call_user_func($sanitize_callback, $value) : sanitize_text_field($value);
            }

            update_post_meta($post_id, $key, $value);
        }
    }

    private static function get_registered_artwork_meta_fields() {
        return [
            //   key                  type      required   sanitize_callback
            'artwork_title'       => ['string',  true,  'sanitize_text_field'],
            'artwork_artist'      => ['string',  true,  'sanitize_text_field'],
            'artwork_medium'      => ['string',  true,  'sanitize_text_field'],
            'artwork_dimensions'  => ['string',  true,  'sanitize_text_field'],
            'artwork_year'        => ['integer', true,  'absint'],
            'artwork_materials'   => ['textarea',true,  'sanitize_textarea_field'],
            'artwork_price'       => ['string',  true,  'sanitize_text_field'],
            'artwork_provenance'  => ['textarea',true,  'sanitize_textarea_field'],
            'artwork_edition'     => ['string',  true,  'sanitize_text_field'],
            'artwork_tags'        => ['string',  true,  'sanitize_text_field'],
            'artwork_description' => ['textarea',true,  'sanitize_textarea_field'],
            'artwork_image'       => ['integer', true,  'absint'],
            'artwork_video_url'   => ['url',     true,  'esc_url_raw'],
            'artwork_featured'    => ['boolean', false, 'rest_sanitize_boolean'],
        ];
    }
}
