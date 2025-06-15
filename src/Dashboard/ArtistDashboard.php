<?php
namespace EAD\Dashboard;

class ArtistDashboard {
    public static function init() {
        add_shortcode('ead_artist_dashboard', [self::class, 'render_dashboard']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_menu', [self::class, 'add_admin_menu']);
        add_action('template_redirect', [self::class, 'handle_profile_submission']);
        add_action('template_redirect', [self::class, 'handle_events_bulk_action']);
        add_action('wp_loaded', [self::class, 'handle_featured_request_submission']);
        add_action('admin_init', [self::class, 'add_admin_capability']);
        add_action('rest_api_init', ['EAD\Rest\ModerationEndpoint', 'register']);


        add_action('rest_api_init', function() {
            register_rest_route('artpulse/v1', '/moderate-event', [
                'methods'  => 'POST',
                'callback' => [self::class, 'handle_event_moderation'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                },
            ]);
        });
    }

    public static function add_admin_capability() {
        $role = get_role('administrator');
        if ($role && !$role->has_cap('view_dashboard')) {
            $role->add_cap('view_dashboard');
            $role->add_cap('edit_others_posts');
            $role->add_cap('delete_others_posts');
        }
    }

    /**
     * Add the Artist Dashboard page to the admin menu.
     */
    public static function add_admin_menu() {
        add_menu_page(
            __('Artist Dashboard Admin', 'artpulse-management'),
            __('Artist Dashboard Admin', 'artpulse-management'),
            'view_dashboard',
            'ead-artist-dashboard',
            [self::class, 'render_admin_page'],
            'dashicons-art',
            27
        );
    }

    /**
     * Render the Artist Dashboard admin page.
     */
    public static function render_admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Artist Dashboard Admin', 'artpulse-management') . '</h1>';
        echo do_shortcode('[ead_artist_dashboard]');
        echo '</div>';
    }

