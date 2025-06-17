<?php
namespace ArtPulse\Admin;

class MetaBoxesArtwork {

    public static function register() {
        add_action('add_meta_boxes', [self::class, 'add_artwork_meta_boxes']);
        add_action('save_post_ead_artwork', [self::class, 'save_artwork_meta'], 10, 2);
        add_action('rest_api_init', [self::class, 'register_rest_fields']);
        add_action('restrict_manage_posts', [self::class, 'add_admin_filters']);
        add_filter('pre_get_posts', [self::class, 'filter_artworks_admin_query']);
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

    public static function save_artwork_meta($post_id, $post) {
        if (!isset($_POST['ead_artwork_meta_nonce_field']) || !wp_verify_nonce($_POST['ead_artwork_meta_nonce_field'], 'ead_artwork_meta_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'ead_artwork') return;

        $fields = array_keys(self::get_registered_artwork_meta_fields());
        foreach ($fields as $field) {
            $value = $_POST[$field] ?? '';

            // Basic validation examples
            if (strpos($field, 'url') !== false && !empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                continue;
            }
            if (strpos($field, 'year') !== false && $value && !preg_match('/^\\d{4}$/', $value)) {
                continue;
            }

            if (self::get_registered_artwork_meta_fields()[$field][0] === 'boolean') {
                $value = isset($_POST[$field]) ? '1' : '0';
            } else {
                $value = sanitize_text_field($value);
            }

            update_post_meta($post_id, $field, $value);
        }
    }

    public static function register_rest_fields() {
        foreach (self::get_registered_artwork_meta_fields() as $field => $args) {
            register_rest_field('ead_artwork', $field, [
                'get_callback' => function($object) use ($field) {
                    return get_post_meta($object['id'], $field, true);
                },
                'update_callback' => function($value, $object) use ($field) {
                    update_post_meta($object->ID, $field, sanitize_text_field($value));
                },
                'schema' => [
                    'type' => 'string',
                    'context' => ['view', 'edit'],
                ],
            ]);
        }
    }

    public static function add_admin_filters() {
        if (get_post_type() !== 'ead_artwork') return;

        $selected = $_GET['artwork_featured'] ?? '';
        echo '<select name="artwork_featured">
            <option value="">' . __('Filter by Featured', 'artpulse-management') . '</option>
            <option value="1"' . selected($selected, '1', false) . '>Yes</option>
            <option value="0"' . selected($selected, '0', false) . '>No</option>
        </select>';
    }

    public static function filter_artworks_admin_query($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'ead_artwork') return;

        if (isset($_GET['artwork_featured']) && $_GET['artwork_featured'] !== '') {
            $query->set('meta_key', 'artwork_featured');
            $query->set('meta_value', $_GET['artwork_featured']);
        }
    }

    private static function get_registered_artwork_meta_fields() {
        return [
            'artwork_title'       => ['text', __('Title of the artwork', 'artpulse-management')],
            'artwork_artist'      => ['text', __('Name of the artist', 'artpulse-management')],
            'artwork_medium'      => ['text', __('Medium used (e.g. oil on canvas)', 'artpulse-management')],
            'artwork_dimensions'  => ['text', __('Dimensions', 'artpulse-management')],
            'artwork_year'        => ['text', __('Year created', 'artpulse-management')],
            'artwork_materials'   => ['textarea', __('List of materials', 'artpulse-management')],
            'artwork_price'       => ['text', __('Asking price (e.g. $2000)', 'artpulse-management')],
            'artwork_provenance'  => ['textarea', __('Provenance or exhibition history', 'artpulse-management')],
            'artwork_edition'     => ['text', __('Edition/number', 'artpulse-management')],
            'artwork_tags'        => ['text', __('Tags', 'artpulse-management')],
            'artwork_description' => ['textarea', __('Artwork description', 'artpulse-management')],
            'artwork_image'       => ['number', __('Featured image ID', 'artpulse-management')],
            'artwork_video_url'   => ['url', __('Video URL', 'artpulse-management')],
            'artwork_featured'    => ['boolean', __('Mark as featured', 'artpulse-management')],
            'artwork_styles'      => ['text', __('Styles', 'artpulse-management')],
        ];
    }
}
