<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\Capabilities;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use WP_Error;
use WP_Post;
use WP_User;

class OrganizationEventForm {

    private const ERROR_TRANSIENT_PREFIX = 'ap_org_event_errors_';
    private const MAX_IMAGE_BYTES = 10 * MB_IN_BYTES;
    private const MIN_IMAGE_DIMENSION = 200;

    public static function register() {
        add_shortcode('ap_org_submit_event', [self::class, 'render']);

        // Ensure the generic event submission shortcode continues to render the
        // shared public form when this class is loaded after
        // EventSubmissionShortcode. Without this guard the organization form
        // would override the shared shortcode registration, hiding the
        // organization selector and WooCommerce style notices.
        if (!shortcode_exists('ap_submit_event')) {
            add_shortcode('ap_submit_event', ['\\ArtPulse\\Frontend\\EventSubmissionShortcode', 'render']);
        }
    }

    public static function render() {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to submit an event.</p>';
        }

        $user_id   = get_current_user_id();
        $org_id    = isset($_GET['org_id']) ? absint($_GET['org_id']) : 0;
        $artist_id = isset($_GET['artist_id']) ? absint($_GET['artist_id']) : 0;

        if ($org_id && !PortfolioAccess::is_owner($user_id, $org_id)) {
            wp_die(__('Forbidden', 'artpulse-management'));
        }

        if ($artist_id && !PortfolioAccess::is_owner($user_id, $artist_id)) {
            wp_die(__('Forbidden', 'artpulse-management'));
        }

        if (!$org_id) {
            $org_id = self::get_user_org_id($user_id);
        }

        if (!$artist_id) {
            $artist_id = self::get_user_artist_id($user_id);
        }

        if (!$org_id && !$artist_id) {
            return '<p>' . esc_html__('You need an approved portfolio before submitting events.', 'artpulse-management') . '</p>';
        }

        if (!user_can($user_id, Capabilities::CAP_SUBMIT_EVENTS)) {
            return '<p>' . esc_html__('Your account is not allowed to submit events.', 'artpulse-management') . '</p>';
        }

        // Show success message if redirected after submission
        if (!empty($_GET['event_submitted'])) {
            echo '<div class="ap-success-message">✅ Event submitted successfully!</div>';
        }

        $errors = self::pull_errors($user_id);
        if (!empty($errors)) {
            echo '<div class="ap-error-message" role="alert">';
            echo '<ul>';
            foreach ($errors as $message) {
                echo '<li>' . esc_html($message) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ap_event_nonce']) && wp_verify_nonce($_POST['ap_event_nonce'], 'submit_event')) {
            self::handle_submission();
        }

        ob_start();
        ?>
        <div class="ap-form-messages" role="status" aria-live="polite"></div>
        <form method="post" enctype="multipart/form-data" class="ap-event-form">
            <?php wp_nonce_field('submit_event', 'ap_event_nonce'); ?>
            <input type="hidden" name="org_id" value="<?php echo esc_attr($org_id); ?>" />
            <input type="hidden" name="artist_id" value="<?php echo esc_attr($artist_id); ?>" />

            <label for="ap_org_event_title">Event Title*</label>
            <input id="ap_org_event_title" type="text" name="title" required data-test="event-title">

            <label for="ap_org_event_description">Description*</label>
            <textarea id="ap_org_event_description" name="description" required></textarea>

            <label for="ap_org_event_date">Event Date*</label>
            <input id="ap_org_event_date" type="date" name="event_date" required data-test="event-date">

            <label for="ap_org_event_location">Location*</label>
            <input id="ap_org_event_location" type="text" name="event_location" required>

            <label for="ap_org_event_type">Event Type</label>
            <select id="ap_org_event_type" name="event_type">
                <option value="">Select Type</option>
                <?php
                $terms = get_terms(['taxonomy' => 'artpulse_event_type', 'hide_empty' => false]);
                foreach ($terms as $term) {
                    echo '<option value="' . esc_attr($term->term_id) . '">' . esc_html($term->name) . '</option>';
                }
                ?>
            </select>

            <label for="ap_org_event_flyer">Event Flyer</label>
            <input id="ap_org_event_flyer" type="file" name="event_flyer" accept="image/jpeg,image/png,image/webp" data-test="event-flyer">
            <p class="description"><?php esc_html_e('Optional. JPG, PNG, or WebP. Max 10MB and at least 200×200 pixels.', 'artpulse-management'); ?></p>

            <button type="submit" data-test="event-submit">Submit Event</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function handle_submission(bool $should_redirect = true) {
        $user_id   = get_current_user_id();
        $org_id    = isset($_POST['org_id']) ? absint($_POST['org_id']) : 0;
        $artist_id = isset($_POST['artist_id']) ? absint($_POST['artist_id']) : 0;

        $errors = [];

        if ($user_id <= 0) {
            $errors[] = __('You do not have permission to submit this event.', 'artpulse-management');
            return self::maybe_handle_errors($errors, $should_redirect);
        }

        if ($org_id && !PortfolioAccess::is_owner($user_id, $org_id)) {
            $errors[] = __('You do not have permission to submit this event.', 'artpulse-management');
            return self::maybe_handle_errors($errors, $should_redirect);
        }

        if ($artist_id && !PortfolioAccess::is_owner($user_id, $artist_id)) {
            $errors[] = __('You do not have permission to submit this event.', 'artpulse-management');
            return self::maybe_handle_errors($errors, $should_redirect);
        }

        if (!$org_id && !$artist_id) {
            $errors[] = __('You must choose an organization or artist for this event.', 'artpulse-management');
            return self::maybe_handle_errors($errors, $should_redirect);
        }

        if (!user_can($user_id, Capabilities::CAP_SUBMIT_EVENTS)) {
            $errors[] = __('Your account is not allowed to submit events.', 'artpulse-management');
            return self::maybe_handle_errors($errors, $should_redirect);
        }

        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $description = wp_kses_post(wp_unslash($_POST['description'] ?? ''));
        $date = sanitize_text_field(wp_unslash($_POST['event_date'] ?? ''));
        $location = sanitize_text_field(wp_unslash($_POST['event_location'] ?? ''));
        $type = isset($_POST['event_type']) ? absint($_POST['event_type']) : 0;

        if ($title === '') {
            $errors[] = __('Please provide a title for the event.', 'artpulse-management');
        }

        if ($description === '') {
            $errors[] = __('Please provide a description for the event.', 'artpulse-management');
        }

        if ($date === '') {
            $errors[] = __('Please provide a date for the event.', 'artpulse-management');
        }

        if ($location === '') {
            $errors[] = __('Please provide a location for the event.', 'artpulse-management');
        }

        if (!empty($errors)) {
            return self::maybe_handle_errors($errors, $should_redirect);
        }

        $status = get_option('ap_require_event_review', true) ? 'pending' : 'publish';

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $description,
            'post_type'    => 'artpulse_event',
            'post_status'  => $status,
            'post_author'  => $user_id,
        ], true);

