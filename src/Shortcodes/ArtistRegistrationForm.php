<?php
namespace EAD\Shortcodes;

use EAD\Shortcodes\HoneypotTrait;

/**
 * Class ArtistRegistrationForm
 *
 * Renders the artist registration form and handles submissions.
 *
 * @package EAD\Shortcodes
 */
class ArtistRegistrationForm {
    use HoneypotTrait;

    /**
     * Initialize the shortcode.
     */
    public static function register() {
        add_shortcode('ap_artist_registration_form', [self::class, 'render_form']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * Enqueue the artist registration CSS and JavaScript.
     */
    public static function enqueue_assets() {
        $plugin_url = EAD_PLUGIN_DIR_URL; // points to /assets/

        wp_enqueue_style(
            'artist-registration',
            $plugin_url . 'assets/css/artist-registration.css',
            [],
            EAD_MANAGEMENT_VERSION
        );

        wp_enqueue_media();

        wp_enqueue_script(
            'ead-artist-gallery',
            $plugin_url . 'assets/js/artist-gallery.js',
            ['jquery'],
            EAD_MANAGEMENT_VERSION,
            true
        );

        wp_localize_script(
            'ead-artist-gallery',
            'eadArtistGallery',
            [
                'select_image_title' => __( 'Select or Upload Image', 'artpulse-management' ),
                'use_image_button'   => __( 'Use this image', 'artpulse-management' ),
                'placeholder_prefix' => __( 'Image ', 'artpulse-management' ),
            ]
        );

        wp_enqueue_style('select2', $plugin_url . 'assets/select2/css/select2.min.css');
        wp_enqueue_script('select2', $plugin_url . 'assets/select2/js/select2.min.js', ['jquery'], null, true);

        wp_enqueue_script(
            'ead-address',
            $plugin_url . 'assets/js/ead-address.js',
            ['jquery', 'select2'],
            EAD_MANAGEMENT_VERSION,
            true
        );

        $geonames_username = \EAD\Admin\SettingsPage::get_setting('geonames_username');

        wp_localize_script(
            'ead-address',
            'eadAddress',
            [
                'countriesJson'    => $plugin_url . 'data/countries.json',
                'ajaxUrl'          => admin_url('admin-ajax.php'),
                'statesNonce'      => wp_create_nonce('ead_load_states'),
                'citiesNonce'      => wp_create_nonce('ead_search_cities'),
                'geonamesUsername' => $geonames_username,
            ]
        );

        wp_enqueue_script(
            'artist-registration',
            $plugin_url . 'assets/js/artist-registration.js',
            ['jquery', 'wp-util', 'ead-address', 'ead-artist-gallery'],
            EAD_MANAGEMENT_VERSION,
            true
        );

        wp_localize_script(
            'artist-registration',
            'eadArtistRegistration',
            [
                'restUrl'          => esc_url_raw( rest_url( 'artpulse/v1/artists' ) ),
                'nonce'            => wp_create_nonce( 'wp_rest' ),
                'confirmationUrl'  => home_url( '/artist-dashboard/' ),
            ]
        );
    }

    /**
     * Render the registration form.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function render_form( $atts = [] ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in.', 'artpulse-management' ) . '</p>';
        }

        $level = get_user_meta( get_current_user_id(), 'membership_level', true );
        if ( 'pro' !== $level ) {
            return '<p>' . esc_html__( 'You must be a Pro Artist member to apply.', 'artpulse-management' ) . '</p>';
        }

        do_action( 'artpulse_before_form' );
        ob_start();
        ?>
        <form method="post" enctype="multipart/form-data" class="ap-artist-registration-form">
            <?php wp_nonce_field('ap_artist_register', 'ap_artist_register_nonce'); ?>

            <div class="form-group">
                <label for="artist_username"><?php esc_html_e('Username', 'artpulse-management'); ?></label>
                <input type="text" id="artist_username" name="artist_username" required>
            </div>

            <div class="form-group">
                <label for="registration_email"><?php esc_html_e('Email', 'artpulse-management'); ?></label>
                <input type="email" id="registration_email" name="registration_email" required>
            </div>

            <div class="form-group">
                <label for="artist_name"><?php esc_html_e('Artist Name', 'artpulse-management'); ?></label>
                <input type="text" id="artist_name" name="artist_name">
            </div>

            <div class="form-group">
                <label for="artist_email"><?php esc_html_e('Artist Email', 'artpulse-management'); ?></label>
                <input type="email" id="artist_email" name="artist_email">
            </div>

            <div class="form-group">
                <label for="artist_password"><?php esc_html_e('Password', 'artpulse-management'); ?></label>
                <input type="password" id="artist_password" name="artist_password" required>
            </div>

            <div class="form-group">
                <label for="artist_password_confirm"><?php esc_html_e('Confirm Password', 'artpulse-management'); ?></label>
                <input type="password" id="artist_password_confirm" name="artist_password_confirm" required>
            </div>

            <div class="form-group">
                <label for="artist_display_name"><?php esc_html_e('Display Name', 'artpulse-management'); ?></label>
                <input type="text" id="artist_display_name" name="artist_display_name" required>
            </div>

            <div class="form-group">
                <label for="artist_bio"><?php esc_html_e('Biography', 'artpulse-management'); ?></label>
                <textarea id="artist_bio" name="artist_bio" rows="4"></textarea>
            </div>

            <div class="form-group">
                <label for="artist_website"><?php esc_html_e('Website', 'artpulse-management'); ?></label>
                <input type="url" id="artist_website" name="artist_website">
            </div>

            <div class="form-group">
                <label for="artist_phone"><?php esc_html_e('Phone Number', 'artpulse-management'); ?></label>
                <input type="tel" id="artist_phone" name="artist_phone">
            </div>

            <fieldset>
                <legend><?php esc_html_e('Address', 'artpulse-management'); ?></legend>
                <label for="ead_country"><?php esc_html_e('Country', 'artpulse-management'); ?></label>
                <select id="ead_country" name="ead_country" required></select>

                <label for="ead_state"><?php esc_html_e('State', 'artpulse-management'); ?></label>
                <select id="ead_state" name="ead_state" disabled required></select>

                <label for="ead_city"><?php esc_html_e('City', 'artpulse-management'); ?></label>
                <select id="ead_city" name="ead_city" disabled required></select>

                <label for="ead_suburb"><?php esc_html_e('Suburb', 'artpulse-management'); ?></label>
                <input type="text" id="ead_suburb" name="ead_suburb">

                <label for="ead_street"><?php esc_html_e('Street Address', 'artpulse-management'); ?></label>
                <input type="text" id="ead_street" name="ead_street">

                <label for="ead_postcode"><?php esc_html_e('Postcode', 'artpulse-management'); ?></label>
                <input type="text" id="ead_postcode" name="ead_postcode">

                <input type="hidden" id="ead_latitude" name="ead_latitude">
                <input type="hidden" id="ead_longitude" name="ead_longitude">
            </fieldset>

            <div class="form-group">
                <label for="artist_portrait"><?php esc_html_e('Profile Picture', 'artpulse-management'); ?></label>
                <input type="hidden" id="artist_portrait" name="artist_portrait" value="">
                <button type="button" id="artist_select_image" class="button"><?php esc_html_e('Select Profile Image', 'artpulse-management'); ?></button>
                <button type="button" id="artist_remove_image" class="button" style="display:none;"><?php esc_html_e('Remove Image', 'artpulse-management'); ?></button>
                <br>
                <img id="artist-profile-image-preview" src="#" alt="<?php esc_attr_e('Profile Picture Preview', 'artpulse-management'); ?>" style="display: none; max-width: 200px; margin-top: 10px;">
            </div>

            <div class="form-group">
                <label for="artist_instagram"><?php esc_html_e('Instagram Handle', 'artpulse-management'); ?></label>
                <input type="text" id="artist_instagram" name="artist_instagram">
            </div>

            <div class="form-group">
                <label for="artist_facebook"><?php esc_html_e('Facebook Page', 'artpulse-management'); ?></label>
                <input type="url" id="artist_facebook" name="artist_facebook">
            </div>

            <div class="form-group">
                <label for="artist_twitter"><?php esc_html_e('Twitter Handle', 'artpulse-management'); ?></label>
                <input type="text" id="artist_twitter" name="artist_twitter">
            </div>

        <div class="form-group">
            <label for="artist_linkedin"><?php esc_html_e('LinkedIn URL', 'artpulse-management'); ?></label>
            <input type="url" id="artist_linkedin" name="artist_linkedin">
        </div>

        <div class="form-group">
            <label><?php esc_html_e( 'Gallery Images', 'artpulse-management' ); ?></label>
            <div class="ead-artist-image-upload-area">
                <?php
                $max_images = 5;
                for ( $i = 0; $i < $max_images; $i++ ) :
                    ?>
                    <div class="ead-image-upload-container" data-image-index="<?php echo $i; ?>">
                        <div class="ead-image-preview"><span class="placeholder"><?php printf( esc_html__( 'Image %d', 'artpulse-management' ), $i + 1 ); ?></span></div>
                        <input type="hidden" name="artist_gallery_images[]" class="ead-image-id-input" value="">
                        <button type="button" class="button ead-upload-image-button"><?php esc_html_e( 'Select Image', 'artpulse-management' ); ?></button>
                        <button type="button" class="button ead-remove-image-button hidden"><?php esc_html_e( 'Remove Image', 'artpulse-management' ); ?></button>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

            <?php echo self::render_honeypot( $atts ); ?>
            <button type="submit" class="button">
                <?php esc_html_e('Register as Artist', 'artpulse-management'); ?>
            </button>
        </form>
        <?php
        $html = ob_get_clean();
        $html = apply_filters( 'artpulse_form_output', $html );
        return $html;
    }

    /**
     * Handle form submission.
     */
    public static function handle_submission() {
        if (
            'POST' !== $_SERVER['REQUEST_METHOD'] ||
            empty($_POST['ap_artist_register_nonce']) ||
            !wp_verify_nonce($_POST['ap_artist_register_nonce'], 'ap_artist_register')
        ) {
            return;
        }

        if ( self::honeypot_triggered() ) {
            wp_die( __( 'Spam detected.', 'artpulse-management' ) );
        }

        // Validate core fields
        $username   = sanitize_user($_POST['artist_username']);
        $email      = sanitize_email($_POST['registration_email']);
        $password   = $_POST['artist_password'];
        $confirm    = $_POST['artist_password_confirm'];
        $display    = sanitize_text_field($_POST['artist_display_name']);

        // Optional extra fields
        $artist_email = sanitize_email($_POST['artist_email'] ?? $email);
        $_POST['artist_email'] = $artist_email;

        if (empty($username) || empty($email) || empty($password) || empty($confirm) || empty($display)) {
            wp_die(__('All required fields must be completed.', 'artpulse-management'));
        }

        if ($password !== $confirm) {
            wp_die(__('Passwords do not match.', 'artpulse-management'));
        }

        if (username_exists($username) || email_exists($email)) {
            wp_die(__('Username or email already exists.', 'artpulse-management'));
        }

        // Create user
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            wp_die($user_id->get_error_message());
        }

        // Set display name
        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $display
        ]);

