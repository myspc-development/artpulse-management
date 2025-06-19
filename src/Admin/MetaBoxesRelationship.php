<?php
namespace ArtPulse\Admin;

class MetaBoxesRelationship
{
    // Define all meta boxes with keys: meta_key, title, post_type to attach to, related post_type, multiple or single select
    private static array $relationship_boxes = [
        [
            'id'           => 'ap_artist_artworks',
            'title'        => 'Associated Artworks',
            'screen'       => 'artpulse_artist', // Corrected CPT slug
            'meta_key'     => '_ap_artist_artworks',
            'related_type' => 'artpulse_artwork', // Corrected CPT slug
            'multiple'     => true,
            'description'  => 'Select artworks related to this artist.',
        ],
        [
            'id'           => 'ap_event_artworks',
            'title'        => 'Featured Artworks',
            'screen'       => 'artpulse_event', // Corrected CPT slug
            'meta_key'     => '_ap_event_artworks',
            'related_type' => 'artpulse_artwork', // Corrected CPT slug
            'multiple'     => true,
            'description'  => 'Select artworks featured in this event.',
        ],
        [
            'id'           => 'ap_event_organizations',
            'title'        => 'Participating Organizations',
            'screen'       => 'artpulse_event', // Corrected CPT slug
            'meta_key'     => '_ap_event_organizations',
            'related_type' => 'artpulse_org', // Corrected CPT slug
            'multiple'     => true,
            'description'  => 'Select organizations participating in this event.',
        ],
        [
            'id'           => 'ap_artwork_artist',
            'title'        => 'Artwork Artist',
            'screen'       => 'artpulse_artwork', // Corrected CPT slug
            'meta_key'     => '_ap_artwork_artist',
            'related_type' => 'artpulse_artist', // Corrected CPT slug
            'multiple'     => false, // Typically an artwork has one primary artist
            'description'  => 'Select the artist for this artwork.',
        ],
        [
            'id'           => 'ap_org_artists',
            'title'        => 'Associated Artists',
            'screen'       => 'artpulse_org', // Corrected CPT slug
            'meta_key'     => '_ap_org_artists',
            'related_type' => 'artpulse_artist', // Corrected CPT slug
            'multiple'     => true,
            'description'  => 'Select artists associated with this organization.',
        ],
    ];

    public static function register() {
        add_action('add_meta_boxes', [self::class, 'add_relationship_meta_boxes']);
        add_action('save_post', [self::class, 'save_relationship_meta_boxes'], 10, 1); // save_post typically passes post_id, and optionally post object and update status
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('wp_ajax_ap_search_posts', [self::class, 'ajax_search_posts']);
    }

    public static function add_relationship_meta_boxes() {
        foreach (self::$relationship_boxes as $box) {
            // Ensure the screen (post type) for the meta box exists
            if (!post_type_exists($box['screen'])) {
                // error_log("ArtPulse Relationship Meta Box: Screen post type '{$box['screen']}' does not exist for box '{$box['id']}'.");
                continue;
            }
            add_meta_box(
                $box['id'],
                __($box['title'], 'artpulse-management'),
                [self::class, 'render_relationship_meta_box'],
                $box['screen'], // This is the CPT slug where the meta box appears
                $box['multiple'] ? 'normal' : 'side', // 'side' for single select is often better UX
                'default', // 'high' or 'default'
                $box // Pass $box as callback args
            );
        }
    }

    public static function render_relationship_meta_box($post, $callback_args) {
        $box = $callback_args['args']; // $callback_args contains the meta box definition
        wp_nonce_field($box['id'] . '_nonce_action', $box['id'] . '_nonce_field');

        $selected_ids = get_post_meta($post->ID, $box['meta_key'], true);

        if ($box['multiple']) {
            if (!is_array($selected_ids)) {
                $selected_ids = empty($selected_ids) ? [] : [$selected_ids]; // Handle case where single value might be stored
            }
        } else {
            // For single select, ensure it's an integer or empty
            $selected_ids = !empty($selected_ids) ? (int)$selected_ids : '';
        }

        echo '<p>' . esc_html__($box['description'], 'artpulse-management') . '</p>';

        $multiple_attr = $box['multiple'] ? 'multiple="multiple"' : '';
        // Use the meta_key for the name attribute for easier saving, or keep box id if preferred and handle in save
        $name_attr = $box['multiple'] ? esc_attr($box['meta_key']) . '[]' : esc_attr($box['meta_key']);
        $class_attr = 'ap-related-posts regular-text'; // Add regular-text for WP styling
        $data_post_type = esc_attr($box['related_type']); // The CPT we are searching for

        echo '<select id="' . esc_attr($box['id']) . '" name="' . $name_attr . '" ' . $multiple_attr . ' style="width:100%;" class="' . esc_attr($class_attr) . '" data-post-type="' . $data_post_type . '" data-placeholder="' . __('Search for items...', 'artpulse-management') . '">';

        if ($box['multiple']) {
            if (!empty($selected_ids)) {
                foreach ($selected_ids as $related_id) {
                    $related_id = (int)$related_id;
                    if ($related_id > 0) {
                        $title = get_the_title($related_id);
                        if ($title) {
                            echo '<option value="' . esc_attr($related_id) . '" selected="selected">' . esc_html($title) . '</option>';
                        }
                    }
                }
            }
        } else { // Single select
            if (!empty($selected_ids)) {
                $title = get_the_title($selected_ids);
                if ($title) {
                    echo '<option value="' . esc_attr($selected_ids) . '" selected="selected">' . esc_html($title) . '</option>';
                }
            } else {
                 // Add a default empty option for single select if nothing is selected
                 if (!$box['multiple']) echo '<option value=""></option>';
            }
        }
        echo '</select>';
        if (empty($selected_ids) && !$box['multiple']) {
             // For single select, if nothing is selected, Select2 needs an empty option to show placeholder
             // This is a common Select2 setup. The JS will also need to handle this.
        }
    }

