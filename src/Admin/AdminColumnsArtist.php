<?php
namespace ArtPulse\Admin;

class AdminColumnsArtist {

    public static function register() {
        add_filter('manage_ead_artist_posts_columns', [self::class, 'custom_columns']);
        add_action('manage_ead_artist_posts_custom_column', [self::class, 'render_columns'], 10, 2);
        add_filter('manage_edit-ead_artist_sortable_columns', [self::class, 'sortable_columns']);
        add_action('quick_edit_custom_box', [self::class, 'quick_edit'], 10, 2);
        add_action('save_post', [self::class, 'save_quick_edit']);
    }

    public static function custom_columns($columns) {
        unset($columns['date']);
        $columns['artist_portrait']  = __('Portrait', 'artpulse-management');
        $columns['artist_name']      = __('Name', 'artpulse-management');
        $columns['artist_featured']  = __('Featured', 'artpulse-management');
        $columns['artist_email']     = __('Email', 'artpulse-management');
        $columns['date']             = __('Date');
        return $columns;
    }

    public static function render_columns($column, $post_id) {
        switch ($column) {
            case 'artist_portrait':
                $id = get_post_meta($post_id, 'artist_portrait', true);
                if ($id) echo wp_get_attachment_image($id, [50, 50]);
                break;

            case 'artist_name':
                echo esc_html(get_the_title($post_id));
                break;

            case 'artist_featured':
                $featured = get_post_meta($post_id, 'artist_featured', true);
                echo $featured === '1' ? '<span class="dashicons dashicons-star-filled"></span>' : '<span class="dashicons dashicons-star-empty"></span>';
                break;

            case 'artist_email':
                $email = get_post_meta($post_id, 'artist_email', true);
                echo $email ? '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '';
                break;
        }
    }

    public static function sortable_columns($columns) {
        $columns['artist_featured'] = 'artist_featured';
        $columns['artist_name']     = 'title';
        return $columns;
    }

    public static function quick_edit($column_name, $post_type) {
        if ($post_type !== 'ead_artist' || $column_name !== 'artist_featured') return;
        echo '<fieldset class="inline-edit-col-left">
                <div class="inline-edit-col">
                    <label class="alignleft">
                        <input type="checkbox" name="artist_featured" value="1" />
                        <span>' . __('Featured Artist', 'artpulse-management') . '</span>
                    </label>
                </div>
              </fieldset>';
    }

    public static function save_quick_edit($post_id) {
        if (!isset($_POST['artist_featured']) && get_post_type($post_id) === 'ead_artist') {
            update_post_meta($post_id, 'artist_featured', '0');
        } elseif (get_post_type($post_id) === 'ead_artist') {
            update_post_meta($post_id, 'artist_featured', '1');
        }
    }
}
