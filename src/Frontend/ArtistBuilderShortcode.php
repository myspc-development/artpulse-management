<?php

namespace ArtPulse\Frontend;

use ArtPulse\Artists\ArtistDraftCreator;
use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\RateLimitHeaders;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use WP_Error;

/**
 * Shortcode for managing artist profiles via the front-end builder.
 */
final class ArtistBuilderShortcode
{
    public static function register(): void
    {
        add_shortcode('ap_artist_builder', [self::class, 'render']);
    }

    public static function render(): string
    {
        if (!get_option('ap_enable_artist_builder', true)) {
            status_header(404);

            if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
                wp_send_json([
                    'code' => 'builder_disabled',
                ], 404);
            }

            return '';
        }

        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            self::enforce_post_security();
        }

        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }

        $user_id = get_current_user_id();

        $auto_create_error = self::maybe_handle_auto_create($user_id);

        $artist_ids = self::owned_artists($user_id);

        if (empty($artist_ids)) {
            return self::render_empty_state($auto_create_error);
        }

        $mobile = isset($_GET['view']) && 'mobile' === sanitize_text_field(wp_unslash($_GET['view']));

        $profiles = [];
        foreach ($artist_ids as $artist_id) {
            $post = get_post($artist_id);
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $status       = $post->post_status;
            $status_label = __('Draft', 'artpulse-management');
            $badge_variant = 'info';
            $progress     = 45;

            if ('publish' === $status) {
                $status_label = __('Published', 'artpulse-management');
                $badge_variant = 'success';
                $progress = 100;
            } elseif ('pending' === $status) {
                $status_label = __('Pending review', 'artpulse-management');
                $badge_variant = 'warning';
                $progress = 80;
            } elseif ('future' === $status) {
                $status_label = __('Scheduled', 'artpulse-management');
                $badge_variant = 'info';
                $progress = 95;
            }

            $builder_url = add_query_arg(['artist_id' => $artist_id], home_url('/artist-builder/'));
            $dashboard_url = add_query_arg('role', 'artist', home_url('/dashboard/'));
            $public_url   = get_permalink($artist_id) ?: '';
            $submit_event_url = '';

            if (in_array($status, ['publish', 'future'], true)) {
                $submit_event_url = add_query_arg('artist_id', $artist_id, home_url('/submit-event/'));
            }

            $profiles[] = [
                'id'               => $artist_id,
                'title'            => get_the_title($artist_id),
                'status'           => $status,
                'status_label'     => $status_label,
                'badge_variant'    => $badge_variant,
                'progress_percent' => $progress,
                'thumbnail'        => get_the_post_thumbnail_url($artist_id, 'medium') ?: '',
                'excerpt'          => wp_trim_words($post->post_content, 35),
                'actions'          => [
                    'builder'      => $builder_url,
                    'dashboard'    => $dashboard_url,
                    'public'       => $public_url,
                    'submit_event' => $submit_event_url,
                ],
            ];
        }

        if (empty($profiles)) {
            return self::render_empty_state('');
        }

        ob_start();

        wp_enqueue_style('ap-artist-builder', plugins_url('assets/css/ap-artist-builder.css', ARTPULSE_PLUGIN_FILE), [], ARTPULSE_VERSION);

        $builder_artist_ids = $artist_ids;
        $builder_profiles   = $profiles;
        $is_mobile_view     = $mobile;
        $builder_summary    = [
            'total'     => count($profiles),
            'published' => count(array_filter($profiles, static fn(array $profile): bool => in_array($profile['status'], ['publish', 'future'], true))),
        ];

        include ARTPULSE_PLUGIN_DIR . 'templates/artist-builder/wrapper.php';

        return (string) ob_get_clean();
    }

    private static function maybe_handle_auto_create(int $user_id): string
    {
        if (!self::should_auto_create()) {
            return '';
        }

        $result = ArtistDraftCreator::create_for_user($user_id);
        if ($result instanceof WP_Error) {
            return $result->get_error_message();
        }

        self::redirect_to_builder((int) $result);

        return '';
    }

    private static function should_auto_create(): bool
    {
        if (!isset($_GET['autocreate'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }

        $value = strtolower(sanitize_text_field(wp_unslash($_GET['autocreate']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        return in_array($value, ['1', 'true'], true);
    }

    private static function redirect_to_builder(int $post_id): void
    {
        $target = add_query_arg('post_id', $post_id, self::builder_base_url());

        wp_safe_redirect($target);
        exit;
    }

    private static function builder_base_url(): string
    {
        return add_query_arg(['ap_builder' => 'artist'], home_url('/artist-builder/'));
    }

    private static function render_empty_state(string $error_message): string
    {
        wp_enqueue_script(
            'ap-artist-builder-empty-state',
            plugins_url('assets/js/ap-artist-builder-empty-state.js', ARTPULSE_PLUGIN_FILE),
            [],
            ARTPULSE_VERSION,
            true
        );

        wp_localize_script('ap-artist-builder-empty-state', 'APArtistBuilderEmpty', [
            'endpoint'      => esc_url_raw(rest_url('artpulse/v1/artist/create')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'builderUrl'    => esc_url_raw(self::builder_base_url()),
            'creatingLabel' => esc_html__('Creating your draft…', 'artpulse-management'),
            'successLabel'  => esc_html__('Draft ready! Redirecting…', 'artpulse-management'),
            'errorMessage'  => esc_html__('We could not create your artist profile. Please try again.', 'artpulse-management'),
        ]);

        $error_attributes = '';
        if ('' === $error_message) {
            $error_attributes = ' hidden';
        }

        ob_start();
        ?>
        <section class="ap-artist-builder-empty" aria-labelledby="ap-artist-builder-empty-heading">
            <h2 id="ap-artist-builder-empty-heading"><?php esc_html_e('Let’s set up your Artist profile', 'artpulse-management'); ?></h2>
            <p><?php esc_html_e('We’ll create a starter draft you can finish later.', 'artpulse-management'); ?></p>
            <div class="ap-artist-builder-empty__actions">
                <button type="button" class="ap-dashboard-button ap-dashboard-button--primary" data-ap-artist-create>
                    <?php esc_html_e('Create my artist profile', 'artpulse-management'); ?>
                </button>
                <span class="ap-artist-builder-empty__status" data-ap-artist-create-status role="status" aria-live="polite"></span>
            </div>
            <p class="ap-artist-builder-empty__error" data-ap-artist-create-error role="alert"<?php echo $error_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo esc_html($error_message); ?>
            </p>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function enforce_post_security(): void
    {
        if (!is_user_logged_in()) {
            self::respond_with_error(
                'auth_required',
                __('You must be logged in to update this artist.', 'artpulse-management'),
                401
            );
        }

        $nonce_valid = isset($_POST['_ap_nonce'])
            && check_admin_referer('ap_portfolio_update', '_ap_nonce', false);

        if (!$nonce_valid) {
            self::respond_with_error(
                'invalid_nonce',
                __('Security check failed.', 'artpulse-management'),
                403
            );
        }

        $user_id = get_current_user_id();

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id && isset($_POST['artist_id'])) {
            $post_id = absint($_POST['artist_id']);
        }

        if (!$post_id || !PortfolioAccess::is_owner($user_id, $post_id)) {
            self::respond_with_error(
                'forbidden',
                __('You do not have permission to update this artist.', 'artpulse-management'),
                403
            );
        }

        $rate_error = FormRateLimiter::enforce($user_id, 'builder_write', 30, 60);
        if ($rate_error instanceof WP_Error) {
            self::bail_rate_limited($rate_error);
        }
    }

    private static function bail_rate_limited(WP_Error $error): void
    {
        $data   = (array) $error->get_error_data();
        $status = isset($data['status']) ? (int) $data['status'] : 429;

        if (429 !== $status) {
            self::respond_with_error($error->get_error_code(), $error->get_error_message(), $status);
        }

        $retry_after = isset($data['retry_after']) ? max(1, (int) $data['retry_after']) : 60;
        $limit       = isset($data['limit']) ? max(1, (int) $data['limit']) : 30;
        $reset       = isset($data['reset']) ? (int) $data['reset'] : time() + $retry_after;

        $headers = RateLimitHeaders::emit($limit, 0, $retry_after, $reset);

        AuditLogger::info('rate_limit.hit', [
            'user_id'     => get_current_user_id(),
            'route'       => sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '')),
            'context'     => 'artist_builder',
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
        if (null !== $retry_after) {
            $details['retry_after'] = max(0, $retry_after);
        }

        if ('invalid_nonce' === $code && !isset($details['hint'])) {
            $details['hint'] = 'refresh_nonce_and_retry';
        }

        $payload = [
            'code'    => $code,
            'message' => $message,
            'details' => $details,
        ];

        if (isset($details['nonce']) && is_string($details['nonce'])) {
            header('X-ArtPulse-Nonce: ' . $details['nonce']);
        }

        wp_send_json($payload, $status);
    }

    private static function owned_artists(int $user_id): array
    {
        return PortfolioAccess::get_owned_portfolio_ids($user_id, 'artpulse_artist');
    }
}
