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
            'ead_artist', // Matches your post type
            'normal',
            'high'
        );
    }

    public static function render_artist_details($post) {
        wp_nonce_field('ead_artist_meta_nonce', 'ead_artist_meta_nonce_field');

        $fields = self::get_registered_artist_meta_fields();

        echo '<div class="ead-artist-meta-box">';
        foreach ($fields as $key => $args) {
            $value = get_post_meta($post->ID, $key, true);
            $label = ucwords(str_replace('_', ' ', $key));
            $input_type = 'text'; // default
            $field_id = esc_attr($key . '_' . $post->ID); // Ensure unique IDs per field

            if ($args[0] === 'boolean') {
                $input_type = 'checkbox';
            } elseif ($args[0] === 'integer') {
                $input_type = 'number';
            } elseif ($args[0] === 'array') {
                $input_type = 'text'; // treat arrays as comma-separated
            }

            echo '<p>';
            echo '<label for="' . $field_id . '"><strong>' . esc_html($label) . ':</strong></label><br>';

            if ($input_type === 'checkbox') {
                $checked = $value ? 'checked' : '';
                echo '<input type="checkbox" name="' . esc_attr($key) . '" id="' . $field_id . '" value="1" ' . $checked . ' />';
            } else {
                echo '<input type="' . esc_attr($input_type) . '" name="' . esc_attr($key) . '" id="' . $field_id . '" value="' . esc_attr($value) . '" style="width:100%;" />';
            }

            echo '</p>';
        }
        echo '</div>';
    }

    public static function save_artist_meta($post_id, $post) {
        if ( ! isset( $_POST['ead_artist_meta_nonce_field'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ead_artist_meta_nonce_field'] ) ), 'ead_artist_meta_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( $post->post_type !== 'ead_artist' ) {
            return;
        }

        $fields = self::get_registered_artist_meta_fields();

        foreach ($fields as $key => $args) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];

                if ($args[0] === 'boolean') {
                    $value = $_POST[$key] ? true : false;
                }

                $sanitize_callback = $args[2];
                if (is_callable($sanitize_callback)) {
                    $value = call_user_func($sanitize_callback, $value);
                }

                update_post_meta($post_id, $key, $value);
            } elseif ($args[0] === 'boolean') {
                update_post_meta($post_id, $key, false);
            }
        }
    }

    private static function get_registered_artist_meta_fields() {
        return [
            'artist_name' => ['string', true, 'sanitize_text_field', true],
            'artist_bio' => ['string', true, 'sanitize_textarea_field', true],
            'artist_email' => ['string', true, 'sanitize_email', true],
            'artist_phone' => ['string', true, 'sanitize_text_field', true],
            'artist_website' => ['string', true, 'esc_url_raw', true],
            'artist_facebook' => ['string', true, 'esc_url_raw', true],
            'artist_instagram' => ['string', true, 'sanitize_text_field', true],
            'artist_twitter' => ['string', true, 'sanitize_text_field', true],
            'artist_linkedin' => ['string', true, 'esc_url_raw', true],
            'artist_portrait' => ['integer', true, 'absint', true],
            // Add any additional fields registered in Artist_Meta
        ];
    }
}
