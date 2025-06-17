<?php
namespace ArtPulse\Admin;

class AdminColumnsOrganisation {

    public static function register() {
        add_filter('manage_ead_organization_posts_columns', [self::class, 'add_columns']);
        add_action('manage_ead_organization_posts_custom_column', [self::class, 'render_column'], 10, 2);

        add_filter('manage_edit-ead_organization_sortable_columns', [self::class, 'sortable_columns']);
        add_action('quick_edit_custom_box', [self::class, 'quick_edit_fields'], 10, 2);
        add_action('save_post_ead_organization', [self::class, 'save_quick_edit_fields']);
    }

    public static function add_columns($columns) {
        $columns['ead_org_type'] = __('Type', 'artpulse-management');
        $columns['ead_org_size'] = __('Size', 'artpulse-management');
        $columns['ead_org_featured'] = __('Featured?', 'artpulse-management');
        $columns['ead_org_city'] = __('City', 'artpulse-management');
        $columns['ead_org_country'] = __('Country', 'artpulse-management');
        $columns['ead_org_website'] = __('Website', 'artpulse-management');
        return $columns;
    }

    public static function render_column($column, $post_id) {
        $value = get_post_meta($post_id, $column, true);
        switch ($column) {
            case 'ead_org_featured':
                echo $value === '1' 
                    ? '<span class="dashicons dashicons-star-filled" style="color:gold"></span>' 
                    : '<span class="dashicons dashicons-star-empty" style="color:#ccc"></span>';
                break;
            case 'ead_org_website':
                if ($value) {
                    echo '<a href="' . esc_url($value) . '" target="_blank">' . esc_html(parse_url($value, PHP_URL_HOST)) . '</a>';
                }
                break;
            default:
                echo esc_html($value);
        }
    }

    public static function sortable_columns($columns) {
        $columns['ead_org_type'] = 'ead_org_type';
        $columns['ead_org_size'] = 'ead_org_size';
        $columns['ead_org_country'] = 'ead_org_country';
        $columns['ead_org_featured'] = 'ead_org_featured';
        return $columns;
    }

    public static function quick_edit_fields($column_name, $post_type) {
        if ($post_type !== 'ead_organization') return;

        if ($column_name === 'ead_org_featured') {
            ?>
            <fieldset class="inline-edit-col-right">
                <div class="inline-edit-col">
                    <label class="alignleft">
                        <input type="checkbox" name="ead_org_featured" value="1">
                        <span class="checkbox-title"><?php esc_html_e('Featured', 'artpulse-management'); ?></span>
                    </label>
                </div>
            </fieldset>
            <?php
        }
    }

    public static function save_quick_edit_fields($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $value = isset($_REQUEST['ead_org_featured']) ? '1' : '0';
        update_post_meta($post_id, 'ead_org_featured', $value);
    }
}
