<?php

namespace ArtPulse\Frontend;

use WP_Post;

class PortfolioBuilder
{
    private const POST_TYPE = 'artpulse_portfolio';
    private const META_VISIBILITY = '_ap_visibility';
    private const META_LINK = '_ap_portfolio_link';
    private const META_OWNER = '_ap_owner_user';

    private const ALLOWED_VISIBILITY = ['public', 'members', 'private'];

    public static function register()
    {
        add_shortcode('ap_portfolio_builder', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('wp_ajax_ap_save_portfolio', [self::class, 'handle_form']);
        add_action('wp_ajax_ap_get_portfolio_item', [self::class, 'get_item']);
        add_action('wp_ajax_ap_toggle_visibility', [self::class, 'toggle_visibility']);
        add_action('wp_ajax_ap_delete_portfolio_item', [self::class, 'delete_item']);
    }

    public static function enqueue_scripts()
    {
        if (!is_user_logged_in()) return;

        wp_enqueue_script(
            'ap-portfolio-builder',
            plugins_url('/assets/js/ap-portfolio-builder.js', ARTPULSE_PLUGIN_FILE),
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('ap-portfolio-builder', 'APPortfolio', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('ap_portfolio_nonce'),
            'visibilities' => self::ALLOWED_VISIBILITY,
        ]);
    }

    public static function render()
    {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to manage your portfolio.</p>';
        }

        $user_id = get_current_user_id();
        $categories = get_terms([
            'taxonomy'   => 'portfolio_category',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        ob_start();
        ?>
        <div class="ap-form-messages" role="status" aria-live="polite"></div>
        <form id="ap-portfolio-form">
            <h3>Create or Edit Portfolio Item</h3>
            <input type="hidden" name="post_id" value="" />
            <p><label for="ap_portfolio_title">Title</label><br><input id="ap_portfolio_title" type="text" name="title" required /></p>
            <p><label for="ap_portfolio_description">Description</label><br><textarea id="ap_portfolio_description" name="description" rows="4"></textarea></p>
            <p><label for="ap_portfolio_category">Category</label><br>
                <select id="ap_portfolio_category" name="category">
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $term) : ?>
                        <option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p><label for="ap_portfolio_link">Link (optional)</label><br><input id="ap_portfolio_link" type="url" name="link" /></p>
            <p><label for="ap_portfolio_visibility">Visibility</label><br>
                <select id="ap_portfolio_visibility" name="visibility">
                    <option value="public">Public</option>
                    <option value="members">Members Only</option>
                    <option value="private">Private</option>
                </select>
            </p>
            <p><button type="submit">Save Portfolio Item</button></p>
            <p id="ap-portfolio-message" class="ap-form-messages" role="status" aria-live="polite" style="color:green;"></p>
        </form>
        <hr>

        <h3>Your Saved Portfolio Items</h3>
        <div id="ap-saved-items">
            <?php
            $items = get_posts([
                'post_type'   => [self::POST_TYPE, 'portfolio'],
                'author'      => $user_id,
                'post_status' => ['publish', 'pending', 'draft', 'future', 'private'],
                'numberposts' => -1,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ]);

            foreach ($items as $item) :
                if (!$item instanceof WP_Post) {
                    continue;
                }

                $visibility = get_post_meta($item->ID, self::META_VISIBILITY, true);
                if ('' === $visibility) {
                    $visibility = get_post_meta($item->ID, 'portfolio_visibility', true) ?: 'public';
                }
                $status_meta = self::describe_status($item->post_status);
                $description_source = $item->post_content;
                $legacy_description = get_post_meta($item->ID, 'portfolio_description', true);
                if ($legacy_description !== '') {
                    $description_source = $legacy_description;
                }
                $desc = wp_trim_words(wp_strip_all_tags($description_source), 24, '…');
                ?>
                <div class="ap-saved-item" data-id="<?php echo esc_attr($item->ID); ?>" data-status="<?php echo esc_attr($item->post_status); ?>">
                    <strong><?php echo esc_html(get_the_title($item)); ?></strong>
                    <p class="ap-saved-item__meta">
                        <span class="ap-saved-item__status ap-saved-item__status--<?php echo esc_attr($status_meta['status']); ?>"><?php echo esc_html($status_meta['label']); ?></span>
                        <span class="ap-saved-item__visibility"><?php echo esc_html(ucfirst($visibility)); ?></span>
                    </p>
                    <?php if (!empty($desc)) : ?>
                        <p><?php echo esc_html($desc); ?></p>
                    <?php endif; ?>
                    <p>
                        <button class="edit-item">Edit</button>
                        <button class="toggle-visibility" data-new="<?php echo esc_attr(self::next_visibility($visibility)); ?>">
                            Set <?php echo esc_html(ucfirst(self::next_visibility($visibility))); ?>
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

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to manage portfolio items.'], 403);
        }

        $user_id = get_current_user_id();
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if ($post_id > 0 && !self::user_can_manage_post($post_id, $user_id)) {
            wp_send_json_error(['message' => 'You are not allowed to edit this item.'], 403);
        }

        if ($post_id === 0 && !self::current_user_can_create()) {
            wp_send_json_error(['message' => 'You are not allowed to create portfolio items.'], 403);
        }

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        if ('' === $title) {
            wp_send_json_error(['message' => 'Title is required.'], 422);
        }

        $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
        $category    = isset($_POST['category']) ? sanitize_key(wp_unslash($_POST['category'])) : '';
        $link_value  = isset($_POST['link']) ? trim((string) wp_unslash($_POST['link'])) : '';
        $visibility  = isset($_POST['visibility']) ? self::sanitize_visibility(wp_unslash($_POST['visibility'])) : 'public';

        $post_status = self::status_for_visibility($visibility);
        $link        = '';

        if ($link_value !== '') {
            $maybe_url = esc_url_raw($link_value);
            if ($maybe_url && wp_http_validate_url($maybe_url)) {
                $link = $maybe_url;
            }
        }

        if ($post_id > 0) {
            $existing = get_post($post_id);
            if (!$existing instanceof WP_Post) {
                wp_send_json_error(['message' => 'Portfolio item not found.'], 404);
            }

            $result = wp_update_post([
                'ID'           => $post_id,
                'post_title'   => $title,
                'post_content' => $description,
                'post_status'  => $post_status,
                'post_type'    => self::POST_TYPE,
            ], true);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => 'Failed to update portfolio item.']);
            }
        } else {
            $post_id = wp_insert_post([
                'post_type'    => self::POST_TYPE,
                'post_title'   => $title,
                'post_content' => $description,
                'post_status'  => $post_status,
                'post_author'  => $user_id,
            ], true);

            if (is_wp_error($post_id) || !$post_id) {
                wp_send_json_error(['message' => 'Failed to save portfolio item.']);
            }

            update_post_meta($post_id, self::META_OWNER, $user_id);
        }

        if ($category !== '') {
            wp_set_post_terms($post_id, [$category], 'portfolio_category', false);
        } else {
            wp_set_post_terms($post_id, [], 'portfolio_category', false);
        }

        update_post_meta($post_id, self::META_VISIBILITY, $visibility);
        update_post_meta($post_id, 'portfolio_visibility', $visibility);
        update_post_meta($post_id, self::META_LINK, $link);
        update_post_meta($post_id, 'portfolio_link', $link);
        update_post_meta($post_id, 'portfolio_description', wp_strip_all_tags($description));

        $owner = (int) get_post_meta($post_id, self::META_OWNER, true);
        if ($owner <= 0) {
            update_post_meta($post_id, self::META_OWNER, $user_id);
        }

        wp_send_json_success(['message' => 'Saved successfully.']);
    }

    public static function get_item()
    {
        check_ajax_referer('ap_portfolio_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Not found or unauthorized', 403);
        }

        $id   = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $post = $id > 0 ? get_post($id) : null;

        if (!$post instanceof WP_Post || !in_array($post->post_type, [self::POST_TYPE, 'portfolio'], true)) {
            wp_send_json_error('Not found or unauthorized', 404);
        }

        if (!self::user_can_manage_post($post->ID, get_current_user_id())) {
            wp_send_json_error('Not found or unauthorized', 403);
        }

        $visibility = get_post_meta($post->ID, self::META_VISIBILITY, true);
        if ('' === $visibility) {
            $visibility = get_post_meta($post->ID, 'portfolio_visibility', true) ?: 'public';
        }

        $description = $post->post_content;
        if ('' === $description) {
            $description = (string) get_post_meta($post->ID, 'portfolio_description', true);
        }

        wp_send_json_success([
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'description' => $description,
            'link'        => get_post_meta($post->ID, self::META_LINK, true) ?: get_post_meta($post->ID, 'portfolio_link', true),
            'visibility'  => $visibility,
            'category'    => wp_get_post_terms($post->ID, 'portfolio_category', ['fields' => 'slugs'])[0] ?? '',
            'status'      => $post->post_status,
        ]);
    }

    public static function toggle_visibility()
    {
        check_ajax_referer('ap_portfolio_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Not allowed', 403);
        }

        $id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $post = $id > 0 ? get_post($id) : null;

        if (!$post instanceof WP_Post || !in_array($post->post_type, [self::POST_TYPE, 'portfolio'], true)) {
            wp_send_json_error('Not allowed', 403);
        }

        if (!self::user_can_manage_post($post->ID, get_current_user_id())) {
            wp_send_json_error('Not allowed', 403);
        }

        $new_visibility = isset($_POST['visibility']) ? self::sanitize_visibility(wp_unslash($_POST['visibility'])) : 'private';

        update_post_meta($post->ID, self::META_VISIBILITY, $new_visibility);
        update_post_meta($post->ID, 'portfolio_visibility', $new_visibility);

        $new_status = self::status_for_visibility($new_visibility);
        $update = [];
        if ($new_status !== $post->post_status) {
            $update['post_status'] = $new_status;
        }

        if ($post->post_type !== self::POST_TYPE) {
            $update['post_type'] = self::POST_TYPE;
        }

        if (!empty($update)) {
            $update['ID'] = $post->ID;
            wp_update_post($update);
        }

        wp_send_json_success([
            'visibility' => $new_visibility,
            'status'     => $new_status,
        ]);
    }

    public static function delete_item()
    {
        check_ajax_referer('ap_portfolio_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Not allowed', 403);
        }

        $id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $post = $id > 0 ? get_post($id) : null;

        if (!$post instanceof WP_Post || !in_array($post->post_type, [self::POST_TYPE, 'portfolio'], true)) {
            wp_send_json_error('Not allowed', 403);
        }

        if (!current_user_can('delete_post', $post->ID)) {
            wp_send_json_error('Not allowed', 403);
        }

        wp_trash_post($post->ID);
        wp_send_json_success();
    }

    public static function rest_get_portfolio($request)
    {
        $user_id = absint($request['user_id']);

        $items = get_posts([
            'post_type'   => self::POST_TYPE,
            'author'      => $user_id,
            'meta_key'    => self::META_VISIBILITY,
            'meta_value'  => 'public',
            'numberposts' => -1,
        ]);

        $data = array_map(function ($post) {
            return [
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'description' => $post->post_content,
                'link'        => get_post_meta($post->ID, self::META_LINK, true),
                'category'    => wp_get_post_terms($post->ID, 'portfolio_category', ['fields' => 'names'])[0] ?? '',
            ];
        }, $items);

        return rest_ensure_response($data);
    }

    private static function sanitize_visibility($value): string
    {
        $value = sanitize_key((string) $value);
        if (!in_array($value, self::ALLOWED_VISIBILITY, true)) {
            return 'private';
        }

        return $value;
    }

    private static function status_for_visibility(string $visibility): string
    {
        switch ($visibility) {
            case 'public':
                return 'publish';
            case 'members':
                return 'pending';
            default:
                return 'draft';
        }
    }

    private static function describe_status(string $status): array
    {
        $map = [
            'publish' => ['status' => 'published', 'label' => 'Published'],
            'pending' => ['status' => 'pending', 'label' => 'Pending Review'],
            'future'  => ['status' => 'scheduled', 'label' => 'Scheduled'],
            'private' => ['status' => 'private', 'label' => 'Private'],
            'draft'   => ['status' => 'draft', 'label' => 'Draft'],
        ];

        return $map[$status] ?? ['status' => 'draft', 'label' => ucfirst($status) ?: 'Draft'];
    }

    private static function next_visibility(string $current): string
    {
        $current = in_array($current, self::ALLOWED_VISIBILITY, true) ? $current : 'private';

        $order = ['public', 'members', 'private'];
        $index = array_search($current, $order, true);
        $next  = ($index === false) ? 0 : ($index + 1) % count($order);

        return $order[$next];
    }

    private static function user_can_manage_post(int $post_id, int $user_id): bool
    {
        if ($post_id <= 0 || $user_id <= 0) {
            return false;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post || !in_array($post->post_type, [self::POST_TYPE, 'portfolio'], true)) {
            return false;
        }

        if ((int) $post->post_author === $user_id) {
            return true;
        }

        $owner_id = (int) get_post_meta($post_id, self::META_OWNER, true);
        if ($owner_id > 0 && $owner_id === $user_id) {
            return true;
        }

        return current_user_can('manage_options');
    }

    private static function current_user_can_create(): bool
    {
        $post_type = get_post_type_object(self::POST_TYPE);
        $cap       = $post_type && isset($post_type->cap->edit_posts) ? $post_type->cap->edit_posts : 'edit_posts';

        return current_user_can($cap);
    }
}