    public static function render_dashboard($atts) {
        if (!current_user_can('view_dashboard')) {
            return '<p>' . esc_html__('You do not have permission to view this dashboard.', 'artpulse-management') . '</p>';
        }

        ob_start();
        ?>
        <div id="ead-artist-dashboard" class="ead-artist-dashboard">
            <h2><?php esc_html_e('Artist Dashboard', 'artpulse-management'); ?></h2>

            <?php
            $current_user = wp_get_current_user();

            // Admin Artist Switcher
            if (current_user_can('manage_options')) {
                $artists = get_users(['role' => 'artist']);
                echo '<form method="get" id="ead-artist-switcher">';
                echo '<label for="artist_id">' . esc_html__('Select Artist:', 'artpulse-management') . '</label> ';
                echo '<select name="artist_id" id="artist_id">';
                echo '<option value="">' . esc_html__('My Dashboard', 'artpulse-management') . '</option>';
                foreach ($artists as $artist) {
                    $selected = (isset($_GET['artist_id']) && $_GET['artist_id'] == $artist->ID) ? 'selected' : '';
                    echo '<option value="' . esc_attr($artist->ID) . '" ' . $selected . '>' . esc_html($artist->display_name) . '</option>';
                }
                echo '</select> ';
                echo '<input type="submit" class="button" value="' . esc_attr__('Switch', 'artpulse-management') . '">';
                echo '</form>';

                // Override $current_user if an artist is selected
                if (!empty($_GET['artist_id'])) {
                    $selected_user = get_user_by('id', (int) $_GET['artist_id']);
                    if ($selected_user && in_array('artist', $selected_user->roles)) {
                        $current_user = $selected_user;
                    }
                }
            }
            ?>

            <!-- 📌 Profile Management -->
            <section id="ead-artist-profile" class="ead-dashboard-section">
                <h3><?php esc_html_e('Profile Management', 'artpulse-management'); ?></h3>
                <p><?php esc_html_e('Edit your artist profile details here.', 'artpulse-management'); ?></p>
                <div id="ead-artist-profile-form">
                    <?php self::render_profile_form($current_user); ?>
                </div>
            </section>

            <!-- 📌 Events Management -->
            <section id="ead-artist-events" class="ead-dashboard-section">
                <h3><?php esc_html_e('Events Management', 'artpulse-management'); ?></h3>
                <p><?php esc_html_e('Manage your events here.', 'artpulse-management'); ?></p>
                <div id="ead-artist-events-list">
                    <?php self::render_events_table($current_user); ?>
                </div>
            </section>

            <!-- 📌 Performance Metrics -->
            <section id="ead-artist-metrics" class="ead-dashboard-section">
                <h3><?php esc_html_e('Performance Metrics', 'artpulse-management'); ?></h3>
                <p><?php esc_html_e('View your performance statistics here.', 'artpulse-management'); ?></p>
                <div id="ead-artist-metrics-widgets">
                    <?php self::render_metrics($current_user); ?>
                </div>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function enqueue_assets() {
        $plugin_url = EAD_PLUGIN_DIR_URL;

        wp_enqueue_style(
            'ead-artist-dashboard',
            $plugin_url . 'assets/css/artist-dashboard.css',
            [],
            EAD_MANAGEMENT_VERSION
        );

        wp_enqueue_style(
            'ead-artist-gallery',
            $plugin_url . 'assets/css/artist-gallery.css',
            [],
            EAD_MANAGEMENT_VERSION
        );

        wp_enqueue_media();

        wp_enqueue_script(
            'ead-artist-dashboard',
            $plugin_url . 'assets/js/artist-dashboard.js',
            ['jquery'],
            EAD_MANAGEMENT_VERSION,
            true
        );

        wp_enqueue_script(
            'ead-artist-gallery',
            $plugin_url . 'assets/js/artist-gallery.js',
            ['jquery'],
            EAD_MANAGEMENT_VERSION,
            true
        );

        wp_enqueue_script(
            'ead-artist-gallery-sortable',
            $plugin_url . 'assets/js/artist-gallery-sortable.js',
            ['jquery', 'jquery-ui-sortable', 'ead-artist-gallery'],
            EAD_MANAGEMENT_VERSION,
            true
        );

        wp_localize_script('ead-artist-dashboard', 'eadDashboardApi', [
            'restUrl' => esc_url_raw(rest_url('artpulse/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        wp_localize_script(
            'ead-artist-gallery',
            'eadArtistGallery',
            [
                'select_image_title' => __('Select or Upload Image', 'artpulse-management'),
                'use_image_button'   => __('Use this image', 'artpulse-management'),
                'placeholder_prefix' => __('Image ', 'artpulse-management'),
            ]
        );
    }

     public static function render_profile_form($profile_user = null) {
        if (!$profile_user) {
            $profile_user = wp_get_current_user();
        }

        if (!current_user_can('view_dashboard')) {
            echo '<p>' . esc_html__('You do not have permission to edit this profile.', 'artpulse-management') . '</p>';
            return;
        }

        if (!in_array('artist', $profile_user->roles) && !current_user_can('administrator')) {
            echo '<p>' . esc_html__('You do not have permission to edit this profile.', 'artpulse-management') . '</p>';
            return;
        }

        //Get the artist post id from the user meta
        $artist_post_id = get_user_meta($profile_user->ID, 'ead_artist_post_id', true);

        if(!$artist_post_id){
            echo '<p>' . esc_html__('No artist profile found for this user.', 'artpulse-management') . '</p>';
            return;
        }

        $bio       = get_post_meta($artist_post_id, 'artist_bio', true);
        $website   = get_post_meta($artist_post_id, 'artist_website', true);
        $phone     = get_post_meta($artist_post_id, 'artist_phone', true);
        $instagram = get_post_meta($artist_post_id, 'artist_instagram', true);
        $facebook  = get_post_meta($artist_post_id, 'artist_facebook', true);
        $twitter   = get_post_meta($artist_post_id, 'artist_twitter', true);
        $linkedin  = get_post_meta($artist_post_id, 'artist_linkedin', true);
        $portrait_id  = get_post_meta($artist_post_id, 'artist_portrait', true);
        $portrait_url = '';
        if ( $portrait_id ) {
            $info = wp_get_attachment_image_src( $portrait_id, 'thumbnail' );
            if ( $info ) {
                $portrait_url = $info[0];
            }
        }
        $gallery_ids = get_post_meta($artist_post_id, 'artist_gallery_images', true);
        if (!is_array($gallery_ids)) {
            $gallery_ids = [];
        }
        ?>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('ead_artist_profile_update', 'ead_artist_profile_nonce'); ?>
            <input type="hidden" name="artist_id" value="<?php echo esc_attr($profile_user->ID); ?>">
            <input type="hidden" name="artist_post_id" value="<?php echo esc_attr($artist_post_id); ?>">
            <p>
                <label for="artist_bio"><?php esc_html_e('Biography', 'artpulse-management'); ?></label><br>
                <textarea name="artist_bio" id="artist_bio" rows="4" style="width:100%;"><?php echo esc_textarea($bio); ?></textarea>
            </p>
            <p>
                <label for="artist_website"><?php esc_html_e('Website', 'artpulse-management'); ?></label><br>
                <input type="text" name="artist_website" id="artist_website" style="width:100%;"  value="<?php echo esc_attr($website); ?>">
            </p>
            <p>
                <label for="artist_phone"><?php esc_html_e('Phone', 'artpulse-management'); ?></label><br>
                <input type="text" name="artist_phone" id="artist_phone" style="width:100%;"  value="<?php echo esc_attr($phone); ?>">
            </p>
            <p>
                <label for="artist_instagram"><?php esc_html_e('Instagram', 'artpulse-management'); ?></label><br>
                <input type="text" name="artist_instagram" id="artist_instagram"  style="width:100%;" value="<?php echo esc_attr($instagram); ?>">
            </p>
            <p>
                <label for="artist_facebook"><?php esc_html_e('Facebook', 'artpulse-management'); ?></label><br>
                <input type="text" name="artist_facebook" id="artist_facebook" style="width:100%;"  value="<?php echo esc_attr($facebook); ?>">
            </p>
            <p>
                <label for="artist_twitter"><?php esc_html_e('Twitter', 'artpulse-management'); ?></label><br>
                <input type="text" name="artist_twitter" id="artist_twitter" style="width:100%;" value="<?php echo esc_attr($twitter); ?>">
            </p>
            <p>
                <label for="artist_linkedin"><?php esc_html_e('LinkedIn', 'artpulse-management'); ?></label><br>
                <input type="url" name="artist_linkedin" id="artist_linkedin" style="width:100%;" value="<?php echo esc_attr($linkedin); ?>">
            </p>
            <p>
                <label for="artist_portrait"><?php esc_html_e('Portrait', 'artpulse-management'); ?></label><br>
                <?php if ($portrait_url): ?>
                    <img src="<?php echo esc_url($portrait_url); ?>" alt="<?php esc_attr_e('Current Portrait', 'artpulse-management'); ?>" style="max-width:150px;display:block;margin-bottom:10px;" />
                <?php endif; ?>
                <input type="file" name="artist_portrait" id="artist_portrait" accept="image/*" />
                <input type="hidden" name="existing_portrait_id" value="<?php echo esc_attr($portrait_id); ?>" />
            </p>

            <p>
                <label><?php esc_html_e('Gallery Images', 'artpulse-management'); ?></label>
                <div class="ead-artist-image-upload-area">
                    <?php
                    $max_images = 5;
                    for ($i = 0; $i < $max_images; $i++):
                        $img_id = $gallery_ids[$i] ?? '';
                        $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';
                        ?>
                        <div class="ead-image-upload-container" data-image-index="<?php echo $i; ?>">
                            <div class="ead-image-preview<?php echo $img_url ? ' has-image' : ''; ?>"<?php echo $img_url ? ' style="background-image:url(' . esc_url($img_url) . ')"' : ''; ?>>
                                <?php if (!$img_url): ?>
                                    <span class="placeholder"><?php printf(esc_html__('Image %d', 'artpulse-management'), $i + 1); ?></span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="artist_gallery_images[]" class="ead-image-id-input" value="<?php echo esc_attr($img_id); ?>">
                            <button type="button" class="button ead-upload-image-button"><?php esc_html_e('Select Image', 'artpulse-management'); ?></button>
                            <button type="button" class="button ead-remove-image-button<?php echo $img_id ? '' : ' hidden'; ?>"><?php esc_html_e('Remove Image', 'artpulse-management'); ?></button>
                        </div>
                    <?php endfor; ?>
                </div>
            </p>
            <p>
                <input type="submit" name="ead_artist_profile_submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'artpulse-management'); ?>">
            </p>
        </form>
        <?php
        $payment_status = get_post_meta($artist_post_id, '_ead_featured_payment_status', true);
        $payment_url    = get_post_meta($artist_post_id, '_ead_featured_payment_url', true);
        if (get_post_meta($artist_post_id, '_ead_featured', true)) {
            echo '<span class="ead-badge-featured"><span class="dashicons dashicons-star-filled"></span>' . esc_html__('Featured', 'artpulse-management') . '</span>';
        } elseif ($payment_status === 'pending' && $payment_url) {
            echo '<a href="' . esc_url($payment_url) . '" class="button button-small">' . esc_html__('Complete Payment', 'artpulse-management') . '</a>';
        } elseif (get_post_meta($artist_post_id, '_ead_featured_request', true)) {
            echo '<span class="ead-badge-requested"><span class="dashicons dashicons-star-filled"></span>' . esc_html__('Requested', 'artpulse-management') . '</span>';
        } else {
            ?>
            <form method="post" style="margin-top:10px;">
                <?php wp_nonce_field('ead_request_featured_' . $artist_post_id); ?>
                <input type="hidden" name="ead_request_featured_listing_id" value="<?php echo esc_attr($artist_post_id); ?>">
                <button type="submit" name="ead_request_featured_submit" class="button"><?php esc_html_e('Request Featured', 'artpulse-management'); ?></button>
            </form>
            <?php
        }
    }

    public static function handle_profile_submission() {
        if (
            !is_user_logged_in() ||
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            !isset($_POST['ead_artist_profile_nonce']) ||
            !wp_verify_nonce($_POST['ead_artist_profile_nonce'], 'ead_artist_profile_update')
        ) {
            return;
        }

        $current_user = wp_get_current_user();
        $artist_id = isset($_POST['artist_id']) ? (int) $_POST['artist_id'] : $current_user->ID;
        $artist_post_id = isset($_POST['artist_post_id']) ? (int) $_POST['artist_post_id'] : '';

        if (!current_user_can('administrator') && $artist_id !== $current_user->ID) {
            return;
        }

        $target_user = get_user_by('id', $artist_id);
        if (!$target_user || !in_array('artist', $target_user->roles)) {
            return;
        }

        if(!$artist_post_id){
            return;
        }

        $bio       = sanitize_textarea_field($_POST['artist_bio'] ?? '');
        $website   = sanitize_text_field($_POST['artist_website'] ?? '');
        $phone     = sanitize_text_field($_POST['artist_phone'] ?? '');
        $instagram = sanitize_text_field($_POST['artist_instagram'] ?? '');
        $facebook  = sanitize_text_field($_POST['artist_facebook'] ?? '');
        $twitter   = sanitize_text_field($_POST['artist_twitter'] ?? '');
        $linkedin  = esc_url_raw($_POST['artist_linkedin'] ?? '');

        $existing_portrait_id = isset($_POST['existing_portrait_id']) ? absint($_POST['existing_portrait_id']) : 0;
        $new_portrait_id      = $existing_portrait_id;

        if (!empty($_FILES['artist_portrait']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $file      = $_FILES['artist_portrait'];
            $overrides = [
                'test_form' => false,
                'mimes'     => ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'],
            ];

            $uploaded = wp_handle_upload($file, $overrides);

            if (empty($uploaded['error'])) {
                $attachment = [
                    'post_mime_type' => $uploaded['type'],
                    'post_title'     => sanitize_file_name(basename($uploaded['file'])),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];

                $attach_id  = wp_insert_attachment($attachment, $uploaded['file'], $artist_post_id);
                $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
                $new_portrait_id = $attach_id;
            }
        }

        update_post_meta($artist_post_id, 'artist_bio', $bio);
        update_post_meta($artist_post_id, 'artist_website', $website);
        update_post_meta($artist_post_id, 'artist_phone', $phone);
        update_post_meta($artist_post_id, 'artist_instagram', $instagram);
        update_post_meta($artist_post_id, 'artist_facebook', $facebook);
        update_post_meta($artist_post_id, 'artist_twitter', $twitter);
        update_post_meta($artist_post_id, 'artist_linkedin', $linkedin);

        if ($new_portrait_id) {
            update_post_meta($artist_post_id, 'artist_portrait', $new_portrait_id);

            if (!has_post_thumbnail($artist_post_id)) {
                set_post_thumbnail($artist_post_id, intval($new_portrait_id));
            }
        } else {
            delete_post_meta($artist_post_id, 'artist_portrait');
        }

        if (!empty($_POST['artist_gallery_images']) && is_array($_POST['artist_gallery_images'])) {
            $gallery_ids = array_map('absint', $_POST['artist_gallery_images']);
            $gallery_ids = array_filter($gallery_ids);
            $gallery_ids = array_slice(array_unique($gallery_ids), 0, 5);
            $valid_ids   = [];
            foreach ($gallery_ids as $gid) {
                $file = get_attached_file($gid);
                if ($file && filesize($file) <= 2 * 1024 * 1024) {
                    $valid_ids[] = $gid;
                }
            }
            if ($valid_ids) {
                update_post_meta($artist_post_id, 'artist_gallery_images', $valid_ids);
            } else {
                delete_post_meta($artist_post_id, 'artist_gallery_images');
            }
        } else {
            delete_post_meta($artist_post_id, 'artist_gallery_images');
        }

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Profile updated successfully.', 'artpulse-management') . '</p></div>';
        });
    }

    public static function render_events_table($profile_user = null) {
        if (!$profile_user) {
            $profile_user = wp_get_current_user();
        }

        $args = [
            'post_type'      => 'ead_event',
            'post_status'    => ['publish', 'pending', 'draft'],
            'posts_per_page' => -1,
            'author'         => $profile_user->ID,
        ];
        $events = get_posts($args);

        if (empty($events)) {
            echo '<p>' . esc_html__('No events found.', 'artpulse-management') . '</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Event Name', 'artpulse-management') . '</th>';
        echo '<th>' . esc_html__('Status', 'artpulse-management') . '</th>';
        echo '<th>' . esc_html__('Actions', 'artpulse-management') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($events as $event) {
            echo '<tr>';
            echo '<td>' . esc_html($event->post_title) . '</td>';
            echo '<td>' . esc_html(ucfirst($event->post_status)) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url(get_edit_post_link($event->ID)) . '" class="button">' . esc_html__('Edit', 'artpulse-management') . '</a> ';
            echo '<a href="' . esc_url(get_permalink($event->ID)) . '" class="button" target="_blank">' . esc_html__('View', 'artpulse-management') . '</a> ';
            if (current_user_can('manage_options')) {
                if ($event->post_status === 'pending') {
                    echo '<a href="#" class="button ead-event-action" data-action="approve" data-event-id="' . esc_attr($event->ID) . '">' . esc_html__('Approve', 'artpulse-management') . '</a> ';
                }
                echo '<a href="#" class="button ead-event-action" data-action="delete" data-event-id="' . esc_attr($event->ID) . '">' . esc_html__('Delete', 'artpulse-management') . '</a>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    public static function handle_events_bulk_action() {
        // Placeholder for bulk action logic.
    }

    public static function handle_featured_request_submission() {
        if (
            isset($_POST['ead_request_featured_submit']) &&
            isset($_POST['ead_request_featured_listing_id']) &&
            wp_verify_nonce($_POST['_wpnonce'], 'ead_request_featured_' . intval($_POST['ead_request_featured_listing_id']))
        ) {
            $listing_id = intval($_POST['ead_request_featured_listing_id']);
            $user_id    = get_current_user_id();
            $post       = get_post($listing_id);

            if (
                $post &&
                $post->post_author == $user_id &&
                $post->post_type === 'ead_artist'
            ) {
                update_post_meta($listing_id, '_ead_featured_request', '1');
                update_post_meta($listing_id, '_ead_featured_payment_status', 'pending');
                update_post_meta($listing_id, '_ead_featured_request_time', current_time('mysql'));

                $checkout = \EAD\Integration\WooCommercePayments::generate_checkout_url( $listing_id );
                if ( $checkout ) {
                    update_post_meta( $listing_id, '_ead_featured_payment_url', esc_url_raw( $checkout ) );
                }

                wp_mail(
                    get_option('admin_email'),
                    __('Featured Listing Request', 'artpulse-management'),
                    sprintf(
                        'User %d (%s) has requested their artist "%s" (ID %d) be featured.',
                        $user_id,
                        wp_get_current_user()->user_email,
                        get_the_title($listing_id),
                        $listing_id
                    )
                );

                add_action('wp_footer', function() use ( $checkout ) {
                    $msg = esc_html__('Your featured request was sent for review.', 'artpulse-management');
                    if ( $checkout ) {
                        $msg = esc_html__('Proceed to payment to complete your request.', 'artpulse-management') . ' <a href="' . esc_url( $checkout ) . '" class="button">' . esc_html__('Pay Now', 'artpulse-management') . '</a>';
                    }
                    echo '<div class="ead-featured-confirm" style="background:#eaffea;color:#308000;border-radius:8px;padding:10px 14px;position:fixed;bottom:32px;right:32px;z-index:99;">' . $msg . '</div>';
                });
            }
        }
    }

    public static function render_metrics($profile_user = null) {
        if (!$profile_user) {
            $profile_user = wp_get_current_user();
        }

        if (!in_array('artist', $profile_user->roles) && !current_user_can('administrator')) {
            echo '<p>' . esc_html__('You do not have permission to view metrics.', 'artpulse-management') . '</p>';
            return;
        }

        $args = [
            'post_type'      => 'ead_event',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'author'         => $profile_user->ID,
            'fields'         => 'ids',
        ];
        $artist_events = get_posts($args);
        $total_artist_events = count($artist_events);

        $published_count = 0;
        $pending_count = 0;

        foreach ($artist_events as $event_id) {
            $status = get_post_status($event_id);
            if ($status === 'publish') {
                $published_count++;
            } elseif ($status === 'pending') {
                $pending_count++;
            }
        }

        echo '<div class="ead-dashboard-metrics">';
        echo '<div class="ead-metric">';
        echo '<h4>' . esc_html__('Total Events', 'artpulse-management') . '</h4>';
        echo '<p>' . intval($total_artist_events) . '</p>';
        echo '</div>';

        echo '<div class="ead-metric">';
        echo '<h4>' . esc_html__('Published Events', 'artpulse-management') . '</h4>';
        echo '<p>' . intval($published_count) . '</p>';
        echo '</div>';

        echo '<div class="ead-metric">';
        echo '<h4>' . esc_html__('Pending Events', 'artpulse-management') . '</h4>';
        echo '<p>' . intval($pending_count) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<div id="ead-metrics-refresh">';
        echo '<button id="ead-refresh-metrics" class="button button-secondary">' . esc_html__('Refresh Metrics', 'artpulse-management') . '</button>';
        echo '</div>';
    }

    public static function handle_event_moderation($request) {
        $action = sanitize_text_field($request['action_type']);
        $event_id = absint($request['event_id']);

        if (!$event_id || !get_post($event_id)) {
            return new \WP_Error('invalid_event', __('Invalid event ID.', 'artpulse-management'), ['status' => 400]);
        }

        if ($action === 'approve') {
            wp_update_post(['ID' => $event_id, 'post_status' => 'publish']);
            return rest_ensure_response(['success' => true, 'message' => __('Event approved successfully.', 'artpulse-management')]);
        } elseif ($action === 'delete') {
            wp_delete_post($event_id, true);
            return rest_ensure_response(['success' => true, 'message' => __('Event deleted successfully.', 'artpulse-management')]);
        } else {
            return new \WP_Error('invalid_action', __('Invalid action.', 'artpulse-management'), ['status' => 400]);
        }
    }
}