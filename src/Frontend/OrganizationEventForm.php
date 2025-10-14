<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\Capabilities;
use ArtPulse\Core\RateLimitHeaders;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use WP_Error;
use WP_Post;

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

        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            self::handle_submission();
        }

        $nonce_value = wp_create_nonce('ap_event_submit');
        self::bind_context_to_nonce($user_id, $nonce_value, $org_id, $artist_id);

        ob_start();
        ?>
        <div class="ap-form-messages" role="status" aria-live="polite"></div>
        <form method="post" enctype="multipart/form-data" class="ap-event-form">
            <input type="hidden" name="_ap_nonce" value="<?php echo esc_attr($nonce_value); ?>" />
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
        $context_org_id = isset($_GET['org_id']) ? absint(wp_unslash($_GET['org_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $context_artist_id = isset($_GET['artist_id']) ? absint(wp_unslash($_GET['artist_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $org_id    = 0;
        $artist_id = 0;

        $errors = [];

        if ($user_id <= 0) {
            $errors[] = __('You do not have permission to submit this event.', 'artpulse-management');
            return self::maybe_handle_errors($errors, $should_redirect);
        }

        $owner_id     = $user_id;
        $org_id_param = $context_org_id;
        $artist_param = $context_artist_id;
        $owned_org    = $org_id_param && PortfolioAccess::is_owner($owner_id, $org_id_param);
        $owned_artist = $artist_param && PortfolioAccess::is_owner($owner_id, $artist_param);
        if (($org_id_param && !$owned_org) || ($artist_param && !$owned_artist)) {
            wp_die(esc_html__('Forbidden.', 'artpulse-management'), 403);
        }

        $nonce_value = isset($_POST['_ap_nonce']) ? sanitize_text_field(wp_unslash($_POST['_ap_nonce'])) : '';
        $nonce_valid = $nonce_value !== '' && check_admin_referer('ap_event_submit', '_ap_nonce', false);
        if (!$nonce_valid) {
            self::respond_with_error(
                'invalid_nonce',
                __('Security check failed.', 'artpulse-management'),
                403,

            );
        }

        $rate_error = FormRateLimiter::enforce($user_id, 'event_submit', 10, 60);
        if ($rate_error instanceof WP_Error) {
            self::bail_rate_limited($rate_error);
        }

        $dedupe_key = null;

        $bound_context = self::get_bound_context($user_id, $nonce_value);

        if (null !== $bound_context) {
            $org_id    = (int) $bound_context['org_id'];
            $artist_id = (int) $bound_context['artist_id'];
        } else {
            if ($context_org_id && PortfolioAccess::is_owner($user_id, $context_org_id)) {
                $org_id = $context_org_id;
            } else {
                $org_id = self::get_user_org_id($user_id);
            }

            if ($context_artist_id && PortfolioAccess::is_owner($user_id, $context_artist_id)) {
                $artist_id = $context_artist_id;
            } else {
                $artist_id = self::get_user_artist_id($user_id);
            }
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

        $raw_start_ts = isset($_POST['start_ts']) ? wp_unslash($_POST['start_ts']) : '';
        $start_ts     = 0;

        if (is_numeric($raw_start_ts)) {
            $start_ts = (int) $raw_start_ts;
        } elseif (is_string($raw_start_ts) && '' !== $raw_start_ts) {
            $parsed = strtotime($raw_start_ts);
            if (false !== $parsed) {
                $start_ts = (int) $parsed;
            }
        }

        if (0 === $start_ts) {
            $parsed = strtotime($date);
            if (false !== $parsed) {
                $start_ts = (int) $parsed;
            }
        }

        $dedupe_key = 'ap_event_submit_' . md5(sanitize_title($title) . '|' . $start_ts . '|' . $owner_id);

        if (false !== get_transient($dedupe_key)) {
            self::respond_with_error(
                'duplicate_event',
                __('A similar event was just submitted. Please wait a moment before trying again.', 'artpulse-management'),
                409,
                [
                    'retry_after' => MINUTE_IN_SECONDS,
                ]
            );
        }

        set_transient($dedupe_key, time(), MINUTE_IN_SECONDS);

        $require_review = (bool) get_option('ap_require_event_review', true);
        $status         = $require_review ? 'pending' : 'publish';
        $moderation_state      = 'publish' === $status ? 'approved' : 'pending';
        $moderation_changed_at = current_time('timestamp', true);

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $description,
            'post_type'    => 'artpulse_event',
            'post_status'  => $status,
            'post_author'  => $user_id,
        ], true);

        if (is_wp_error($post_id)) {
            if ($dedupe_key) {
                delete_transient($dedupe_key);
            }
            $errors[] = $post_id->get_error_message();
            return self::maybe_handle_errors($errors, $should_redirect);
        }

        update_post_meta($post_id, '_ap_event_date', $date);
        update_post_meta($post_id, '_ap_event_location', $location);
        update_post_meta($post_id, '_ap_event_organization', $org_id);
        update_post_meta($post_id, '_ap_org_id', $org_id);
        update_post_meta($post_id, '_ap_artist_id', $artist_id);
        update_post_meta($post_id, '_ap_moderation_state', $moderation_state);
        update_post_meta($post_id, '_ap_moderation_changed_at', $moderation_changed_at);


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

        AuditLogger::info('event.submit', [
            'event_id' => $post_id,
            'user_id'  => $user_id,
            'owner_id' => $user_id,
            'source'   => 'web',
            'status'   => $status,
            'state'    => $moderation_state,
            'org_id'   => $org_id,
            'artist_id' => $artist_id,
            'reason'   => '',
            'changed_at' => $moderation_changed_at,
        ]);

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

    private static function bind_context_to_nonce(int $user_id, string $nonce, int $org_id, int $artist_id): void
    {
        if ($user_id <= 0 || '' === $nonce) {
            return;
        }

        $context = [
            'org_id'    => max(0, $org_id),
            'artist_id' => max(0, $artist_id),
            'bound_at'  => time(),
        ];

        set_transient(self::get_nonce_context_key($user_id, $nonce), $context, HOUR_IN_SECONDS);
    }

    private static function get_bound_context(int $user_id, string $nonce): ?array
    {
        if ($user_id <= 0 || '' === $nonce) {
            return null;
        }

        $key = self::get_nonce_context_key($user_id, $nonce);
        $context = get_transient($key);

        if (!is_array($context)) {
            return null;
        }

        $org_id = isset($context['org_id']) ? (int) $context['org_id'] : 0;
        $artist_id = isset($context['artist_id']) ? (int) $context['artist_id'] : 0;

        if ($org_id && !PortfolioAccess::is_owner($user_id, $org_id)) {
            $org_id = 0;
        }

        if ($artist_id && !PortfolioAccess::is_owner($user_id, $artist_id)) {
            $artist_id = 0;
        }

        return [
            'org_id'    => $org_id,
            'artist_id' => $artist_id,
        ];
    }

    private static function get_nonce_context_key(int $user_id, string $nonce): string
    {
        return 'ap_event_form_ctx_' . md5($user_id . '|' . $nonce);
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

    private static function bail_rate_limited(WP_Error $error): void
    {
        $data        = (array) $error->get_error_data();
        $retry_after = isset($data['retry_after']) ? max(1, (int) $data['retry_after']) : 30;
        $limit       = isset($data['limit']) ? max(1, (int) $data['limit']) : 10;
        $reset       = isset($data['reset']) ? (int) $data['reset'] : time() + $retry_after;

        $headers = $data['headers'] ?? RateLimitHeaders::build($limit, 0, $reset, $retry_after);
        RateLimitHeaders::emit($headers);

        AuditLogger::info('rate_limit.hit', [
            'user_id'     => get_current_user_id(),
            'route'       => sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '')),
            'context'     => 'event_form',
            'retry_after' => $retry_after,
            'limit'       => $limit,
        ]);

        self::respond_with_error(
            $error->get_error_code(),
            $error->get_error_message(),
            429,
            [
                'limit'       => $limit,
                'retry_after' => $retry_after,
                'reset'       => $reset,
            ],
            $retry_after
        );
    }

    private static function respond_with_error(
        string $code,
        string $message,
        int $status,
        array $details = [],
        ?int $retry_after = null
    ): void {

        $payload = [
            'code'    => $code,
            'message' => $message,
            'details' => $details,
        ];


        if (isset($details['nonce']) && is_string($details['nonce'])) {
            header('X-ArtPulse-Nonce: ' . $details['nonce']);
        }

        if (null !== $retry_after) {
            header('Retry-After: ' . max(1, (int) $retry_after));
        }

        wp_send_json($payload, $status);
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
