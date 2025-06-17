<?php
namespace ArtPulse\Admin;

class AdminColumnsArtwork {

    public static function register() {
        add_filter('manage_ead_artwork_posts_columns', [self::class, 'add_columns']);
        add_action('manage_ead_artwork_posts_custom_column', [self::class, 'render_column'], 10, 2);
        add_filter('manage_edit-ead_artwork_sortable_columns', [self::class, 'sortable_columns']);
        add_action('quick_edit_custom_box', [self::class, 'quick_edit_box'], 10, 2);
        add_action('save_post', [self::class, 'save_quick_edit'], 10, 2);
    }

    public static function add_columns($columns) {
        $columns['artwork_image'] = __('Image', 'artpulse-management');
        $columns['artwork_title'] = __('Title', 'artpulse-management');
        $columns['artwork_artist'] = __('Artist', 'artpulse-management');
        $columns['artwork_medium'] = __('Medium', 'artpulse-management');
        $columns['artwork_featured'] = __('Featured', 'artpulse-management');
        return $columns;
    }

    public static function render_column($column, $post_id) {
        switch ($column) {
            case 'artwork_image':
                $img_id = get_post_meta($post_id, 'artwork_image', true);
                if ($img_id) {
                    echo wp_get_attachment_image($img_id, 'thumbnail');
                }
                break;

            case 'artwork_title':
                echo esc_html(get_post_meta($post_id, 'artwork_title', true));
                break;

            case 'artwork_artist':
                echo esc_html(get_post_meta($post_id, 'artwork_artist', true));
                break;

            case 'artwork_medium':
                echo esc_html(get_post_meta($post_id, 'artwork_medium', true));
                break;

            case 'artwork_featured':
                $featured = get_post_meta($post_id, 'artwork_featured', true);
                echo $featured ? ⭐ : '';
                break;
        }
    }

    public static function sortable_columns($columns) {
        $columns['artwork_title'] = 'artwork_title';
        $columns['artwork_featured'] = 'artwork_featured';
        return $columns;
    }

    public static function quick_edit_box($column, $post_type) {
        if ($post_type !== 'ead_artwork' || $column !== 'artwork_featured') return;
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <label class="alignleft">
                    <span class="title"><?php _e('Featured', 'artpulse-management'); ?></span>
                    <input type="checkbox" name="artwork_featured" value="1">
                </label>
            </div>
        </fieldset>
        <?php
    }

    public static function save_quick_edit($post_id, $post) {
        if ($post->post_type !== 'ead_artwork') return;

        if (isset($_POST['artwork_featured'])) {
            update_post_meta($post_id, 'artwork_featured', '1');
        } else {
            update_post_meta($post_id, 'artwork_featured', '0');
        }
    }
}
