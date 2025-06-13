<?php
namespace EAD\Export;

/**
 * Class DataExport
 *
 * Handles exporting plugin data to CSV for admins.
 *
 * @package EAD\Export
 */
class DataExport {

    /**
     * Initialize the Data Export feature.
     */
    public static function init() {
        add_action('admin_menu', [self::class, 'add_export_page']);
    }

    /**
     * Add a submenu page under Tools for exporting data.
     */
    public static function add_export_page() {
        add_submenu_page(
            'tools.php',
            __('Export Plugin Data', 'artpulse-management'),
            __('Export Plugin Data', 'artpulse-management'),
            'manage_options',
            'ead-data-export',
            [self::class, 'render_export_page']
        );
    }

    /**
     * Render the Export Data admin page.
     */
    public static function render_export_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'artpulse-management'));
        }

        if (isset($_POST['ead_export_csv'])) {
            self::handle_csv_export();
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Export Plugin Data', 'artpulse-management'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('ead_export_csv_action', 'ead_export_csv_nonce'); ?>
                <p><?php esc_html_e('Click the button below to export all Events data to a CSV file.', 'artpulse-management'); ?></p>
                <p><input type="submit" name="ead_export_csv" class="button button-primary" value="<?php esc_attr_e('Export Events CSV', 'artpulse-management'); ?>"></p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle exporting Events data to CSV.
     */
    private static function handle_csv_export() {
        if (!isset($_POST['ead_export_csv_nonce']) || !wp_verify_nonce($_POST['ead_export_csv_nonce'], 'ead_export_csv_action')) {
            wp_die(__('Invalid nonce specified.', 'artpulse-management'), __('Error', 'artpulse-management'), ['response' => 403]);
        }

        // Fetch Events
        $args = [
            'post_type'      => 'ead_event',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];
        $events = get_posts($args);

        // CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="events-export.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Title', 'Date', 'Author']);

        foreach ($events as $event) {
            fputcsv($output, [
                $event->ID,
                $event->post_title,
                $event->post_date,
                get_the_author_meta('display_name', $event->post_author)
            ]);
        }

        fclose($output);
        exit;
    }
}