        // Assign role
        $user = new \WP_User($user_id);
        $user->set_role('artist');

        // Create the ead_artist post
        $artist_post_args = [
            'post_title' => $display, // Or use the username
            'post_type' => 'ead_artist',
            'post_status' => 'pending', // Or 'pending' if you want admin approval
            'post_author' => $user_id,
        ];

        $artist_post_id = wp_insert_post( $artist_post_args, true );

        if ( is_wp_error( $artist_post_id ) ) {
            wp_die( __( 'Error creating artist profile.', 'artpulse-management' ) . ' ' . $artist_post_id->get_error_message() );
        }

        // Save extra profile fields as post meta for the ead_artist post
        $extra_fields = [
            'artist_name',
            'artist_email',
            'artist_bio',
            'artist_website',
            'artist_phone',
            'artist_instagram',
            'artist_facebook',
            'artist_twitter',
            'artist_linkedin',
            'ead_country',
            'ead_state',
            'ead_city',
            'ead_suburb',
            'ead_street',
            'ead_postcode',
            'ead_latitude',
            'ead_longitude'
        ];
        foreach ($extra_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($artist_post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }

        // Link the user to the artist post
        update_user_meta( $user_id, 'ead_artist_post_id', $artist_post_id );

        // Handle profile picture from media library or file upload
        if ( ! empty( $_POST['artist_portrait'] ) ) {
            update_post_meta( $artist_post_id, 'artist_portrait', intval( $_POST['artist_portrait'] ) );
        } elseif ( ! empty( $_FILES['artist_profile_picture']['name'] ) ) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $file      = $_FILES['artist_profile_picture'];
            $max_size  = 2 * 1024 * 1024; // 2 MB limit
            if ( $file['size'] > $max_size ) {
                wp_die( __( 'Profile picture must not exceed 2 MB.', 'artpulse-management' ) );
            }
            $overrides = [
                'test_form' => false,
                'mimes'     => ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif']
            ];

            $uploaded = wp_handle_upload($file, $overrides);

            if (isset($uploaded['error'])) {
                wp_die(__('Error uploading profile picture: ', 'artpulse-management') . $uploaded['error']);
            } else {
                // Attach to media library
                $attachment = [
                    'post_mime_type' => $uploaded['type'],
                    'post_title'     => sanitize_file_name(basename($uploaded['file'])),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                ];

                $attach_id = wp_insert_attachment($attachment, $uploaded['file'], $artist_post_id); //Associate with the artist post
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);

                // Save attachment ID as post meta
                update_post_meta($artist_post_id, 'artist_portrait', $attach_id); //Use the same meta key as MetaBoxesArtist
            }
        }

        // Save gallery image IDs
        if ( ! empty( $_POST['artist_gallery_images'] ) && is_array( $_POST['artist_gallery_images'] ) ) {
            $gallery_ids = [];
            foreach ( $_POST['artist_gallery_images'] as $img_id ) {
                $id = absint( $img_id );
                if ( $id ) {
                    $gallery_ids[] = $id;
                }
            }
            if ( $gallery_ids ) {
                $gallery_ids = array_slice( array_unique( $gallery_ids ), 0, 5 );
                update_post_meta( $artist_post_id, 'artist_gallery_images', $gallery_ids );
            }
        }

        // Optionally log them in immediately
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        // Redirect to the Artist Dashboard (adjust URL as needed)
        wp_safe_redirect(home_url('/artist-dashboard/'));
        exit;
    }
}
