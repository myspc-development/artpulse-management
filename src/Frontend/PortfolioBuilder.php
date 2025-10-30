<?php

namespace ArtPulse\Frontend;

class PortfolioBuilder
{
    public static function register()
    {
        add_shortcode('ap_portfolio_builder', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('wp_ajax_ap_save_portfolio', [self::class, 'handle_form']);
        add_action('wp_ajax_ap_get_portfolio_item', [self::class, 'get_item']);
        add_action('wp_ajax_ap_toggle_visibility', [self::class, 'toggle_visibility']);
        add_action('wp_ajax_ap_delete_portfolio_item', [self::class, 'delete_item']);
        //add_action('rest_api_init', [self::class, 'register_rest_routes']); // REMOVE THIS LINE
        add_action('rest_api_init', function () { // Add this block
            register_rest_route('artpulse/v1', '/portfolio/(?P<user_id>\d+)', [
                'methods'             => 'GET',
                'callback'            => [self::class, 'rest_get_portfolio'],
                'permission_callback' => '__return_true',
                'args' => [
                    'user_id' => [
                        'validate_callback' => 'is_numeric',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]);
        });
    }

    public static function enqueue_scripts()
    {
        if (!is_user_logged_in()) return;

        wp_enqueue_media();

        wp_enqueue_script(
            'ap-portfolio-builder',
            plugins_url('/assets/js/ap-portfolio-builder.js', ARTPULSE_PLUGIN_FILE),
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('ap-portfolio-builder', 'APPortfolio', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ap_portfolio_nonce'),
        ]);
    }

    public static function render()
    {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to manage your portfolio.</p>';
        }

        ob_start();
        ?>
        <div class="ap-form-messages" role="status" aria-live="polite"></div>
        <form id="ap-portfolio-form">
            <h3>Create or Edit Portfolio Item</h3>
            <input type="hidden" name="post_id" value="" />
            <p><label for="ap_portfolio_title">Title</label><br><input id="ap_portfolio_title" type="text" name="title" required /></p>
            <p><label for="ap_portfolio_description">Description</label><br><textarea id="ap_portfolio_description" name="description" rows="3"></textarea></p>
            <p><label for="ap_portfolio_category">Category</label><br>
                <select id="ap_portfolio_category" name="category">
                    <option value="painting">Painting</option>
                    <option value="exhibition">Exhibition</option>
                    <option value="award">Award</option>
                </select>
            </p>
            <p><label for="ap_portfolio_link">Link (optional)</label><br><input id="ap_portfolio_link" type="url" name="link" /></p>
            <p><label for="ap_portfolio_visibility">Visibility</label><br>
                <select id="ap_portfolio_visibility" name="visibility">
                    <option value="public">Public</option>
                    <option value="private">Private</option>
                </select>
            </p>
            <p>
                <button type="button" id="ap-upload-image">Upload Image</button><br>
                <img id="ap-preview" style="max-width: 200px; display:none;" />
                <input type="hidden" name="image" />
            </p>
            <p><button type="submit">Save Portfolio Item</button></p>
            <p id="ap-portfolio-message" class="ap-form-messages" role="status" aria-live="polite" style="color:green;"></p>
        </form>
        <hr>

        <h3>Your Saved Portfolio Items</h3>
        <div id="ap-saved-items">
            <?php
            $items = get_posts([
                'post_type'   => 'portfolio',
                'author'      => get_current_user_id(),
                'post_status' => 'publish',
                'numberposts' => -1,
            ]);

            foreach ($items as $item) :
                $visibility = get_post_meta($item->ID, 'portfolio_visibility', true) ?: 'public';
                $desc = get_post_meta($item->ID, 'portfolio_description', true);
                ?>
                <div class="ap-saved-item" data-id="<?php echo esc_attr($item->ID); ?>">
                    <strong><?php echo esc_html($item->post_title); ?></strong>
                    <p><?php echo esc_html($desc); ?></p>
                    <p>
                        <button class="edit-item">Edit</button>
                        <button class="toggle-visibility" data-new="<?php echo $visibility === 'private' ? 'public' : 'private'; ?>">
                            <?php echo ucfirst($visibility); ?>
                        </button>
                        <button class="delete-item">Delete</button>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function handle_form()
    {
        check_ajax_referer('ap_portfolio_nonce', 'nonce');

        $post_id = intval($_POST['post_id'] ?? 0);
        $user_id = get_current_user_id();
        $title = sanitize_text_field($_POST['title']);
        $desc = sanitize_text_field($_POST['description']);
        $cat = sanitize_text_field($_POST['category']);
        $link = esc_url_raw($_POST['link']);
        $visibility = sanitize_text_field($_POST['visibility']);
        $image = esc_url_raw($_POST['image']);

        if ($post_id && get_post_field('post_author', $post_id) == $user_id) {
            wp_update_post([
                'ID'         => $post_id,
                'post_title' => $title,
            ]);
        } else {
            $post_id = wp_insert_post([
                'post_type'   => 'portfolio',
                'post_title'  => $title,
                'post_status' => 'publish',
                'post_author' => $user_id,
            ]);
        }

        if (!$post_id || is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'Failed to save portfolio item.']);
        }

        wp_set_post_terms($post_id, [$cat], 'portfolio_category');
        update_post_meta($post_id, 'portfolio_description', $desc);
        update_post_meta($post_id, 'portfolio_link', $link);
        update_post_meta($post_id, 'portfolio_visibility', $visibility);
        update_post_meta($post_id, 'portfolio_image', $image);

        wp_send_json_success(['message' => 'Saved successfully.']);
    }

    public static function get_item()
    {
        check_ajax_referer('ap_portfolio_nonce', 'nonce');

        $id = intval($_GET['post_id']);
        $post = get_post($id);

        if (!$post || $post->post_author != get_current_user_id()) {
            wp_send_json_error('Not found or unauthorized');
        }

        wp_send_json_success([
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => get_post_meta($post->ID, 'portfolio_description', true),
            'link' => get_post_meta($post->ID, 'portfolio_link', true),
            'visibility' => get_post_meta($post->ID, 'portfolio_visibility', true),
            'image' => get_post_meta($post->ID, 'portfolio_image', true),
            'category' => wp_get_post_terms($post->ID, 'portfolio_category', ['fields' => 'slugs'])[0] ?? '',
        ]);
    }

    public static function toggle_visibility()
    {
        check_ajax_referer('ap_portfolio_nonce', 'nonce');

        $id = intval($_POST['post_id']);
        $new = sanitize_text_field($_POST['visibility']);

        if (get_post_field('post_author', $id) != get_current_user_id()) {
            wp_send_json_error('Not allowed');
        }

        update_post_meta($id, 'portfolio_visibility', $new);
        wp_send_json_success();
    }

    public static function delete_item()
    {
        check_ajax_referer('ap_portfolio_nonce', 'nonce');

        $id = intval($_POST['post_id']);

        if (get_post_field('post_author', $id) != get_current_user_id()) {
            wp_send_json_error('Not allowed');
        }

        wp_delete_post($id, true);
        wp_send_json_success();
    }

    //public static function register_rest_routes() // REMOVE THIS FUNCTION DEFINITION
    //{
    //    register_rest_route('artpulse/v1', '/portfolio/(?P<user_id>\d+)', [
    //        'methods'             => 'GET',
    //        'callback'            => [self::class, 'rest_get_portfolio'],
    //        'permission_callback' => '__return_true',
    //        'args' => [
    //            'user_id' => [
    //                'validate_callback' => 'is_numeric',
    //                'sanitize_callback' => 'absint',
    //            ],
    //        ],
    //    ]);
    //}

    public static function rest_get_portfolio($request)
    {
        $user_id = absint($request['user_id']);

        $items = get_posts([
            'post_type'   => 'portfolio',
            'author'      => $user_id,
            'meta_key'    => 'portfolio_visibility',
            'meta_value'  => 'public',
            'numberposts' => -1,
        ]);

        $data = array_map(function ($post) {
            return [
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'description' => get_post_meta($post->ID, 'portfolio_description', true),
                'link'        => get_post_meta($post->ID, 'portfolio_link', true),
                'image'       => get_post_meta($post->ID, 'portfolio_image', true),
                'category'    => wp_get_post_terms($post->ID, 'portfolio_category', ['fields' => 'names'])[0] ?? '',
            ];
        }, $items);

        return rest_ensure_response($data);
    }
}