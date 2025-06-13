<?php
namespace EAD\Admin;

use EAD\Admin\EventMeta;

class MetaBoxesEvent {

    public static function register() {
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
        add_action('save_post_ead_event', [self::class, 'save_meta_boxes'], 10, 2);
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'ead_event_details',
            __('Event Details', 'artpulse-management'),
            [self::class, 'render_meta_box'],
            'ead_event',
            'normal',
            'default'
        );
    }

    public static function render_meta_box($post) {
        wp_nonce_field('ead_event_meta_nonce', 'ead_event_meta_nonce');

        $fields = [
            'event_start_date'        => ['type' => 'date', 'label' => __('Start Date', 'artpulse-management')],
            'event_end_date'          => ['type' => 'date', 'label' => __('End Date', 'artpulse-management')],
            'venue_name'              => ['type' => 'text', 'label' => __('Venue Name', 'artpulse-management')],
            'event_street_address'    => ['type' => 'text', 'label' => __('Street Address', 'artpulse-management')],
            'event_city'              => ['type' => 'text', 'label' => __('City', 'artpulse-management')],
            'event_state'             => ['type' => 'text', 'label' => __('State', 'artpulse-management')],
            'event_country'           => ['type' => 'text', 'label' => __('Country', 'artpulse-management')],
            'event_postcode'          => ['type' => 'text', 'label' => __('Postcode', 'artpulse-management')],
            'event_organizer_name'    => ['type' => 'text', 'label' => __('Organizer Name', 'artpulse-management')],
            'event_organizer_email'   => ['type' => 'email', 'label' => __('Organizer Email', 'artpulse-management')],
            'event_banner_id'         => ['type' => 'media', 'label' => __('Event Banner', 'artpulse-management')],
            'event_featured'          => ['type' => 'checkbox', 'label' => __('Request Featured', 'artpulse-management')],
        ];

        // Load meta values
        $meta = [];
        foreach ($fields as $field => $args) {
            $meta[$field] = get_post_meta($post->ID, $field, true);
        }

        echo '<table class="form-table">';

        // Taxonomy Dropdown (Event Type)
        $terms = get_terms([
            'taxonomy'   => 'ead_event_type',
            'hide_empty' => false,
        ]);
        $current_terms = wp_get_post_terms($post->ID, 'ead_event_type', ['fields' => 'ids']);
        $current_term_id = (!empty($current_terms)) ? $current_terms[0] : 0;

        echo '<tr>';
        echo '<th><label for="event_type">' . esc_html__('Event Type', 'artpulse-management') . '</label></th>';
        echo '<td>';
        echo '<select id="event_type" name="event_type" style="width:100%;">';
        echo '<option value="">' . esc_html__('-- Select Type --', 'artpulse-management') . '</option>';
        foreach ($terms as $term) {
            printf(
                '<option value="%d" %s>%s</option>',
                esc_attr($term->term_id),
                selected($term->term_id, $current_term_id, false),
                esc_html($term->name)
            );
        }
        echo '</select>';
        echo '</td></tr>';

        // Loop through other fields
        foreach ($fields as $field => $args) {
            echo '<tr>';
            echo '<th><label for="' . esc_attr($field) . '">' . esc_html($args['label']) . '</label></th>';
            echo '<td>';
            switch ($args['type']) {
                case 'textarea':
                    echo '<textarea id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" rows="4" style="width:100%;">' . esc_textarea($meta[$field]) . '</textarea>';
                    break;

                case 'date':
                    echo '<input type="date" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" value="' . esc_attr($meta[$field]) . '" style="width:100%;">';
                    break;

                case 'email':
                    echo '<input type="email" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" value="' . esc_attr($meta[$field]) . '" style="width:100%;">';
                    break;

                case 'media':
                    $image_id = intval($meta[$field]);
                    $image_src = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
                    echo '<input type="hidden" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" value="' . esc_attr($image_id) . '">';
                    if ($image_src) {
                        echo '<img src="' . esc_url($image_src) . '" style="max-width: 200px; display: block; margin-bottom: 10px;">';
                    }
                    echo '<button type="button" class="button button-primary ead-media-upload" data-field="' . esc_attr($field) . '">' . __('Upload/Choose Image', 'artpulse-management') . '</button>';
                    echo '<button type="button" class="button button-secondary ead-media-remove" data-field="' . esc_attr($field) . '" style="margin-left: 10px;">' . __('Remove Image', 'artpulse-management') . '</button>';
                    break;

                case 'checkbox':
                    $checked = !empty($meta[$field]) ? 'checked' : '';
                    echo '<label><input type="checkbox" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" value="1" ' . $checked . '> ' . __('Yes', 'artpulse-management') . '</label>';
                    break;

                default:
                    echo '<input type="' . esc_attr($args['type']) . '" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" value="' . esc_attr($meta[$field]) . '" style="width:100%;">';
                    break;
            }
            echo '</td></tr>';
        }

        echo '</table>';
        ?>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.ead-media-upload').click(function(e) {
                    e.preventDefault();
                    const field = $(this).data('field');
                    const custom_uploader = wp.media({
                        title: '<?php esc_html_e('Choose Image', 'artpulse-management'); ?>',
                        button: { text: '<?php esc_html_e('Use this image', 'artpulse-management'); ?>' },
                        multiple: false
                    }).on('select', function() {
                        const attachment = custom_uploader.state().get('selection').first().toJSON();
                        $('#' + field).val(attachment.id);
                        $('#' + field).siblings('img').attr('src', attachment.url).show();
                    }).open();
                });

                $('.ead-media-remove').click(function(e) {
                    e.preventDefault();
                    const field = $(this).data('field');
                    $('#' + field).val('');
                    $('#' + field).siblings('img').attr('src', '').hide();
                });
            });
        </script>
        <?php
    }

    public static function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['ead_event_meta_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ead_event_meta_nonce'])), 'ead_event_meta_nonce')) {
            return;
        }

        if ($post->post_type !== 'ead_event' || !current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = [
            'event_start_date', 'event_end_date', 'venue_name', 'event_street_address',
            'event_city', 'event_state', 'event_country', 'event_postcode',
            'event_organizer_name', 'event_organizer_email', 'event_banner_id', 'event_featured'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                switch ($field) {
                    case 'event_start_date':
                    case 'event_end_date':
                        $sanitized_value = EventMeta::sanitize_date(wp_unslash($value));
                        break;
                    case 'event_organizer_email':
                        $sanitized_value = sanitize_email(wp_unslash($value));
                        break;
                    case 'event_banner_id':
                        $sanitized_value = absint(wp_unslash($value));
                        break;
                    case 'event_featured':
                        $sanitized_value = EventMeta::sanitize_boolean(wp_unslash($value));
                        break;
                    default:
                        $sanitized_value = sanitize_text_field(wp_unslash($value));
                        break;
                }
                update_post_meta($post_id, $field, $sanitized_value);
            } else {
                delete_post_meta($post_id, $field);
            }
        }

        // Save taxonomy
        if (isset($_POST['event_type'])) {
            $term_id = intval($_POST['event_type']);
            if ($term_id > 0) {
                wp_set_object_terms($post_id, $term_id, 'ead_event_type');
            } else {
                wp_set_object_terms($post_id, null, 'ead_event_type');
            }
        }
    }
}
