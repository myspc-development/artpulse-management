<?php
namespace ArtPulse\Admin;

class MetaBoxesOrganisation {

    public static function register() {
        add_action('add_meta_boxes', [self::class, 'add_org_meta_boxes']);
        add_action('save_post_artpulse_org', [self::class, 'save_org_meta'], 10, 2); // Corrected CPT slug
        add_action('rest_api_init', [self::class, 'register_rest_fields']);
        add_action('restrict_manage_posts', [self::class, 'add_admin_filters']);
        add_filter('pre_get_posts', [self::class, 'filter_admin_query']);
    }

    public static function add_org_meta_boxes() {
        add_meta_box(
            'ead_org_details', // This is the meta box ID, can remain or change for consistency
            __('Organization Details', 'artpulse-management'),
            [self::class, 'render_org_details'],
            'artpulse_org', // Corrected CPT slug
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
                case 'email': // Though not used in current fields
                case 'date':  // Though not used in current fields
                case 'number': // For lat/lng, though text is also fine
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
        if ($post->post_type !== 'artpulse_org') return; // Corrected CPT slug

        $fields = self::get_registered_org_meta_fields();
        foreach ($fields as $field => $args) {
            $value = isset($_POST[$field]) ? $_POST[$field] : ''; // Default to empty string
            $type = $args[0];

            // Validation based on type
            if ($type === 'url' && !empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                // Optionally add admin notice
                continue;
            }
            if (($field === 'ead_org_geo_lat' || $field === 'ead_org_geo_lng') && !empty($value) && !is_numeric($value)) {
                 // Optionally add admin notice
                continue;
            }

            // Sanitize based on type
            if ($type === 'textarea') {
                $sanitized_value = sanitize_textarea_field($value);
            } elseif ($type === 'url') {
                $sanitized_value = esc_url_raw($value);
            } else {
                $sanitized_value = sanitize_text_field($value);
            }
            update_post_meta($post_id, $field, $sanitized_value);
        }
    }

    // The original validate_field method was a bit broad. It's better to validate within save_org_meta.
    // private static function validate_field($field, $value) { ... }

    public static function register_rest_fields() {
        foreach (self::get_registered_org_meta_fields() as $key => $args) { // Iterate over the fields array
            register_rest_field('artpulse_org', $key, [ // Corrected CPT slug
                'get_callback'    => fn($data) => get_post_meta($data['id'], $key, true),
                'update_callback' => function($value, $object) use ($key, $args) {
                    $type = $args[0];
                    $sanitized_value = '';
                    if ($type === 'textarea') {
                        $sanitized_value = sanitize_textarea_field($value);
                    } elseif ($type === 'url') {
                        $sanitized_value = esc_url_raw($value);
                    } else {
                        $sanitized_value = sanitize_text_field($value);
                    }
                    return update_post_meta($object->ID, $key, $sanitized_value);
                },
                'schema'          => ['type' => ($args[0] === 'url' || $args[0] === 'textarea') ? 'string' : 'string' ] // Adjust schema type if needed
            ]);
        }
    }

    public static function add_admin_filters() {
        global $typenow; // Using global $typenow is fine here
        if ($typenow !== 'artpulse_org') return; // Corrected CPT slug

        $selected = $_GET['ead_org_type'] ?? ''; // Keep meta key for filter if it's already in use
        echo '<select name="ead_org_type">';
        echo '<option value="">' . __('Filter by Type', 'artpulse-management') . '</option>';
        // These types should ideally come from a dynamic source or a constant
        foreach (['gallery', 'museum', 'studio', 'collective', 'non-profit', 'commercial-gallery', 'public-art-space', 'educational-institution', 'other'] as $type) {
            echo '<option value="' . esc_attr($type) . '" ' . selected($selected, $type, false) . '>' . ucfirst(str_replace('-', ' ', $type)) . '</option>';
        }
        echo '</select>';
    }

    public static function filter_admin_query($query) {
        global $pagenow;
        // Check if it's the main query on an admin edit.php page for the correct post type
        if (!is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query() || $query->get('post_type') !== 'artpulse_org') return; // Corrected CPT slug

        if (!empty($_GET['ead_org_type'])) { // Keep meta key for filter if it's already in use
            $query->set('meta_key', 'ead_org_type');
            $query->set('meta_value', sanitize_text_field($_GET['ead_org_type']));
        }
    }

    private static function get_registered_org_meta_fields() {
        // Note: Address fields are handled by MetaBoxesAddress.php
        return [
            'ead_org_name'        => ['text', __('Organization Name', 'artpulse-management')],
            'ead_org_description' => ['textarea', __('Description', 'artpulse-management')],
            'ead_org_type'        => ['text', __('Type (e.g. Museum, Gallery)', 'artpulse-management')], // Consider a select dropdown if types are predefined
            'ead_org_website'     => ['url', __('Website', 'artpulse-management')],
            'ead_org_logo_url'    => ['url', __('Logo Image URL', 'artpulse-management')], // Consider using Media Library ID instead
            'ead_org_banner_url'  => ['url', __('Banner Image URL', 'artpulse-management')], // Consider using Media Library ID instead
            'ead_org_geo_lat'     => ['text', __('Latitude', 'artpulse-management')], // 'number' type could also be used
            'ead_org_geo_lng'     => ['text', __('Longitude', 'artpulse-management')] // 'number' type could also be used
        ];
    }
}