    public static function save_relationship_meta_boxes($post_id) {
        // Check if our nonce is set.
        // We need to iterate through each box to check its specific nonce.
        // Also, check if the save is for the correct post type.

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_id;

        // Get the post object to check its type
        $post = get_post($post_id);
        if (!$post) return $post_id;


        foreach (self::$relationship_boxes as $box) {
            // Check if this meta box is registered for the current post type being saved
            if ($post->post_type !== $box['screen']) {
                continue;
            }

            $nonce_field_name = $box['id'] . '_nonce_field';
            $nonce_action = $box['id'] . '_nonce_action';

            if (!isset($_POST[$nonce_field_name]) || !wp_verify_nonce($_POST[$nonce_field_name], $nonce_action)) {
                // error_log("Nonce verification failed for meta box: " . $box['id']);
                continue;
            }

            // Check the user's permissions.
            if ('page' == $post->post_type) {
                if (!current_user_can('edit_page', $post_id)) continue;
            } else {
                if (!current_user_can('edit_post', $post_id)) continue;
            }

            $meta_key_for_saving = $box['meta_key']; // Use the defined meta_key

            if ($box['multiple']) {
                $values = isset($_POST[$meta_key_for_saving]) && is_array($_POST[$meta_key_for_saving])
                          ? array_map('intval', $_POST[$meta_key_for_saving])
                          : [];
                // Remove empty values that might result from intval if non-numeric data was submitted
                $values = array_filter($values, function($value) { return $value > 0; });
                update_post_meta($post_id, $box['meta_key'], $values);
            } else { // Single select
                $value = isset($_POST[$meta_key_for_saving]) ? intval($_POST[$meta_key_for_saving]) : 0;
                if ($value > 0) {
                    update_post_meta($post_id, $box['meta_key'], $value);
                } else {
                    delete_post_meta($post_id, $box['meta_key']); // Delete if no valid ID is selected
                }
            }
        }
        return $post_id;
    }

    public static function enqueue_admin_assets($hook) {
        global $post; // Make sure $post is available

        // Only enqueue on post edit screens
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        // Get current post type
        $current_post_type = '';
        if ($post && isset($post->post_type)) {
            $current_post_type = $post->post_type;
        } elseif (isset($_GET['post_type'])) {
            $current_post_type = sanitize_key($_GET['post_type']);
        } elseif (isset($_GET['post'])) {
            $current_post_type = get_post_type(intval($_GET['post']));
        }


        // Check if any of our relationship boxes are registered for this screen
        $is_relevant_screen = false;
        foreach (self::$relationship_boxes as $box) {
            if ($box['screen'] === $current_post_type) {
                $is_relevant_screen = true;
                break;
            }
        }

        if (!$is_relevant_screen) {
            return;
        }

        // Enqueue Select2
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0-rc.0');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0-rc.0', true);

        // Enqueue your custom admin script for relationship boxes
        // Adjust the path to your admin-relationship.js file
        $script_url = plugins_url('assets/js/admin-relationship.js', ARTPULSE_PLUGIN_FILE); // Assuming ARTPULSE_PLUGIN_FILE is defined in your main plugin file
        if (!defined('ARTPULSE_PLUGIN_FILE')) {
             // Fallback if ARTPULSE_PLUGIN_FILE is not defined, adjust path accordingly
             // This might happen if this class is loaded before the main plugin file defines constants.
             // It's better to define ARTPULSE_PLUGIN_FILE early.
             // For now, let's assume it's two levels up from this Admin directory.
             $plugin_base_file = dirname(__DIR__, 2) . '/artpulse-management.php';
             $script_url = plugins_url('assets/js/admin-relationship.js', $plugin_base_file);
        }


        wp_enqueue_script(
            'ap-admin-relationship',
            $script_url,
            ['jquery', 'select2-js'], // Ensure select2-js is a dependency
            ARTPULSE_VERSION, // Use your plugin version constant
            true
        );

        wp_localize_script('ap-admin-relationship', 'apAdminRelationship', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ap_ajax_nonce'), // This nonce should be for your AJAX action
            'placeholder_text' => __('Search for items...', 'artpulse-management'), // For Select2 placeholder
        ]);
    }

    public static function ajax_search_posts() {
        // Verify nonce for security
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'ap_ajax_nonce')) {
            wp_send_json_error(['message' => 'Nonce verification failed.'], 403);
            return;
        }

        // Sanitize search term and post type
        $term = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $post_type_to_search = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post'; // Default to 'post' if not specified

        // Ensure the requested post type is one of our CPTs or a standard one if needed.
        // For security, you might want to restrict this to only the 'related_type' values from your $relationship_boxes.
        $allowed_post_types = array_unique(array_column(self::$relationship_boxes, 'related_type'));
        if (!in_array($post_type_to_search, $allowed_post_types, true) && !post_type_exists($post_type_to_search)) {
             wp_send_json_error(['message' => 'Invalid post type for search.'], 400);
             return;
        }


        $args = [
            'post_type'      => $post_type_to_search,
            'post_status'    => 'publish',
            'posts_per_page' => 20, // Increase for better search results
            's'              => $term,
            // Search only for IDs to speed up AJAX search.
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];

        $query = new \WP_Query($args);
        $results = [];

        foreach ($query->posts as $post_id) {
            $results[] = [
                'id'   => $post_id,
                'text' => get_the_title($post_id), // 'text' is what Select2 expects
            ];
        }

        wp_send_json_success(['results' => $results]); // Select2 expects results in a 'results' key for AJAX
    }
}