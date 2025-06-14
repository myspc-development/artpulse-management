<?php
namespace EAD\Admin;

/**
 * CSV Import/Export Page.
 */
class CSVImportExport {

    public static function register() {
        add_action('admin_post_ead_export_csv', [self::class, 'handle_export']);
        add_action('admin_post_ead_import_csv', [self::class, 'handle_import']);
    }

    public static function render_admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('CSV Import/Export', 'artpulse-management') . '</h1>';

        $import_log = get_transient('ead_import_log');
        if ($import_log && is_array($import_log)) {
            echo '<div class="notice notice-info"><ul>';
            foreach ($import_log as $entry) {
                echo '<li>' . esc_html($entry) . '</li>';
            }
            echo '</ul></div>';
            delete_transient('ead_import_log');
        }

        if (isset($_GET['imported']) && $_GET['imported'] == 1) {
            echo '<div class="notice notice-success"><p>' . __('CSV import completed successfully.', 'artpulse-management') . '</p></div>';
        }

        // Export Form
        echo '<h2>' . __('Export Data', 'artpulse-management') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="ead_export_csv">';
        echo '<label for="export_post_type">' . __('Select Post Type:', 'artpulse-management') . '</label> ';
        echo '<select name="post_type" id="export_post_type">';
        echo '<option value="ead_event">' . __('Events', 'artpulse-management') . '</option>';
        echo '<option value="ead_organization">' . __('Organizations', 'artpulse-management') . '</option>';
        echo '<option value="ead_artist">' . __('Artists', 'artpulse-management') . '</option>';
        echo '<option value="ead_artwork">' . __('Artworks', 'artpulse-management') . '</option>';
        echo '</select> ';
        submit_button(__('Download CSV', 'artpulse-management'), 'primary', 'export_csv');
        echo '</form>';

        // Import Form
        echo '<h2>' . __('Import Data', 'artpulse-management') . '</h2>';
        echo '<form id="ead-csv-import-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="ead_import_csv">';
        wp_nonce_field('ead_import_csv', 'ead_import_csv_nonce');
        echo '<label for="import_post_type">' . __('Select Post Type:', 'artpulse-management') . '</label> ';
        echo '<select name="post_type" id="import_post_type">';
        echo '<option value="ead_event">' . __('Events', 'artpulse-management') . '</option>';
        echo '<option value="ead_organization">' . __('Organizations', 'artpulse-management') . '</option>';
        echo '<option value="ead_artist">' . __('Artists', 'artpulse-management') . '</option>';
        echo '<option value="ead_artwork">' . __('Artworks', 'artpulse-management') . '</option>';
        echo '</select><br><br>';
        echo '<label for="mapping_preset">' . __('Use Saved Mapping:', 'artpulse-management') . '</label> ';
        echo '<select name="mapping_preset" id="mapping_preset"><option value="">' . esc_html__('None', 'artpulse-management') . '</option></select><br><br>';
        echo '<label for="save_mapping_name">' . __('Save Mapping As:', 'artpulse-management') . '</label> ';
        echo '<input type="text" name="save_mapping_name" id="save_mapping_name" placeholder="' . esc_attr__('optional name', 'artpulse-management') . '" /><br><br>';
        echo '<label for="ead_csv_file">' . __('CSV File:', 'artpulse-management') . '</label> ';
        echo '<input type="file" name="ead_csv_file" id="ead_csv_file" accept=".csv" required><br><br>';
        echo '<input type="hidden" name="ead_mapping_json" id="ead_mapping_json" value="" />';
        echo '<div id="ead-csv-preview-wrapper"></div>';

        echo '<p>' . __('The first row of your CSV file should contain the field names. You will confirm the mapping before the import runs.', 'artpulse-management') . '</p>';

        submit_button(__('Upload CSV', 'artpulse-management'), 'primary', 'import_csv');
        echo '</form>';

        // Field Mapping Section
        $options = get_option('artpulse_plugin_settings', []);
        echo '<h2>' . __('Field Mapping', 'artpulse-management') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
        settings_fields('artpulse_plugin_settings');
        echo '<table class="form-table">';

        echo '<tr><th scope="row"><label for="event_field_mapping">' . __('Events Mapping', 'artpulse-management') . '</label></th>';
        echo '<td><textarea id="event_field_mapping" name="artpulse_plugin_settings[event_field_mapping]" rows="5" cols="70" class="large-text code">' . esc_textarea($options['event_field_mapping'] ?? '') . '</textarea></td></tr>';

        echo '<tr><th scope="row"><label for="organization_field_mapping">' . __('Organizations Mapping', 'artpulse-management') . '</label></th>';
        echo '<td><textarea id="organization_field_mapping" name="artpulse_plugin_settings[organization_field_mapping]" rows="5" cols="70" class="large-text code">' . esc_textarea($options['organization_field_mapping'] ?? '') . '</textarea></td></tr>';

