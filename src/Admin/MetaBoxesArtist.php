<?php
namespace ArtPulse\Admin;

class MetaBoxesArtist {

    public static function register() {
        add_action('add_meta_boxes', [self::class, 'add_artist_meta_boxes']);
        add_action('save_post_ead_artist', [self::class, 'save_artist_meta'], 10, 2);
        add_action('rest_api_init', [self::class, 'register_rest_fields']);
        add_action('restrict_manage_posts', [self::class, 'add_admin_filters']);
        add_filter('pre_get_posts', [self::class, 'filter_admin_query']);
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

        echo '<table class="form-table">';
        foreach ($fields as $key => $args) {
            list($type, $label) = $args;
            $value = get_post_meta($post->ID, $key, true);
            echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            switch ($type) {
                case 'text':
                case 'email':
                case 'url':
                case 'date':
                case 'number':
                    echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                    break;
                case 'boolean':
                    echo '<input type="checkbox" name="' . esc_attr($key) . '" value="1" ' . checked($value, '1', false) . ' />';
                    break;
                case 'textarea':
                    echo '<textarea name="' . esc_attr($key) . '" rows="4" class="large-text">' . esc_textarea($value) . '</textarea>';
                    break;
                default:
                    echo '<input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
            }
            echo '</td></tr>';
        }
        echo '</table>';
    }

    public static function save_artist_meta($post_id, $post) {
        if (!isset($_POST['ead_artist_meta_nonce_field']) || !wp_verify_nonce($_POST['ead_artist_meta_nonce_field'], 'ead_artist_meta_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'ead_artist') return;

        $fields = self::get_registered_artist_meta_fields();
        foreach ($fields as $field => $args) {
            $type = $args[0];
            $value = $_POST[$field] ?? '';

            switch ($type) {
                case 'email':
                    if (!is_email($value)) continue 2;
                    break;
                case 'url':
                    $value = esc_url_raw($value);
                    break;
                case 'boolean':
                    $value = isset($_POST[$field]) ? '1' : '0';
                    break;
                case 'number':
                    if (!is_numeric($value)) continue 2;
                    break;
                case 'textarea':
                    $value = sanitize_textarea_field($value);
                    break;
                default:
                    $value = sanitize_text_field($value);
            }

            update_post_meta($post_id, $field, $value);
        }
    }

    public static function register_rest_fields() {
        foreach (array_keys(self::get_registered_artist_meta_fields()) as $key) {
            register_rest_field('ead_artist', $key, [
                'get_callback' => function($object) use ($key) {
                    return get_post_meta($object['id'], $key, true);
                },
                'schema' => [
                    'type' => 'string',
                    'context' => ['view', 'edit']
                ]
            ]);
        }
    }

    public static function add_admin_filters() {
        if (get_current_screen()->post_type !== 'ead_artist') return;
        $selected = $_GET['artist_featured'] ?? '';
        echo '<select name="artist_featured">';
        echo '<option value="">' . __('Filter by Featured', 'artpulse-management') . '</option>';
        echo '<option value="1" ' . selected($selected, '1', false) . '>Yes</option>';
        echo '<option value="0" ' . selected($selected, '0', false) . '>No</option>';
        echo '</select>';
    }

    public static function filter_admin_query($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'ead_artist') return;
        if (isset($_GET['artist_featured']) && $_GET['artist_featured'] !== '') {
            $query->set('meta_key', 'artist_featured');
            $query->set('meta_value', $_GET['artist_featured']);
        }
    }

    private static function get_registered_artist_meta_fields() {
        return [
            'artist_name'         => ['text', __('Artist Name', 'artpulse-management')],
            'artist_bio'          => ['textarea', __('Biography', 'artpulse-management')],
            'artist_email'        => ['email', __('Email', 'artpulse-management')],
            'artist_phone'        => ['text', __('Phone', 'artpulse-management')],
            'artist_website'      => ['url', __('Website', 'artpulse-management')],
            'artist_facebook'     => ['url', __('Facebook', 'artpulse-management')],
            'artist_instagram'    => ['text', __('Instagram', 'artpulse-management')],
            'artist_twitter'      => ['text', __('Twitter', 'artpulse-management')],
            'artist_linkedin'     => ['url', __('LinkedIn', 'artpulse-management')],
            'artist_portrait'     => ['number', __('Portrait ID', 'artpulse-management')],
            'artist_specialties'  => ['text', __('Specialties', 'artpulse-management')],
            'artist_featured'     => ['boolean', __('Featured', 'artpulse-management')]
        ];
    }
}
