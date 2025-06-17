<?php
namespace ArtPulse\Admin;

class MetaBoxesRelationship
{
    // Define all meta boxes with keys: meta_key, title, post_type to attach to, related post_type, multiple or single select
    private static array $relationship_boxes = [
        [
            'id'           => 'ap_artist_artworks',
            'title'        => 'Associated Artworks',
            'screen'       => 'ead_artist',
            'meta_key'     => '_ap_artist_artworks',
            'related_type' => 'ead_artwork',
            'multiple'     => true,
            'description'  => 'Select artworks related to this artist.',
        ],
        [
            'id'           => 'ap_event_artworks',
            'title'        => 'Featured Artworks',
            'screen'       => 'ead_event',
            'meta_key'     => '_ap_event_artworks',
            'related_type' => 'ead_artwork',
            'multiple'     => true,
            'description'  => 'Select artworks featured in this event.',
        ],
        [
            'id'           => 'ap_event_organizations',
            'title'        => 'Participating Organizations',
            'screen'       => 'ead_event',
            'meta_key'     => '_ap_event_organizations',
            'related_type' => 'ead_organization',
            'multiple'     => true,
            'description'  => 'Select organizations participating in this event.',
        ],
        [
            'id'           => 'ap_artwork_artist',
            'title'        => 'Artwork Artist',
            'screen'       => 'ead_artwork',
            'meta_key'     => '_ap_artwork_artist',
            'related_type' => 'ead_artist',
            'multiple'     => false,
            'description'  => 'Select the artist for this artwork.',
        ],
        [
            'id'           => 'ap_org_artists',
            'title'        => 'Associated Artists',
            'screen'       => 'ead_organization',
            'meta_key'     => '_ap_org_artists',
            'related_type' => 'ead_artist',
            'multiple'     => true,
            'description'  => 'Select artists associated with this organization.',
        ],
    ];

    public static function register() {
        add_action('add_meta_boxes', [self::class, 'add_relationship_meta_boxes']);
        add_action('save_post', [self::class, 'save_relationship_meta_boxes']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('wp_ajax_ap_search_posts', [self::class, 'ajax_search_posts']);
    }

    public static function add_relationship_meta_boxes() {
        foreach (self::$relationship_boxes as $box) {
            add_meta_box(
                $box['id'],
                __($box['title'], 'artpulse-management'),
                [self::class, 'render_relationship_meta_box'],
                $box['screen'],
                $box['multiple'] ? 'normal' : 'side',
                'default',
                $box // Pass $box as callback args
            );
        }
    }

    public static function render_relationship_meta_box($post, $args) {
        $box = $args['args'];
        wp_nonce_field($box['id'] . '_nonce_action', $box['id'] . '_nonce_field');

        $selected = get_post_meta($post->ID, $box['meta_key'], true);
        if ($box['multiple']) {
            if (!is_array($selected)) {
                $selected = [];
            }
        } else {
            $selected = (int)$selected;
        }

        echo '<p>' . esc_html__($box['description'], 'artpulse-management') . '</p>';

        // Set select attributes and values
        $multiple_attr = $box['multiple'] ? 'multiple="multiple"' : '';
        $name_attr = $box['multiple'] ? $box['id'] . '[]' : $box['id'];
        $class_attr = 'ap-related-posts';
        $post_type = esc_attr($box['related_type']);

        echo '<select id="' . esc_attr($box['id']) . '" name="' . esc_attr($name_attr) . '" ' . $multiple_attr . ' style="width:100%;" class="' . esc_attr($class_attr) . '" data-post-type="' . $post_type . '">';

        if ($box['multiple']) {
            foreach ($selected as $related_id) {
                $title = get_the_title($related_id);
                if ($title) {
                    echo '<option value="' . esc_attr($related_id) . '" selected="selected">' . esc_html($title) . '</option>';
                }
            }
        } else {
            if ($selected) {
                $title = get_the_title($selected);
                if ($title) {
                    echo '<option value="' . esc_attr($selected) . '" selected="selected">' . esc_html($title) . '</option>';
                }
            }
        }

        echo '</select>';
    }

    public static function save_relationship_meta_boxes($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        foreach (self::$relationship_boxes as $box) {
            $nonce_field = $box['id'] . '_nonce_field';
            $nonce_action = $box['id'] . '_nonce_action';

            if (!isset($_POST[$nonce_field]) || !wp_verify_nonce($_POST[$nonce_field], $nonce_action)) {
                continue;
            }

            if ($box['multiple']) {
                $values = $_POST[$box['id']] ?? [];
                $values = array_map('intval', (array)$values);
                update_post_meta($post_id, $box['meta_key'], $values);
            } else {
                $value = isset($_POST[$box['id']]) ? intval($_POST[$box['id']]) : 0;
                update_post_meta($post_id, $box['meta_key'], $value);
            }
        }
    }

    public static function enqueue_admin_assets($hook) {
        global $post;
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        $post_type = get_post_type($post);
        $screens = array_column(self::$relationship_boxes, 'screen');

        if (!in_array($post_type, $screens, true)) {
            return;
        }

        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css', [], '4.1.0');

        wp_enqueue_script(
            'ap-admin-relationship',
            plugins_url('assets/js/admin-relationship.js', dirname(__DIR__, 3) . '/artpulse-management.php'),
            ['jquery', 'select2'],
            '1.0',
            true
        );

        wp_localize_script('ap-admin-relationship', 'apAdminRelationship', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ap_ajax_nonce'),
        ]);
    }

    public static function ajax_search_posts() {
        check_ajax_referer('ap_ajax_nonce', 'nonce');

        $term = sanitize_text_field($_GET['q'] ?? '');
        $post_type = sanitize_text_field($_GET['post_type'] ?? 'ead_artwork');

        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            's'              => $term,
        ];

        $query = new \WP_Query($args);

        $results = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results[] = [
                    'id'   => get_the_ID(),
                    'text' => get_the_title(),
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json(['results' => $results]);
    }
}
