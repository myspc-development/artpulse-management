<?php
namespace ArtPulse\Admin;

class MetaBoxesOrganisation {

    public static function register() {
        add_action('add_meta_boxes', [self::class, 'add_org_meta_boxes']);
        add_action('save_post_ead_organization', [self::class, 'save_org_meta'], 10, 2);
        add_action('rest_api_init', [self::class, 'register_rest_fields']);
        add_action('restrict_manage_posts', [self::class, 'add_admin_filters']);
        add_filter('pre_get_posts', [self::class, 'filter_admin_query']);
    }

    public static function add_org_meta_boxes() {
        add_meta_box(
            'ead_org_details',
            __('Organization Details', 'artpulse-management'),
            [self::class, 'render_org_details'],
            'ead_organization',
            'normal',
            'high'
        );
    }

    public static function render_org_details($post) {
        wp_nonce_field('ead_org_meta_nonce', 'ead_org_meta_nonce_field');

        $fields = self::get_registered_org_meta_fields();

        echo '<table class="form-table">';
        foreach ($fields as $key => $args) {
            list($type, $label) = $args;
            $value = get_post_meta($post->ID, $key, true);
            echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            switch ($type) {
                case 'text':
                case 'url':
                case 'email':
                case 'date':
                case 'number':
                    echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
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

    public static function save_org_meta($post_id, $post) {
        if (!isset($_POST['ead_org_meta_nonce_field']) || !wp_verify_nonce($_POST['ead_org_meta_nonce_field'], 'ead_org_meta_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'ead_organization') return;

        $fields = self::get_registered_org_meta_fields();
        foreach ($fields as $field => $args) {
            $value = isset($_POST[$field]) ? $_POST[$field] : '';
            if (!self::validate_field($field, $value)) continue;
            update_post_meta($post_id, $field, sanitize_text_field($value));
        }
    }

    private static function validate_field($field, $value) {
        if (in_array($field, ['ead_org_website', 'ead_org_logo_url', 'ead_org_banner_url']) && !filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }
        if (in_array($field, ['ead_org_geo_lat', 'ead_org_geo_lng']) && !is_numeric($value)) {
            return false;
        }
        return true;
    }

    public static function register_rest_fields() {
        foreach (array_keys(self::get_registered_org_meta_fields()) as $key) {
            register_rest_field('ead_organization', $key, [
                'get_callback'    => fn($data) => get_post_meta($data['id'], $key, true),
                'update_callback' => fn($value, $object) => update_post_meta($object->ID, $key, sanitize_text_field($value)),
                'schema'          => ['type' => 'string']
            ]);
        }
    }

    public static function add_admin_filters() {
        global $typenow;
        if ($typenow !== 'ead_organization') return;

        $selected = $_GET['ead_org_type'] ?? '';
        echo '<select name="ead_org_type">';
        echo '<option value="">' . __('Filter by Type', 'artpulse-management') . '</option>';
        foreach (['gallery', 'museum', 'studio', 'collective', 'non-profit', 'commercial-gallery', 'public-art-space', 'educational-institution', 'other'] as $type) {
            echo '<option value="' . esc_attr($type) . '" ' . selected($selected, $type, false) . '>' . ucfirst(str_replace('-', ' ', $type)) . '</option>';
        }
        echo '</select>';
    }

    public static function filter_admin_query($query) {
        global $pagenow;
        if (!is_admin() || $pagenow !== 'edit.php' || $query->get('post_type') !== 'ead_organization') return;

        if (!empty($_GET['ead_org_type'])) {
            $query->set('meta_key', 'ead_org_type');
            $query->set('meta_value', sanitize_text_field($_GET['ead_org_type']));
        }
    }

    private static function get_registered_org_meta_fields() {
        return [
            'ead_org_name'        => ['text', __('Organization Name', 'artpulse-management')],
            'ead_org_description' => ['textarea', __('Description', 'artpulse-management')],
            'ead_org_type'        => ['text', __('Type (e.g. Museum, Gallery)', 'artpulse-management')],
            'ead_org_website'     => ['url', __('Website', 'artpulse-management')],
            'ead_org_logo_url'    => ['url', __('Logo Image URL', 'artpulse-management')],
            'ead_org_banner_url'  => ['url', __('Banner Image URL', 'artpulse-management')],
            'ead_org_geo_lat'     => ['text', __('Latitude', 'artpulse-management')],
            'ead_org_geo_lng'     => ['text', __('Longitude', 'artpulse-management')]
        ];
    }
}
