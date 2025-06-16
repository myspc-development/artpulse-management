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
            list($type, $required, $sanitize_callback, $description, $choices) = array_pad($args, 5, '');
            $value = ead_get_meta($post->ID, $key);
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
                case 'array':
                    // Render a multi-select if choices are defined, else fallback to comma-separated text
                    if (!empty($choices) && is_array($choices)) {
                        $current = is_array($value) ? $value : explode(',', $value);
                        echo '<select name="' . esc_attr($key) . '[]" id="' . $field_id . '" multiple style="width:100%;">';
                        foreach ($choices as $choice) {
                            $selected = in_array($choice, $current) ? 'selected' : '';
                            echo '<option value="' . esc_attr($choice) . '" ' . $selected . '>' . esc_html($choice) . '</option>';
                        }
                        echo '</select>';
                    } else {
                        echo '<input type="text" name="' . esc_attr($key) . '" id="' . $field_id . '" value="' .
                            esc_attr(is_array($value) ? implode(', ', $value) : $value) . '" style="width:100%;" />';
                    }
                    break;
                default:
                    echo '<input type="text" name="' . esc_attr($key) . '" id="' . $field_id . '" value="' . esc_attr($value) . '" style="width:100%;" />';
                    break;
            }
            if (!empty($description)) {
                echo '<br><small class="description">' . esc_html__($description, 'artpulse-management') . '</small>';
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

            // Type-based sanitization, with arrays/multi-selects
            if ($type === 'boolean') {
                $value = $value === '1' ? 1 : 0;
            } elseif ($type === 'integer') {
                $value = intval($value);
            } elseif ($type === 'url') {
                $value = esc_url_raw($value);
            } elseif ($type === 'textarea') {
                $value = is_callable($sanitize_callback) ? call_user_func($sanitize_callback, $value) : wp_kses_post($value);
            } elseif ($type === 'array') {
                // Multi-select sends an array; fallback to comma-separated for text
                if (is_array($value)) {
                    $value = array_map('sanitize_text_field', $value);
                } else {
                    $value = array_map('sanitize_text_field', explode(',', $value));
                }
            } else {
                $value = is_callable($sanitize_callback) ? call_user_func($sanitize_callback, $value) : sanitize_text_field($value);
            }

            update_post_meta($post_id, $key, $value);
        }
    }

    /**
     * Fields: type, required, sanitizer, description, choices (for array type)
     */
    private static function get_registered_artwork_meta_fields() {
        return [
            // key => [type, required, sanitizer, description, choices]
            'artwork_title'       => ['string',  true,  'sanitize_text_field', __('Title of the artwork', 'artpulse-management')],
            'artwork_artist'      => ['string',  true,  'sanitize_text_field', __('Name of the artist', 'artpulse-management')],
            'artwork_medium'      => ['string',  true,  'sanitize_text_field', __('Medium used (e.g. oil on canvas)', 'artpulse-management')],
            'artwork_dimensions'  => ['string',  false, 'sanitize_text_field', __('e.g. 24x36 inches', 'artpulse-management')],
            'artwork_year'        => ['integer', false, 'absint', __('Year created', 'artpulse-management')],
            'artwork_materials'   => ['textarea',false, 'sanitize_textarea_field', __('List of materials', 'artpulse-management')],
            'artwork_price'       => ['string',  false, 'sanitize_text_field', __('Asking price (e.g. $2000)', 'artpulse-management')],
            'artwork_provenance'  => ['textarea',false, 'sanitize_textarea_field', __('Provenance or exhibition history', 'artpulse-management')],
            'artwork_edition'     => ['string',  false, 'sanitize_text_field', __('Edition/number', 'artpulse-management')],
            'artwork_tags'        => ['array',   false, 'sanitize_text_field', __('Tags (comma separated or choose)', 'artpulse-management'), ['Abstract','Modern','Classic','Photography','Sculpture','Watercolor','Other']],
            'artwork_description' => ['textarea',false, 'sanitize_textarea_field', __('Artwork description', 'artpulse-management')],
            'artwork_image'       => ['integer', false, 'absint', __('Featured image attachment ID', 'artpulse-management')],
            'artwork_video_url'   => ['url',     false, 'esc_url_raw', __('Video URL (optional)', 'artpulse-management')],
            'artwork_featured'    => ['boolean', false, 'rest_sanitize_boolean', __('Mark as featured (admin only)', 'artpulse-management')],
            'artwork_styles'      => ['array',   false, 'sanitize_text_field', __('Select one or more styles', 'artpulse-management'), ['Impressionism','Pop Art','Realism','Minimalism','Surrealism','Cubism','Other']],
        ];
    }
}