        if (is_wp_error($post_id)) {
            $errors[] = $post_id->get_error_message();
            return self::maybe_handle_errors($errors, $should_redirect);
        }

        update_post_meta($post_id, '_ap_event_date', $date);
        update_post_meta($post_id, '_ap_event_location', $location);
        update_post_meta($post_id, '_ap_event_organization', $org_id);
        update_post_meta($post_id, '_ap_org_id', $org_id);
        update_post_meta($post_id, '_ap_artist_id', $artist_id);

        if ($type) {
            wp_set_post_terms($post_id, [$type], 'artpulse_event_type');
        }

        if (!empty($_FILES['event_flyer']['name'])) {
            self::ensure_media_dependencies();
            $file = self::prepare_file_array($_FILES['event_flyer']);
            $validation = self::validate_image_upload($file, __('Event flyer', 'artpulse-management'));
            if ($validation) {
                $errors[] = $validation;
            } else {
                $attachment_id = media_handle_upload('event_flyer', $post_id);
                if (is_wp_error($attachment_id)) {
                    $errors[] = $attachment_id->get_error_message();
                } else {
                    set_post_thumbnail($post_id, $attachment_id);
                }
            }
        }

        // Admin notification
        $admin_email = get_option('admin_email');
        $subject = 'New Event Submission on ArtPulse';
        $message = sprintf(
            "A new event was submitted:\n\nTitle: %s\n\nBy User ID: %d\n\nEdit: %s",
            $title,
            $user_id,
            admin_url("post.php?post={$post_id}&action=edit")
        );
        wp_mail($admin_email, $subject, $message);

        // User confirmation
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        if ($user_email) {
            $user_subject = __('Thanks for submitting your event', 'artpulse-management');
            $user_message = sprintf(
                /* translators: %s event title. */
                __('Hi %1$s,%2$s%3$s', 'artpulse-management'),
                $current_user->display_name ?: $current_user->user_login,
                "\n\n",
                ('pending' === $status
                    ? sprintf(
                        /* translators: %s event title. */
                        __('Thanks for submitting your event "%s". Our team will review it shortly.', 'artpulse-management'),
                        $title
                    )
                    : sprintf(
                        /* translators: %s event title. */
                        __('Thanks for submitting your event "%s". It is now live on ArtPulse.', 'artpulse-management'),
                        $title
                    )
                )
            );
            wp_mail($user_email, $user_subject, $user_message);
        }

        if (!empty($errors)) {
            self::remember_errors($user_id, $errors);
        } else {
            self::remember_errors($user_id, []);
        }

        if ($should_redirect) {
            $redirect = get_permalink($post_id);
            if ($redirect) {
                $redirect = add_query_arg('event_submitted', '1', $redirect);
            } else {
                $redirect = wp_get_referer();
                if (!$redirect) {
                    $redirect = add_query_arg('event_submitted', '1', home_url());
                }

                $redirect = add_query_arg('event_submitted', '1', $redirect);
            }
            wp_safe_redirect($redirect);
            exit;
        }

