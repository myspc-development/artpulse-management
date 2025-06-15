<?php
namespace EAD\Admin;

class MetaBoxesArtist {

    public static function register() {
        add_action('add_meta_boxes', [self::class, 'add_artist_meta_boxes']);
        add_action('save_post', [self::class, 'save_artist_meta'], 10, 2);
    }

    public static function add_artist_meta_boxes() {
        add_meta_box(
            'ead_artist_details',
            __('Artist Details', 'artpulse-management'),
            [self::class, 'render_artist_details'],
            'ead_artist',
            'normal',
            'high'
        );
    }

    public static function render_artist_details($post) {
        wp_nonce_field('ead_artist_meta_nonce', 'ead_artist_meta_nonce_field');

        $fields = self::get_registered_artist_meta_fields();
        echo '<div class="ead-artist-meta-box">';
        foreach ($fields as $key => $args) {
            list($type, $required, $sanitize_callback, $description, $choices) = array_pad($args, 5, '');
            $value = get_post_meta($post->ID, $key, true);
            $label = ucwords(str_replace('_', ' ', $key));
            $field_id = esc_attr($key . '_' . $post->ID);

            echo '<p>';
            echo '<label for="' . $field_id . '"><strong>' . esc_html__($label, 'artpulse-management') . ($required ? ' *' : '') . ':</strong></label><br>';

            // Render field type
            if ($type === 'boolean') {
                $checked = $value ? 'checked' : '';
                echo '<input type="checkbox" name="' . esc_attr($key) . '" id="' . $field_id . '" value="1" ' . $checked . ' />';
            } elseif ($type === 'integer') {
                echo '<input type="number" name="' . esc_attr($key) . '" id="' . $field_id . '" value="' . esc_attr($value) . '" style="width:100%;" />';
            } elseif ($type === 'textarea') {
                echo '<textarea name="' . esc_attr($key) . '" id="' . $field_id . '" rows="3" style="width:100%;">' . esc_textarea($value) . '</textarea>';
            } elseif ($type === 'array') {
                // Render as comma-separated text (or extend for multi-select)
                echo '<input type="text" name="' . esc_attr($key) . '" id="' . $field_id . '" value="' . esc_attr(is_array($value) ? implode(', ', $value) : $value) . '" style="width:100%;" />';
            } elseif ($type === 'url') {
                echo '<input type="url" name="' . esc_attr($key) . '" id="' . $field_id . '" value="' . esc_url($value) . '" style="width:100%;" />';
            } else {
                echo '<input type="text" name="' . esc_attr($key) . '" id="' . $field_id . '" value="' . esc_attr($value) . '" style="width:100%;" />';
            }

            // Field description/help
            if (!empty($description)) {
                echo '<br><small class="description">' . esc_html__($description, 'artpulse-management') . '</small>';
            }

            echo '</p>';
        }
        echo '</div>';
    }

    public static function save_artist_meta($post_id, $post) {
        if (
            !isset($_POST['ead_artist_meta_nonce_field']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ead_artist_meta_nonce_field'])), 'ead_artist_meta_nonce')
        ) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'ead_artist') return;

        $fields = self::get_registered_artist_meta_fields();
        foreach ($fields as $key => $args) {
            list($type, , $sanitize_callback) = $args;
            $value = isset($_POST[$key]) ? $_POST[$key] : ($type === 'boolean' ? '0' : '');

            // Handle arrays and type-based sanitization
            if ($type === 'boolean') {
                $value = $value === '1' ? 1 : 0;
            } elseif ($type === 'integer') {
                $value = intval($value);
            } elseif ($type === 'url') {
                $value = esc_url_raw($value);
            } elseif ($type === 'textarea') {
                $value = is_callable($sanitize_callback) ? call_user_func($sanitize_callback, $value) : wp_kses_post($value);
            } elseif ($type === 'array') {
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
    private static function get_registered_artist_meta_fields() {
        return [
            // key                type      required  sanitizer           description                                choices (for 'array')
            'artist_name'     => ['string', true,  'sanitize_text_field', __('Artist name', 'artpulse-management')],
            'artist_bio'      => ['textarea',true,'sanitize_textarea_field', __('Short biography', 'artpulse-management')],
            'artist_email'    => ['string', true,  'sanitize_email', __('Contact email', 'artpulse-management')],
            'artist_phone'    => ['string', false, 'sanitize_text_field', __('Phone number', 'artpulse-management')],
            'artist_website'  => ['url',    false, 'esc_url_raw', __('Personal website URL', 'artpulse-management')],
            'artist_facebook' => ['url',    false, 'esc_url_raw', __('Facebook URL', 'artpulse-management')],
            'artist_instagram'=> ['string', false, 'sanitize_text_field', __('Instagram handle or URL', 'artpulse-management')],
            'artist_twitter'  => ['string', false, 'sanitize_text_field', __('Twitter handle or URL', 'artpulse-management')],
            'artist_linkedin' => ['url',    false, 'esc_url_raw', __('LinkedIn profile URL', 'artpulse-management')],
            'artist_portrait' => ['integer',false, 'absint', __('Portrait attachment ID', 'artpulse-management')],
            'artist_specialties'=>['array',false,  'sanitize_text_field', __('Areas of specialty (comma separated or choose)', 'artpulse-management'), ['Painting','Sculpture','Photography','Performance','Installation','Digital','Other']],
            'artist_featured' => ['boolean',false, 'rest_sanitize_boolean', __('Mark as featured (admin only)', 'artpulse-management')],
        ];
    }
}
