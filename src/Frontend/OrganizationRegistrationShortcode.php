<?php

namespace ArtPulse\Frontend;

use ArtPulse\Community\NotificationManager;
use ArtPulse\Rest\SubmissionRestController;
use WP_REST_Request;

/**
 * Shortcode controller that exposes a community-focused organization registration form.
 */
class OrganizationRegistrationShortcode
{
    /**
     * Collected notices to surface to the shortcode renderer.
     *
     * @var array<int, array{type: string, message: string}>
     */
    protected static array $notices = [];

    /**
     * Stores the ID of the last organization that was created during the current request.
     */
    protected static ?int $last_created_org_id = null;

    /**
     * Boot the shortcode.
     */
    public static function register(): void
    {
        add_shortcode('ap_register_organization', [self::class, 'render']);
        add_action('init', [self::class, 'maybe_handle_form']);
    }

    /**
     * Output the organization registration experience.
     */
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You need to be logged in to register an organization.', 'artpulse-management') . '</p>';
        }

        $output  = SubmissionForms::render_form([
            'post_type'     => 'artpulse_org',
            'include_nonce' => true,
            'nonce_action'  => 'ap_register_org',
            'nonce_field'   => 'ap_register_org_nonce',
            'submit_label'  => __('Register Organization', 'artpulse-management'),
            'submit_name'   => 'ap_register_org',
            'extra_classes' => 'ap-organization-registration-form',
            'notices'       => self::$notices,
        ]);

        if (self::$last_created_org_id) {
            $output .= self::render_engagement_prompts();
        }

        return $output;
    }

    /**
     * Process form submission.
     */
    public static function maybe_handle_form(): void
    {
        if (!is_user_logged_in() || !isset($_POST['ap_register_org'])) {
            return;
        }

        if (!isset($_POST['ap_register_org_nonce']) || !wp_verify_nonce($_POST['ap_register_org_nonce'], 'ap_register_org')) {
            self::add_notice(__('Security check failed. Please refresh and try again.', 'artpulse-management'));
            return;
        }

        $title   = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        $website = isset($_POST['org_website']) ? esc_url_raw(wp_unslash($_POST['org_website'])) : '';
        $email   = isset($_POST['org_email']) ? sanitize_email(wp_unslash($_POST['org_email'])) : '';

        if ('' === $title) {
            self::add_notice(__('Please provide an organization name.', 'artpulse-management'));
            return;
        }

        if (!empty($email) && !is_email($email)) {
            self::add_notice(__('Please provide a valid contact email or leave the field blank.', 'artpulse-management'));
            return;
        }

        $payload = array_filter([
            'post_type'   => 'artpulse_org',
            'title'       => $title,
            'content'     => $content,
            'org_website' => $website,
            'org_email'   => $email,
        ], static fn($value) => '' !== $value && null !== $value);

        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params($payload);

        $response = SubmissionRestController::handle_submission($request);

        if (is_wp_error($response)) {
            self::add_notice($response->get_error_message());
            return;
        }

        $data   = $response->get_data();
        $org_id = isset($data['id']) ? (int) $data['id'] : 0;

        if ($org_id <= 0) {
            self::add_notice(__('We could not determine the new organization ID. Please contact support.', 'artpulse-management'));
            return;
        }

        self::$last_created_org_id = $org_id;

        $user_id = get_current_user_id();
        update_user_meta($user_id, 'ap_organization_id', $org_id);

        self::notify_admins_of_submission($org_id, $user_id, $title);
        self::notify_creator_for_follow_prompt($org_id, $user_id);

        self::$notices = [];
        self::add_notice(__('Organization submitted successfully! Explore the community actions below to get started.', 'artpulse-management'), 'success');
    }

    /**
     * Persist a notice for the form renderer.
     */
    protected static function add_notice(string $message, string $type = 'error'): void
    {
        self::$notices[] = [
            'type'    => sanitize_key($type),
            'message' => wp_strip_all_tags($message),
        ];
    }

    /**
     * Trigger notifications for administrators after a submission.
     */
    protected static function notify_admins_of_submission(int $org_id, int $creator_id, string $title): void
    {
        $admins = get_users([
            'role'   => 'administrator',
            'fields' => 'ID',
        ]);

        if (empty($admins)) {
            return;
        }

        $current_user  = wp_get_current_user();
        $creator_label = $current_user && $current_user->exists()
            ? $current_user->display_name
            : __('an ArtPulse member', 'artpulse-management');

        $message = sprintf(
            /* translators: 1: Organization name, 2: submitting user name */
            __('New organization "%1$s" was submitted by %2$s and is awaiting review.', 'artpulse-management'),
            $title,
            $creator_label
        );

        foreach ($admins as $admin_id) {
            NotificationManager::add((int) $admin_id, 'org_submission', $org_id, $creator_id, $message);
        }
    }

    /**
     * Invite the creator to take community actions via notifications.
     */
    protected static function notify_creator_for_follow_prompt(int $org_id, int $creator_id): void
    {
        $prompt = __('Follow featured artists and favorite upcoming events to personalize your organization dashboard.', 'artpulse-management');
        NotificationManager::add($creator_id, 'org_follow_prompt', $org_id, null, $prompt);
    }

    /**
     * Render onboarding prompts that reuse the social controls from the item partial.
     */
    protected static function render_engagement_prompts(): string
    {
        $post_types = ['artpulse_artist', 'artpulse_event'];
        $items      = get_posts([
            'post_type'      => $post_types,
            'posts_per_page' => 4,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        if (!$items) {
            return '';
        }

        $template = plugin_dir_path(__FILE__) . '../../templates/partials/content-artpulse-item.php';
        if (!file_exists($template)) {
            return '';
        }

        ob_start();
        ?>
        <section class="ap-onboarding-prompts" aria-labelledby="ap-onboarding-prompts-heading">
            <h2 id="ap-onboarding-prompts-heading"><?php esc_html_e('Connect with your community', 'artpulse-management'); ?></h2>
            <p class="ap-onboarding-prompts__intro"><?php esc_html_e('Follow and favorite nearby creators and events to immediately plug your organization into ArtPulse.', 'artpulse-management'); ?></p>
            <div class="ap-onboarding-prompts__items">
                <?php
                global $post;
                $original_post = $post;

                foreach ($items as $post) {
                    setup_postdata($post);
                    include $template;
                }

                $post = $original_post;
                wp_reset_postdata();
                ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