        return $post_id;
    }

    private static function maybe_handle_errors(array $errors, bool $should_redirect)
    {
        $errors = array_filter(array_map(static fn($message) => sanitize_text_field((string) $message), $errors));

        if ($should_redirect) {
            self::remember_errors(get_current_user_id(), $errors);
            $redirect = wp_get_referer() ?: home_url('/dashboard/');
            if (!empty($errors)) {
                $redirect = add_query_arg('event_error', '1', $redirect);
            }
            wp_safe_redirect($redirect);
            exit;
        }

        if (!empty($errors)) {
            return new WP_Error('ap_event_submission_failed', implode(' ', $errors));
        }

        return 0;
    }

    private static function get_user_org_id(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $owned = get_posts([
            'post_type'      => 'artpulse_org',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_ap_owner_user',
                    'value' => $user_id,
                ],
            ],
        ]);

        if (!empty($owned) && $owned[0] instanceof WP_Post) {
            return (int) $owned[0]->ID;
        }

        $authored = get_posts([
            'post_type'      => 'artpulse_org',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'author'         => $user_id,
        ]);

        if (!empty($authored) && $authored[0] instanceof WP_Post) {
            return (int) $authored[0]->ID;
        }

        return 0;
    }

    private static function get_user_artist_id(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $owned = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_ap_owner_user',
                    'value' => $user_id,
                ],
            ],
        ]);

        if (!empty($owned) && $owned[0] instanceof WP_Post) {
            return (int) $owned[0]->ID;
        }

        $authored = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'author'         => $user_id,
        ]);

        if (!empty($authored) && $authored[0] instanceof WP_Post) {
            return (int) $authored[0]->ID;
        }

        return 0;
    }

    private static function remember_errors(int $user_id, array $messages): void
    {
        $messages = array_filter(array_map(static fn($message) => sanitize_text_field((string) $message), $messages));

        $key = self::ERROR_TRANSIENT_PREFIX . $user_id;

        if (empty($messages)) {
            delete_transient($key);
            return;
        }

        set_transient($key, $messages, MINUTE_IN_SECONDS * 5);
    }

    private static function pull_errors(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $key = self::ERROR_TRANSIENT_PREFIX . $user_id;
        $messages = get_transient($key);

        if (!is_array($messages)) {
            return [];
        }

        delete_transient($key);

        return array_map('sanitize_text_field', $messages);
    }

    private static function ensure_media_dependencies(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $loaded = true;
    }

    private static function prepare_file_array(array $file): array
    {
        return [
            'name'     => isset($file['name']) ? (string) $file['name'] : '',
            'type'     => isset($file['type']) ? (string) $file['type'] : '',
            'tmp_name' => isset($file['tmp_name']) ? (string) $file['tmp_name'] : '',
            'error'    => isset($file['error']) ? (int) $file['error'] : 0,
            'size'     => isset($file['size']) ? (int) $file['size'] : 0,
        ];
    }

    private static function validate_image_upload(array $file, string $label): ?string
    {
        if (!empty($file['error']) && UPLOAD_ERR_OK !== $file['error']) {
            $message = match ((int) $file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('The file exceeds the maximum allowed size.', 'artpulse-management'),
                UPLOAD_ERR_PARTIAL                       => __('The upload was incomplete.', 'artpulse-management'),
                UPLOAD_ERR_NO_FILE                       => __('No file was uploaded.', 'artpulse-management'),
                default                                  => __('The file could not be uploaded.', 'artpulse-management'),
            };

            return sprintf('%s: %s', $label, $message);
        }

        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return sprintf(__('Unable to read the %s.', 'artpulse-management'), strtolower($label));
        }

        if ($file['size'] > self::MAX_IMAGE_BYTES) {
            return sprintf(
                /* translators: 1: Field label, 2: maximum size in MB. */
                __('%1$s must be smaller than %2$dMB.', 'artpulse-management'),
                $label,
                10
            );
        }

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $type = $check['type'] ?? '';
        if ('' === $type || !in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return sprintf(__('%s must be a JPG, PNG, or WebP image.', 'artpulse-management'), $label);
        }

        $size = @getimagesize($file['tmp_name']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if (!is_array($size) || count($size) < 2) {
            return sprintf(__('We could not determine the dimensions of the %s.', 'artpulse-management'), strtolower($label));
        }

        [$width, $height] = $size;
        if ($width < self::MIN_IMAGE_DIMENSION || $height < self::MIN_IMAGE_DIMENSION) {
            return sprintf(
                /* translators: 1: Field label, 2: minimum dimension. */
                __('%1$s must be at least %2$d×%2$d pixels.', 'artpulse-management'),
                $label,
                self::MIN_IMAGE_DIMENSION
            );
        }

        return null;
    }
}
