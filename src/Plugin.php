<?php
namespace EAD;

use EAD\Admin;
use EAD\Dashboard;
use EAD\Shortcodes;
use EAD\Rest;
use EAD\Roles;
use EAD\Integration\WPBakery;
use EAD\Integration\PortfolioSync;
use EAD\Integration\SocialAutoPoster;
/**
 * Main plugin class.
 */
class Plugin {

    /**
     * Run the plugin.
     */
    public function run() {
        // Admin
        Admin\MetaBoxesArtwork::register();
        Admin\MetaBoxesEvent::register();
        Admin\MetaBoxesOrganisation::register();
        Admin\MetaBoxesArtist::register();

        // CSV Import/Export
        Admin\CSVImportExport::register();

        Admin\SettingsPage::register();
        Admin\ReviewsModerator::register();
        Admin\NotificationSettingsAdmin::register();
        Admin\Geocoder::register();

        //REST API
        $dashboard_endpoint = new Rest\DashboardEndpoint();
         $settings_endpoint = new Rest\SettingsEndpoint();
        $organizations_endpoint = new Rest\OrganizationsEndpoint();
        $organizationdashboard_endpoint = new Rest\OrganizationDashboardEndpoint();
        $artwork_endpoint = new Rest\ArtworkEndpoint();
        $submit_event_endpoint = new Rest\SubmitEventEndpoint();
        $taxonomy_endpoint = new Rest\TaxonomyEndpoint();
        $userprofile_endpoint = new Rest\UserProfileEndpoint();
        $reviews_endpoint = new Rest\ReviewsEndpoint();
        $artistdashboard_endpoint = new Rest\ArtistDashboardEndpoint();
        $comment_endpoint = new Rest\CommentEndpoint();
        $like_endpoint = new Rest\Like_Endpoint();

        //Dashboards
        Dashboard\OrganizationDashboard::init();
        Dashboard\ArtistDashboard::init();

        //Shortcodes
        Shortcodes\EventsList::register();
        Shortcodes\EventsListShortcode::register();
        Shortcodes\OrganizationList::register();
        Shortcodes\OrganizationForm::register();
        Shortcodes\OrganizationRegistrationForm::register();
        Shortcodes\SubmitEventForm::register();
        Shortcodes\ArtistRegistrationForm::register();
        EditEventForm::register();
        OrganizerDashboard::register();
        OrgReviewForm::register();
        FavoritesList::register();

        // Enqueue admin assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Set up admin features, including filters for CPTs
        $this->setup_admin_features();

        // Notification hooks
        add_action( 'transition_post_status', [ self::class, 'notify_user_on_organization_approval' ], 10, 3 );
        add_action( 'transition_post_status', [ self::class, 'notify_organizer_on_event_approval' ], 10, 3 );
        add_action( 'transition_post_status', [ self::class, 'notify_admin_on_event_pending' ], 10, 3 );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        $plugin_url = EAD_PLUGIN_DIR_URL;
        $version    = self::VERSION;
        $screen     = get_current_screen();

        $allowed_post_types_for_featured_js = [ 'ead_event', 'ead_organization', 'ead_artist', 'ead_artwork' ];

        if ( $screen && in_array( $screen->post_type, $allowed_post_types_for_featured_js, true ) && in_array( $hook_suffix, [ 'post.php', 'post-new.php', 'edit.php' ] ) ) {
            wp_enqueue_script( 'ead-admin-featured-js', $plugin_url . 'assets/js/ead-featured.js', [ 'jquery' ], $version, true );

            wp_localize_script(
                'ead-admin-featured-js',
                'eadFeaturedAdmin',
                [
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'restUrl' => esc_url_raw( rest_url( 'artpulse/v1' ) ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                ]
            );
        }
    }


    private function register_phase3_features() {
        if ( class_exists( ArtistDashboard::class ) ) {
            ArtistDashboard::init();
        }

        if ( class_exists( OrganizationDashboard::class ) ) {
            OrganizationDashboard::init();
        }

        if ( class_exists( Reviews::class ) ) {
            Reviews::init();
        }

        if ( class_exists( RolesManager::class ) ) {
            RolesManager::init();
        }

        if ( class_exists( DataExport::class ) ) {
            DataExport::init();
        }
    }

    private function register_integrations() {
        if ( class_exists( 'Vc_Manager' ) && class_exists( WPBakery::class ) ) {
            WPBakery::register();
        }
        if ( class_exists( PortfolioSync::class ) ) {
            PortfolioSync::register();
        }
        if ( class_exists( SocialAutoPoster::class ) ) {
            SocialAutoPoster::register();
        }
    }

    private function enqueue_assets() {
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_frontend_assets' ] );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_map_assets_conditionally' ] );
    }

    public static function enqueue_frontend_assets() {
        $plugin_url = EAD_PLUGIN_DIR_URL;
        $version    = self::VERSION;

        wp_enqueue_style( 'ead-main-style', $plugin_url . 'assets/css/ead-main.css', [], $version );
        wp_enqueue_style( 'ead-badges-style', $plugin_url . 'assets/css/ead-badges.css', [], $version );
        wp_enqueue_style( 'ead-artist-gallery', $plugin_url . 'assets/css/artist-gallery.css', [], $version );

        wp_enqueue_script( 'ead-main-js', $plugin_url . 'assets/js/ead-main.js', [ 'jquery' ], $version, true );

        wp_localize_script(
            'ead-main-js',
            'eadFrontend',
            [
                'ajaxUrl'                 => admin_url( 'admin-ajax.php' ),
                'restUrl'                 => esc_url_raw( rest_url() ),
                'nonce_wp_rest'           => wp_create_nonce( 'wp_rest' ),
                'nonce_frontend_submit'   => wp_create_nonce( 'ead_frontend_submit_nonce' ),
                'nonce_upload_image'      => wp_create_nonce( 'ead_upload_image_nonce' ),
                'nonce_submit_org_review' => wp_create_nonce( 'ead_submit_org_review_nonce' ),
            ]
        );
    }

    public static function enqueue_map_assets_conditionally() {
        global $post;

        if (
            is_a( $post, 'WP_Post' ) &&
            class_exists( Shortcodes\OrganizationList::class ) &&
            defined( Shortcodes\OrganizationList::class . '::SHORTCODE_TAG_MAP' ) &&
            has_shortcode( $post->post_content, Shortcodes\OrganizationList::SHORTCODE_TAG_MAP )
        ) {
            $version = self::VERSION;

            $settings      = get_option( 'artpulse_plugin_settings', [] );
            $gmaps_api_key = isset( $settings['google_maps_api_key'] ) ? $settings['google_maps_api_key'] : '';

            wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4' );
            wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true );

            wp_enqueue_style( 'leaflet-cluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.css', [ 'leaflet' ], '1.5.3' );
            wp_enqueue_script( 'leaflet-cluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', [ 'leaflet' ], '1.5.3', true );

            wp_enqueue_script( 'ead-org-map-ajax', EAD_PLUGIN_DIR_URL . 'assets/js/ead-org-map-ajax.js', [ 'jquery', 'leaflet', 'leaflet-cluster' ], $version, true );

            if ( $gmaps_api_key ) {
                wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $gmaps_api_key, [], null, true );
            }
            wp_localize_script(
                'ead-org-map-ajax',
                'EAD_ORG_MAP_AJAX',
                [
                    'ajaxurl'     => admin_url( 'admin-ajax.php' ),
                    'nonce'       => wp_create_nonce( 'ead_get_orgs_in_bounds_nonce' ),
                    'defaultLat'  => get_option( 'ead_map_default_lat', 40.7128 ),
                    'defaultLng'  => get_option( 'ead_map_default_lng', - 74.0060 ),
                    'defaultZoom' => get_option( 'ead_map_default_zoom', 10 ),
                    'text_no_orgs_found' => __( 'No organizations found in this area.', self::TEXT_DOMAIN ),
                    'gmapsApiKey' => $gmaps_api_key,
                ]
            );
        }
    }

     private function setup_admin_features() {
        self::setup_event_admin_features();

        add_action( 'restrict_manage_posts', [ self::class, 'add_featured_request_filter_for_cpts' ] );
        add_filter( 'parse_query', [ Admin\MetaBoxesEvent::class, 'parse_featured_request_query_for_cpts' ] );
    }

    private function register_ajax_handlers() {
        $ajax_actions = [
            'ead_upload_image'       => 'handle_image_upload_ajax',
            'ead_submit_org_review'  => 'handle_org_review_submit_ajax',
            'ead_get_orgs_in_bounds' => 'ajax_get_orgs_in_bounds',
        ];

        foreach ( $ajax_actions as $action => $handler_method_name ) {
            if ( method_exists( self::class, $handler_method_name ) ) {
                add_action( 'wp_ajax_' . $action, [ self::class, $handler_method_name ] );
                if ( $action !== 'ead_upload_image' ) {
                    add_action( 'wp_ajax_nopriv_' . $action, [ self::class, $handler_method_name ] );
                }
            }
        }
    }

    public static function setup_event_admin_features() {
        add_filter( 'manage_ead_event_posts_columns', [ self::class, 'modify_event_admin_columns' ] );
        add_action( 'manage_ead_event_posts_custom_column', [ self::class, 'render_event_admin_custom_columns' ], 10, 2 );
        add_filter( 'manage_edit-ead_event_sortable_columns', [ self::class, 'make_event_admin_columns_sortable' ] );
        add_action( 'pre_get_posts', [ self::class, 'handle_event_admin_column_orderby' ] );
        add_action( 'restrict_manage_posts', [ self::class, 'add_event_status_filter_and_export_button' ] );
        add_filter( 'bulk_actions-edit-ead_event', [ self::class, 'add_event_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-edit-ead_event', [ self::class, 'handle_event_bulk_actions' ], 10, 3 );
        add_action( 'admin_notices', [ self::class, 'display_event_bulk_action_notices' ] );
        add_action( 'admin_head', [ self::class, 'add_event_admin_list_styles' ] );
    }

    public static function modify_event_admin_columns( $columns ) {
        $new_columns = [];

        foreach ( $columns as $key => $title ) {
            $new_columns[ $key ] = $title;

            if ( $key === 'title' ) {
                $new_columns['event_organisation'] = __( 'Linked Organization', self::TEXT_DOMAIN );
                $new_columns['organizer_name']   = __( 'Submitter Name', self::TEXT_DOMAIN );
                $new_columns['organizer_email']  = __( 'Submitter Email', self::TEXT_DOMAIN );
                $new_columns['event_start']      = __( 'Start Date', self::TEXT_DOMAIN );
                $new_columns['event_end']        = __( 'End Date', self::TEXT_DOMAIN );
                $new_columns['gallery']          = __( 'Gallery', self::TEXT_DOMAIN );
            }
        }

        if ( ! isset( $new_columns['ead_featured_request'] ) ) {
            $new_columns['ead_featured_request'] = __( 'Featured Requested', self::TEXT_DOMAIN );
        }

        return $new_columns;
    }

    public static function render_event_admin_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'event_organisation':
                $org_id   = get_post_meta( $post_id, '_ead_event_organisation_id', true );

                if ( $org_id && get_post( $org_id ) && 'trash' !== get_post_status( $org_id ) ) {
                    echo '<a href="' . esc_url( get_edit_post_link( $org_id ) ) . '">' . esc_html( get_the_title( $org_id ) ) . '</a>';
                } else {
                    echo '<em>' . esc_html__( 'N/A', self::TEXT_DOMAIN ) . '</em>';
                }
                break;

            case 'organizer_name':
                echo esc_html( get_post_meta( $post_id, 'event_organizer_name', true ) );
                break;

            case 'organizer_email':
                $email = get_post_meta( $post_id, 'event_organizer_email', true );

                if ( $email && is_email( $email ) ) {
                    echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
                }
                break;

            case 'event_start':
                echo esc_html( get_post_meta( $post_id, 'event_start_date', true ) );
                break;

            case 'event_end':
                echo esc_html( get_post_meta( $post_id, 'event_end_date', true ) );
                break;

            case 'gallery':
                $gallery_ids = get_post_meta( $post_id, 'event_gallery', true );

                if ( ! empty( $gallery_ids ) && is_array( $gallery_ids ) ) {
                    echo esc_html( count( $gallery_ids ) ) . ' ' . esc_html( _n( 'image', 'images', count( $gallery_ids ), self::TEXT_DOMAIN ) ) . '</a>';
                } else {
                    echo esc_html__( '0 images', self::TEXT_DOMAIN );
                }
                break;

            case 'ead_featured_request':
                if ( get_post_meta( $post_id, '_ead_featured_request', true ) === '1' ) {
                    echo '<span style="color:orange;font-weight:bold;">⧫</span> ' . esc_html__( 'Requested', self::TEXT_DOMAIN );
                }
                break;
        }
    }

    public static function make_event_admin_columns_sortable( $columns ) {
        $columns['event_organisation'] = 'event_organisation_meta';
        $columns['organizer_name']   = 'organizer_name_meta';
        $columns['organizer_email']  = 'organizer_email_meta';
        $columns['event_start']      = 'event_start_date_meta';
        $columns['event_end']        = 'event_end_date_meta';

        return $columns;
    }

    public static function handle_event_admin_column_orderby( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'ead_event' ) {
            return;
        }

        $orderby = $query->get( 'orderby' );

        $meta_key_map = [
            'event_organisation_meta' => '_ead_event_organisation_id',
            'organizer_name_meta'   => 'event_organizer_name',
            'organizer_email_meta'  => 'event_organizer_email',
            'event_start_date_meta' => 'event_start_date',
            'event_end_date_meta'   => 'event_end_date',
        ];

        if ( isset( $meta_key_map[ $orderby ] ) ) {
            $query->set( 'meta_key', $meta_key_map[ $orderby ] );
            $query->set( 'orderby', ( $orderby === 'event_organisation_meta' ) ? 'meta_value_num' : 'meta_value' );

            if ( $orderby === 'event_start_date_meta' || $orderby === 'event_end_date_meta' ) {
                $query->set( 'meta_type', 'DATE' );
            }
        }
    }

    public static function add_event_status_filter_and_export_button( $post_type ) {
        if ( $post_type === 'ead_event' ) {
            $current_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
            ?>
            <select name="post_status" id="post_status_filter">
                <option value=""><?php esc_html_e( 'All Statuses', self::TEXT_DOMAIN ); ?></option>
                <option value="publish" <?php selected( $current_status, 'publish' ); ?>><?php esc_html_e( 'Published', self::TEXT_DOMAIN ); ?></option>
                <option value="pending" <?php selected( $current_status, 'pending' ); ?>><?php esc_html_e( 'Pending Review', self::TEXT_DOMAIN ); ?></option>
                <option value="draft" <?php selected( $current_status, 'draft' ); ?>><?php esc_html_e( 'Draft', self::TEXT_DOMAIN ); ?></option>
                <option value="trash" <?php selected( $current_status, 'trash' ); ?>><?php esc_html_e( 'Trash', self::TEXT_DOMAIN ); ?></option>
            </select>
            <?php

            if ( current_user_can( 'edit_others_posts' ) && ( empty( $current_status ) || $current_status === 'pending' ) ) {
                $export_url = add_query_arg(
                    [
                        'action'  => 'ead_export_pending_events_csv',
                        '_wpnonce' => wp_create_nonce( 'ead_export_pending_csv_nonce' ),
                    ],
                    admin_url( 'admin-post.php' )
                );

                echo '<a href="' . esc_url( $export_url ) . '" class="button button-secondary" style="margin-left:10px;">' . esc_html__( 'Export Pending to CSV', self::TEXT_DOMAIN ) . '</a>';
            }
        }
    }

    public static function process_export_pending_csv() {
        // Nonce and capability checks are crucial here.
        if (
            ! isset( $_GET['_wpnonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ead_export_pending_csv_nonce' )
        ) {
            wp_die(
                esc_html__( 'Security check failed.', self::TEXT_DOMAIN ),
                esc_html__( 'Error', self::TEXT_DOMAIN ),
                [ 'response' => 403 ]
            );
        }

        if ( ! current_user_can( 'edit_others_posts' ) ) {
            wp_die(
                esc_html__( 'You do not have permission to export this data.', self::TEXT_DOMAIN ),
                esc_html__( 'Error', self::TEXT_DOMAIN ),
                [ 'response' => 403 ]
            );
        }

        $args = [
            'post_type'      => 'ead_event',
            'post_status'    => 'pending',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $events = get_posts( $args );

        if ( empty( $events ) ) {
            wp_safe_redirect( admin_url( 'edit.php?post_type=ead_event&exported_status=empty' ) );
            exit;
        }

        $filename = 'pending-events-' . date( 'Y-m-d' ) . '.csv';

        // Send HTTP headers to force download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        $headers = [
            __( 'ID', self::TEXT_DOMAIN ),
            __( 'Title', self::TEXT_DOMAIN ),
            __( 'Organizer Name', self::TEXT_DOMAIN ),
            __( 'Organizer Email', self::TEXT_DOMAIN ),
            __( 'Start Date', self::TEXT_DOMAIN ),
            __( 'End Date', self::TEXT_DOMAIN ),
            __( 'Description', self::TEXT_DOMAIN ),
            __( 'Submitted Date', self::TEXT_DOMAIN ),
            __( 'Linked Organization ID', self::TEXT_DOMAIN ),
            __( 'Linked Organization Name', self::TEXT_DOMAIN ),
        ];

        fputcsv( $output, $headers );

        foreach ( $events as $event ) {
            $org_id   = get_post_meta( $event->ID, '_ead_event_organisation_id', true );
            $org_name = '';

            if ( $org_id && get_post( $org_id ) ) {
                $org_name = get_the_title( $org_id );
            }

            fputcsv(
                $output,
                [
                    $event->ID,
                    $event->post_title,
                    get_post_meta( $event->ID, 'event_organizer_name', true ),
                    get_post_meta( $event->ID, 'event_organizer_email', true ),
                    get_post_meta( $event->ID, 'event_start_date', true ),
                    get_post_meta( $event->ID, 'event_end_date', true ),
                    wp_strip_all_tags( $event->post_content ),
                    get_the_date( 'Y-m-d H:i:s', $event->ID ),
                    $org_id,
                    $org_name,
                ]
            );
        }

        fclose( $output );
        exit;
    }

    public static function add_event_bulk_actions( $bulk_actions ) {
        $bulk_actions['bulk_approve'] = __( 'Approve Events', self::TEXT_DOMAIN );
        $bulk_actions['bulk_reject']  = __( 'Reject Events (Move to Trash)', self::TEXT_DOMAIN );

        return $bulk_actions;
    }

    public static function handle_event_bulk_actions( $redirect_to, $doaction, $post_ids ) {
        if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
            return $redirect_to;
        }

        $processed_count = 0;

        if ( $doaction === 'bulk_approve' ) {
            foreach ( $post_ids as $post_id_val ) {
                $post_id = intval( $post_id_val );

                if ( get_post_status( $post_id ) === 'pending' && current_user_can( 'publish_post', $post_id ) ) {
                    wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
                    $processed_count++;
                }
            }

            if ( $processed_count > 0 ) {
                $redirect_to = add_query_arg( 'bulk_approved', $processed_count, $redirect_to );
            }
        } elseif ( $doaction === 'bulk_reject' ) {
            foreach ( $post_ids as $post_id_val ) {
                $post_id = intval( $post_id_val );

                if ( current_user_can( 'delete_post', $post_id ) ) {
                    wp_trash_post( $post_id );
                    $processed_count++;
                }
            }

            if ( $processed_count > 0 ) {
                $redirect_to = add_query_arg( 'bulk_rejected', $processed_count, $redirect_to );
            }
        }

        return $redirect_to;
    }

    public static function display_event_bulk_action_notices() {
        global $pagenow, $typenow;

        if ( $pagenow === 'edit.php' && $typenow === 'ead_event' ) {
            if ( ! empty( $_REQUEST['bulk_approved'] ) ) {
                $count = intval( $_REQUEST['bulk_approved'] );

                printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( sprintf( _n( '%s event approved.', '%s events approved.', $count, self::TEXT_DOMAIN ), number_format_i18n( $count ) ) ) );
            }

            if ( ! empty( $_REQUEST['bulk_rejected'] ) ) {
                $count = intval( $_REQUEST['bulk_rejected'] );

                printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( sprintf( _n( '%s event rejected and moved to trash.', '%s events rejected and moved to trash.', $count, self::TEXT_DOMAIN ), number_format_i18n( $count ) ) ) );
            }

            if ( isset( $_GET['exported_status'] ) && $_GET['exported_status'] === 'empty' ) {
                echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'No pending events found to export.', self::TEXT_DOMAIN ) . '</p></div>';
            }
        }
    }

    public static function add_event_admin_list_styles() {
        global $pagenow, $typenow;

        if ( $pagenow === 'edit.php' && $typenow === 'ead_event' ) {
            echo '<style>
                .post-type-ead_event tr.status-pending { background-color:#fff5e0 !important; }
                .post-type-ead_event tr.status-publish { background-color:#eaffea !important; }
                .post-type-ead_event .column-title .post-state {
                    background:#ffd080; color:#835b00; border-radius:3px;
                    padding:0 4px; margin-left:6px; font-size:11px;
                }
            </style>';
        }
    }

    public static function notify_user_on_organization_approval( $new_status, $old_status, $post ) {
        if ( $post instanceof \WP_Post && $post->post_type === 'ead_organization' && $old_status === 'pending' && $new_status === 'publish' ) {
            $user_id   = $post->post_author;
            $user_data = get_userdata( $user_id );

            if ( $user_data && ! empty( $user_data->user_email ) ) {
                $org_name          = $post->post_title;
                $user_display_name = $user_data->display_name;

                $subject = sprintf( esc_html__( 'Your Organization "%s" has been Approved!', self::TEXT_DOMAIN ), $org_name );

                $body   = sprintf( esc_html__( "Hello %s,\n\n", self::TEXT_DOMAIN ), esc_html( $user_display_name ) );
                $body  .= sprintf( esc_html__( "Great news! Your organization submission:\n\n\"%s\"\n\nhas been approved and is now live on our website.\n\n", self::TEXT_DOMAIN ), esc_html( $org_name ) );
                $body  .= sprintf( esc_html__( "You can view it here: %s\n\n", self::TEXT_DOMAIN ), esc_url( get_permalink( $post->ID ) ) );
                $body  .= esc_html__( "You can now proceed to submit events associated with your organization.\n\n", self::TEXT_DOMAIN ) . get_bloginfo( 'name' );

                $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

                wp_mail( $user_data->user_email, $subject, $body, $headers );
            }
        }
    }

    public static function notify_organizer_on_event_approval( $new_status, $old_status, $post ) {
        if ( $post instanceof \WP_Post && $post->post_type === 'ead_event' && $old_status === 'pending' && $new_status === 'publish' ) {
            $organizer_email = get_post_meta( $post->ID, 'event_organizer_email', true );
            $organizer_name  = get_post_meta( $post->ID, 'event_organizer_name', true );
            $event_title     = get_the_title( $post->ID );

            if ( $organizer_email && is_email( $organizer_email ) ) {
                $subject = sprintf( esc_html__( 'Your event "%s" has been approved!', self::TEXT_DOMAIN ), $event_title );

                $body   = sprintf( esc_html__( "Hello %s,\n\n", self::TEXT_DOMAIN ), esc_html( $organizer_name ) );
                $body  .= esc_html__( "Great news! Your event submission:\n\n", self::TEXT_DOMAIN ) . '"' . esc_html( $event_title ) . "\"\n\n" . esc_html__( "has been approved and published on our website.\n\n", self::TEXT_DOMAIN );
                $body  .= sprintf( esc_html__( "You can view it here: %s\n\n", self::TEXT_DOMAIN ), esc_url( get_permalink( $post->ID ) ) );
                $body  .= esc_html__( "Thank you for sharing your event with us!\n", self::TEXT_DOMAIN ) . get_bloginfo( 'name' );

                $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
                wp_mail( $organizer_email, $subject, $body, $headers );
            }
        }
    }

    public static function notify_admin_on_event_pending( $new_status, $old_status, $post ) {
        if ( $post instanceof \WP_Post && $post->post_type === 'ead_event' && $new_status === 'pending' && $old_status !== 'pending' ) {
            $settings = get_option( 'artpulse_notification_settings', [] );
            if ( isset( $settings['new_event_submission_notification'] ) && ! $settings['new_event_submission_notification'] ) {
                return;
            }

            $admin_email = get_option( 'admin_email' );
            $event_title = get_the_title( $post->ID );
            $subject     = sprintf( esc_html__( 'New event "%s" awaiting approval', self::TEXT_DOMAIN ), $event_title );
            $body        = sprintf( esc_html__( 'A new event submission titled "%s" is awaiting approval.', self::TEXT_DOMAIN ), $event_title ) . "\n\n";
            $body       .= esc_html__( 'Review it here:', self::TEXT_DOMAIN ) . ' ' . esc_url( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) );

            $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

            wp_mail( $admin_email, $subject, $body, $headers );
        }
    }
}

// Activation and Deactivation Hooks for RolesManager
if ( class_exists( RolesManager::class ) ) {
    register_activation_hook( __FILE__, [ RolesManager::class, 'add_roles' ] );
    register_deactivation_hook( __FILE__, [ RolesManager::class, 'remove_roles' ] );
    add_action( 'init', [ RolesManager::class, 'init' ] );
}

// Initialize the main plugin class
add_action( 'plugins_loaded', [ Plugin::class, 'init' ] );

