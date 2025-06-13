<?php
namespace EAD\Admin;

class HelpTabs {
    public static function register() {
        add_action('current_screen', [self::class, 'add_help_tabs']);
    }

    public static function add_help_tabs(\WP_Screen $screen) {
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        switch ($page) {
            case 'artpulse-csv-import-export':
                self::csv_help($screen);
                break;
            case 'artpulse-pending-events':
                self::pending_events_help($screen);
                break;
            case 'artpulse-pending-organizations':
                self::pending_orgs_help($screen);
                break;
            case 'artpulse-pending-artists':
                self::pending_artists_help($screen);
                break;
            case 'artpulse-pending-artworks':
                self::pending_artworks_help($screen);
                break;
            case 'ead-moderate-reviews':
                self::reviews_help($screen);
                break;
            case 'artpulse-comments':
                self::comments_help($screen);
                break;
            case 'artpulse-bookings':
                self::bookings_help($screen);
                break;
            case 'artpulse-notifications':
                self::notifications_help($screen);
                break;
            case 'artpulse-settings':
                if ( isset( $_GET['tab'] ) && $_GET['tab'] === 'import_export' ) {
                    self::csv_help( $screen );
                } else {
                    self::settings_help( $screen );
                }
                break;
            case 'ead-admin-add-event':
                self::admin_event_help($screen);
                break;
            default:
                return;
        }
        self::add_sidebar($screen);
    }

    private static function csv_help($screen) {
        $content  = '<p>' . esc_html__('Use this page to import or export plugin data via CSV files.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('See the %1$sAdmin Help Guide%2$s for CSV instructions.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Admin_Help.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_csv_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function pending_events_help($screen) {
        $content  = '<p>' . esc_html__('Approve or reject submitted events before they appear publicly.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('Refer to the %1$sAdmin Help Guide%2$s for moderation tips.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Admin_Help.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_pending_events_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function pending_orgs_help($screen) {
        $content  = '<p>' . esc_html__('Review organization submissions awaiting approval.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('More details are in the %1$sAdmin Help Guide%2$s.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Admin_Help.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_pending_orgs_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function pending_artists_help($screen) {
        $content  = '<p>' . esc_html__('Approve or reject artist profiles submitted by users.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('See the %1$sAdmin Help Guide%2$s for review steps.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Admin_Help.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_pending_artists_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function pending_artworks_help($screen) {
        $content  = '<p>' . esc_html__('Moderate artwork submissions waiting for review.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('Guidance is available in the %1$sAdmin Help Guide%2$s.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Admin_Help.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_pending_artworks_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function reviews_help($screen) {
        $content  = '<p>' . esc_html__('Moderate visitor reviews and mark them as approved or spam.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('See the %1$sAdmin Help Guide%2$s for details.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Admin_Help.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_reviews_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function comments_help($screen) {
        $content  = '<p>' . esc_html__('Manage visitor comments submitted through ArtPulse forms.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('For more information see the %1$sAdmin Help Guide%2$s.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Admin_Help.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_comments_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function bookings_help($screen) {
        $content  = '<p>' . esc_html__('View and manage bookings created through event pages.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('Helpful tips are in the %1$sAdmin Help Guide%2$s.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Admin_Help.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_bookings_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function notifications_help($screen) {
        $content  = '<p>' . esc_html__('Configure email and on-site notifications sent by the plugin.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('Read the %1$sAdmin Help Guide%2$s for notification settings.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Admin_Help.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_notifications_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function settings_help($screen) {
        $content  = '<p>' . esc_html__('Adjust global options, API keys and data management settings.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('Consult the %1$sOnboarding Guide%2$s for setup guidance.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Onboarding_Guide.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_settings_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function admin_event_help($screen) {
        $content  = '<p>' . esc_html__('Create events directly from the admin without visiting the front-end forms.', 'artpulse-management') . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('Steps for adding events are in the %1$sOnboarding Guide%2$s.', 'artpulse-management'),
            '<a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'documents/Onboarding_Guide.md') . '" target="_blank">',
            '</a>'
        ) . '</p>';
        $screen->add_help_tab([
            'id'      => 'ead_admin_event_help',
            'title'   => __('Overview', 'artpulse-management'),
            'content' => $content,
        ]);
    }

    private static function add_sidebar($screen) {
        $screen->set_help_sidebar(
            '<p><strong>' . esc_html__('More help', 'artpulse-management') . '</strong></p>' .
            '<p><a href="' . esc_url(EAD_PLUGIN_DIR_URL . 'README.md') . '" target="_blank">' . esc_html__('Plugin README', 'artpulse-management') . '</a></p>'
        );
    }
}

