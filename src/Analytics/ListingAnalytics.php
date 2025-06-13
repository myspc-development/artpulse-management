<?php
namespace EAD\Analytics;

/**
 * Class ListingAnalytics
 *
 * Logs page views and click events for listings.
 */
class ListingAnalytics {
    /**
     * Initialize hooks.
     */
    public static function init() {
        add_action( 'template_redirect', [ self::class, 'track_view' ] );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_script' ] );
        add_action( 'wp_ajax_ead_track_click', [ self::class, 'ajax_track_click' ] );
        add_action( 'wp_ajax_nopriv_ead_track_click', [ self::class, 'ajax_track_click' ] );
        add_action( 'wp_dashboard_setup', [ self::class, 'register_dashboard_widget' ] );
        add_action( 'admin_post_ead_export_analytics_csv', [ self::class, 'export_csv' ] );
    }

    /**
     * Enqueue tracking script on the frontend.
     */
    public static function enqueue_script() {
        $plugin_url = EAD_PLUGIN_DIR_URL;
        wp_enqueue_script( 'ead-analytics', $plugin_url . 'assets/js/ead-analytics.js', [ 'jquery' ], EAD_PLUGIN_VERSION, true );
        wp_localize_script(
            'ead-analytics',
            'eadAnalytics',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ead_tracking_nonce' ),
            ]
        );
    }

    /**
     * Track a single listing view.
     */
    public static function track_view() {
        if ( is_singular( [ 'ead_event', 'ead_organization' ] ) ) {
            self::increment_meta_count( get_queried_object_id(), '_ead_view_count' );
        }
    }

    /**
     * AJAX handler to record clicks.
     */
    public static function ajax_track_click() {
        check_ajax_referer( 'ead_tracking_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( $post_id ) {
            self::increment_meta_count( $post_id, '_ead_click_count' );
        }

        wp_send_json_success();
    }

    /**
     * Increment a numeric post meta count.
     *
     * @param int    $post_id  Post ID.
     * @param string $meta_key Meta key.
     */
    private static function increment_meta_count( $post_id, $meta_key ) {
        $count = (int) get_post_meta( $post_id, $meta_key, true );
        update_post_meta( $post_id, $meta_key, $count + 1 );
    }

    /**
     * Register the admin dashboard widget.
     */
    public static function register_dashboard_widget() {
        wp_add_dashboard_widget( 'ead_listing_analytics', __( 'Listing Analytics', 'artpulse-management' ), [ self::class, 'render_dashboard_widget' ] );
    }

    /**
     * Render analytics summary widget.
     */
    public static function render_dashboard_widget() {
        $types = [
            'ead_event'        => __( 'Events', 'artpulse-management' ),
            'ead_organization' => __( 'Organizations', 'artpulse-management' ),
        ];
        foreach ( $types as $type => $label ) {
            $posts = get_posts(
                [
                    'post_type'      => $type,
                    'posts_per_page' => 5,
                    'meta_key'       => '_ead_view_count',
                    'orderby'        => 'meta_value_num',
                    'order'          => 'DESC',
                ]
            );
            if ( $posts ) {
                echo '<h4>' . esc_html( $label ) . '</h4><ul>';
                foreach ( $posts as $post ) {
                    $views    = (int) get_post_meta( $post->ID, '_ead_view_count', true );
                    $clicks   = (int) get_post_meta( $post->ID, '_ead_click_count', true );
                    $featured = get_post_meta( $post->ID, '_ead_featured', true ) ? ' ⭐' : '';
                    echo '<li>' . esc_html( $post->post_title ) . $featured . ' - V:' . $views . ' C:' . $clicks . '</li>';
                }
                echo '</ul>';
            }
        }
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="ead_export_analytics_csv" />
            <?php wp_nonce_field( 'ead_export_analytics', 'ead_export_analytics_nonce' ); ?>
            <p><input type="submit" class="button" value="<?php esc_attr_e( 'Export CSV', 'artpulse-management' ); ?>"></p>
        </form>
        <?php
    }

    /**
     * Export analytics as CSV.
     */
    public static function export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'artpulse-management' ) );
        }

        check_admin_referer( 'ead_export_analytics', 'ead_export_analytics_nonce' );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="listing-analytics.csv"' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'ID', 'Title', 'Type', 'Views', 'Clicks', 'Featured' ] );

        foreach ( [ 'ead_event', 'ead_organization' ] as $type ) {
            $posts = get_posts( [ 'post_type' => $type, 'posts_per_page' => -1, 'post_status' => 'publish' ] );
            foreach ( $posts as $post ) {
                fputcsv(
                    $out,
                    [
                        $post->ID,
                        $post->post_title,
                        $type,
                        (int) get_post_meta( $post->ID, '_ead_view_count', true ),
                        (int) get_post_meta( $post->ID, '_ead_click_count', true ),
                        get_post_meta( $post->ID, '_ead_featured', true ) ? '1' : '0',
                    ]
                );
            }
        }

        fclose( $out );
        exit;
    }
}