        echo '<tr><th scope="row"><label for="artist_field_mapping">' . __('Artists Mapping', 'artpulse-management') . '</label></th>';
        echo '<td><textarea id="artist_field_mapping" name="artpulse_plugin_settings[artist_field_mapping]" rows="5" cols="70" class="large-text code">' . esc_textarea($options['artist_field_mapping'] ?? '') . '</textarea></td></tr>';

        echo '<tr><th scope="row"><label for="artwork_field_mapping">' . __('Artworks Mapping', 'artpulse-management') . '</label></th>';
        echo '<td><textarea id="artwork_field_mapping" name="artpulse_plugin_settings[artwork_field_mapping]" rows="5" cols="70" class="large-text code">' . esc_textarea($options['artwork_field_mapping'] ?? '') . '</textarea></td></tr>';

        echo '</table>';
        submit_button(__('Save Mapping', 'artpulse-management'));
        echo '</form>';
        echo '</div>';
    }

    public static function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export data.', 'artpulse-management'));
        }

        $post_type       = sanitize_text_field($_POST['post_type'] ?? 'ead_event');
        $mapping_preset  = sanitize_text_field($_POST['mapping_preset'] ?? '');
        $save_name       = sanitize_text_field($_POST['save_mapping_name'] ?? '');

        $confirmed_json = isset($_POST['ead_mapping_json']) ? wp_unslash($_POST['ead_mapping_json']) : '';
        $new_mapping    = null;
        if ($confirmed_json) {
            $parsed = json_decode($confirmed_json, true);
            if (is_array($parsed)) {
                $new_mapping = $parsed;
                if ($save_name) {
                    self::save_mapping_preset($post_type, $save_name, $parsed);
                }
                $options = get_option('artpulse_plugin_settings', []);
                switch ($post_type) {
                    case 'ead_event':
                        $options['event_field_mapping'] = wp_json_encode($parsed);
                        break;
                    case 'ead_organization':
                        $options['organization_field_mapping'] = wp_json_encode($parsed);
                        break;
                    case 'ead_artist':
                        $options['artist_field_mapping'] = wp_json_encode($parsed);
                        break;
                    case 'ead_artwork':
                        $options['artwork_field_mapping'] = wp_json_encode($parsed);
                        break;
                }
                update_option('artpulse_plugin_settings', $options);
            }
        }

        if ($mapping_preset && empty($confirmed_json)) {
            $presets = self::get_saved_mappings($post_type);
            if (isset($presets[$mapping_preset])) {
                $new_mapping = $presets[$mapping_preset];
            }
        }
        $filename = $post_type . '_export_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=' . $filename);

        $output = fopen('php://output', 'w');

        $mapping = self::get_mapping_for_post_type($post_type);
        if ($mapping_preset && empty($confirmed_json)) {
            $presets = self::get_saved_mappings($post_type);
            if (isset($presets[$mapping_preset])) {
                $mapping = $presets[$mapping_preset];
            }
        }
        if (isset($new_mapping)) {
            $mapping = $new_mapping;
        }
        if (!empty($mapping)) {
            $fields = array_values($mapping); // meta keys
            fputcsv($output, array_merge(['ID', 'Title'], array_keys($mapping)));
        } else {
            $fields = self::get_fields_for_post_type($post_type);
            fputcsv($output, array_merge(['ID', 'Title'], $fields));
        }

        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ];
        $posts = new \WP_Query($args);

        if ($posts->have_posts()) {
            while ($posts->have_posts()) {
                $posts->the_post();
                $post_id = get_the_ID();
                $title = get_the_title();
                $row = [$post_id, $title];

                foreach ($fields as $field) {
                    $row[] = get_post_meta($post_id, $field, true);
                }

                fputcsv($output, $row);
            }
        }

        fclose($output);
        exit;
    }

    public static function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to import data.', 'artpulse-management'));
        }

        if (!isset($_POST['ead_import_csv_nonce']) || !wp_verify_nonce($_POST['ead_import_csv_nonce'], 'ead_import_csv')) {
            wp_die(__('Security check failed.', 'artpulse-management'));
        }

        $post_type      = sanitize_text_field($_POST['post_type'] ?? 'ead_event');
        $mapping_preset = sanitize_text_field($_POST['mapping_preset'] ?? '');
        $save_name      = sanitize_text_field($_POST['save_mapping_name'] ?? '');
        $confirmed_json = isset($_POST['ead_mapping_json']) ? wp_unslash($_POST['ead_mapping_json']) : '';
        $new_mapping    = null;
        if ($confirmed_json) {
            $parsed = json_decode($confirmed_json, true);
            if (is_array($parsed)) {
                $new_mapping = $parsed;
                if ($save_name) {
                    self::save_mapping_preset($post_type, $save_name, $parsed);
                }
                $options = get_option('artpulse_plugin_settings', []);
                switch ($post_type) {
                    case 'ead_event':
                        $options['event_field_mapping'] = wp_json_encode($parsed);
                        break;
                    case 'ead_organization':
                        $options['organization_field_mapping'] = wp_json_encode($parsed);
                        break;
                    case 'ead_artist':
                        $options['artist_field_mapping'] = wp_json_encode($parsed);
                        break;
                    case 'ead_artwork':
                        $options['artwork_field_mapping'] = wp_json_encode($parsed);
                        break;
                }
                update_option('artpulse_plugin_settings', $options);
            }
        }

        if (!isset($_FILES['ead_csv_file']) || $_FILES['ead_csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('CSV file upload failed.', 'artpulse-management'));
        }

        $file = $_FILES['ead_csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            wp_die(__('Could not read uploaded file.', 'artpulse-management'));
        }

        $header = fgetcsv($handle);
        if (!$header) {
            wp_die(__('Invalid CSV file.', 'artpulse-management'));
        }

        $mapping = self::get_mapping_for_post_type($post_type);
        if ($mapping_preset && empty($confirmed_json)) {
            $presets = self::get_saved_mappings($post_type);
            if (isset($presets[$mapping_preset])) {
                $mapping = $presets[$mapping_preset];
            }
        }
        if (isset($new_mapping)) {
            $mapping = $new_mapping;
        }
        $log = [];

        while (($data = fgetcsv($handle)) !== false) {
            $post_title = sanitize_text_field($data[1] ?? '');
            if (empty($post_title)) {
                $log[] = __('Skipped row with missing title.', 'artpulse-management');
                continue;
            }

            $post_id = wp_insert_post([
                'post_type'   => $post_type,
                'post_status' => 'publish',
                'post_title'  => $post_title,
            ]);

            if (!is_wp_error($post_id)) {
                foreach ($header as $index => $field_name) {
                    if ($index < 2) continue; // skip ID and Title columns
                    $csv_column = sanitize_text_field($field_name);
                    $meta_key  = $mapping[$csv_column] ?? sanitize_key($csv_column);
                    $meta_value = sanitize_text_field($data[$index] ?? '');
                    update_post_meta($post_id, $meta_key, $meta_value);
                }
                $log[] = sprintf(__('Imported "%s" successfully.', 'artpulse-management'), $post_title);
            } else {
                $log[] = sprintf(__('Error importing "%s".', 'artpulse-management'), $post_title);
            }
        }

        fclose($handle);
        set_transient('ead_import_log', $log, 60);

        wp_safe_redirect(admin_url('admin.php?page=artpulse-csv-import-export&imported=1'));
        exit;
    }

    private static function get_fields_for_post_type($post_type) {
        switch ($post_type) {
            case 'ead_event':
                return ['_ead_event_date', '_ead_event_time', '_ead_event_location'];
            case 'ead_organization':
                return ['ead_org_name', 'organisation_name', 'organisation_email', 'organisation_phone', 'organisation_website'];
            case 'ead_artist':
                return ['artist_name', 'artist_email', 'artist_phone', 'artist_website'];
            case 'ead_artwork':
                return ['artwork_title', 'artwork_medium', 'artwork_dimensions', 'artwork_year', 'artwork_price'];
            default:
                return [];
        }
    }

    private static function get_mapping_for_post_type($post_type) {
        $options = get_option('artpulse_plugin_settings', []);
        switch ($post_type) {
            case 'ead_event':
                return json_decode($options['event_field_mapping'] ?? '', true) ?: [];
            case 'ead_organization':
                return json_decode($options['organization_field_mapping'] ?? '', true) ?: [];
            case 'ead_artist':
                return json_decode($options['artist_field_mapping'] ?? '', true) ?: [];
            case 'ead_artwork':
                return json_decode($options['artwork_field_mapping'] ?? '', true) ?: [];
            default:
                return [];
        }
    }

    public static function get_saved_mappings($post_type) {
        $all = get_option('artpulse_mapping_presets', []);
        return $all[$post_type] ?? [];
    }

    public static function get_all_saved_mappings() {
        return get_option('artpulse_mapping_presets', []);
    }

    public static function save_mapping_preset($post_type, $name, $mapping) {
        $all = get_option('artpulse_mapping_presets', []);
        if (!isset($all[$post_type])) {
            $all[$post_type] = [];
        }
        $all[$post_type][$name] = $mapping;
        update_option('artpulse_mapping_presets', $all);
    }
}
