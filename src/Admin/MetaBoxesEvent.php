<?php
namespace ArtPulse\Admin;

class MetaBoxesEvent {

    public static function register() {
        add_action('add_meta_boxes', [self::class, 'add_event_meta_boxes']);
        add_action('save_post_ead_event', [self::class, 'save_event_meta'], 10, 2);
        add_action('rest_api_init', [self::class, 'register_rest_fields']);
        add_action('restrict_manage_posts', [self::class, 'add_admin_filters']);
        add_filter('pre_get_posts', [self::class, 'filter_admin_query']);
    }

    public static function add_event_meta_boxes() {
        add_meta_box(
            'ead_event_details',
            __('Event Details', 'artpulse-management'),
            [self::class, 'render_event_details'],
            'ead_event',
            'normal',
            'high'
        );
    }

    public static function render_event_details($post) {
        wp_nonce_field('ead_event_meta_nonce', 'ead_event_meta_nonce_field');

        $fields = self::get_registered_event_meta_fields();

        echo '<table class="form-table">';
        foreach ($fields as $key => $args) {
            $type = $args['type'];
            $label = $args['label'];
            $value = get_post_meta($post->ID, $key, true);
            echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            switch ($type) {
                case 'date':
                case 'email':
                case 'text':
                    echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                    break;
                case 'checkbox':
                    echo '<input type="checkbox" name="' . esc_attr($key) . '" value="1" ' . checked($value, '1', false) . ' />';
                    break;
                case 'media':
                    echo '<input type="number" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                    break;
                default:
                    echo '<input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
            }
            echo '</td></tr>';
        }
        echo '</table>';
    }

    public static function save_event_meta($post_id, $post) {
        if (!isset($_POST['ead_event_meta_nonce_field']) || !wp_verify_nonce($_POST['ead_event_meta_nonce_field'], 'ead_event_meta_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'ead_event') return;

        $fields = self::get_registered_event_meta_fields();
        foreach ($fields as $field => $args) {
            $value = $_POST[$field] ?? '';
            if ($args['type'] === 'checkbox') {
                $value = isset($_POST[$field]) ? '1' : '0';
            } elseif ($args['type'] === 'email' && !is_email($value)) {
                continue;
            }
            update_post_meta($post_id, $field, sanitize_text_field($value));
        }
    }

    private static function get_registered_event_meta_fields() {
        return [
            'event_start_date'      => ['type' => 'date', 'label' => __('Start Date', 'artpulse-management')],
            'event_end_date'        => ['type' => 'date', 'label' => __('End Date', 'artpulse-management')],
            'venue_name'            => ['type' => 'text', 'label' => __('Venue Name', 'artpulse-management')],
            'event_street_address'  => ['type' => 'text', 'label' => __('Street Address', 'artpulse-management')],
            'event_city'            => ['type' => 'text', 'label' => __('City', 'artpulse-management')],
            'event_state'           => ['type' => 'text', 'label' => __('State', 'artpulse-management')],
            'event_country'         => ['type' => 'text', 'label' => __('Country', 'artpulse-management')],
            'event_postcode'        => ['type' => 'text', 'label' => __('Postcode', 'artpulse-management')],
            'event_organizer_name'  => ['type' => 'text', 'label' => __('Organizer Name', 'artpulse-management')],
            'event_organizer_email' => ['type' => 'email', 'label' => __('Organizer Email', 'artpulse-management')],
            'event_banner_id'       => ['type' => 'media', 'label' => __('Event Banner', 'artpulse-management')],
            'event_featured'        => ['type' => 'checkbox', 'label' => __('Request Featured', 'artpulse-management')],
        ];
    }

    public static function register_rest_fields() {
        foreach (self::get_registered_event_meta_fields() as $field => $args) {
            register_rest_field('ead_event', $field, [
                'get_callback'    => fn($object) => get_post_meta($object['id'], $field, true),
                'update_callback' => fn($value, $object) => update_post_meta($object->ID, $field, sanitize_text_field($value)),
                'schema'          => ['type' => 'string'],
            ]);
        }
    }

    public static function add_admin_filters() {
        if (get_current_screen()->post_type !== 'ead_event') return;
        $selected = $_GET['event_featured'] ?? '';
        echo '<select name="event_featured">
            <option value="">' . __('Filter by Featured', 'artpulse-management') . '</option>
            <option value="1"' . selected($selected, '1', false) . '>Yes</option>
            <option value="0"' . selected($selected, '0', false) . '>No</option>
        </select>';
    }

    public static function filter_admin_query($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'ead_event') return;

        if (isset($_GET['event_featured']) && $_GET['event_featured'] !== '') {
            $query->set('meta_key', 'event_featured');
            $query->set('meta_value', $_GET['event_featured']);
        }
    }
}
