<?php

namespace ArtPulse\Frontend;

use ArtPulse\Rest\SubmissionRestController;
use WP_REST_Request;

class EventSubmissionShortcode {

    /**
     * Stores fallback notices when WooCommerce helpers are unavailable.
     *
     * @var array<int, array{type: string, message: string}>
     */
    protected static $fallback_notices = [];

    public static function register() {
        add_shortcode('ap_submit_event', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_scripts']); // Enqueue scripts and styles
        add_action('init', [self::class, 'maybe_handle_form']); // Handle form submission
    }

    public static function enqueue_scripts() {
        // Enqueue your styles and scripts here
        wp_enqueue_style('ap-event-form-styles', get_template_directory_uri() . '/assets/css/event-form.css'); // Replace with your CSS file
    }

    public static function render() {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to submit an event.</p>';
        }

        ob_start();

        echo SubmissionForms::render_form([
            'post_type'     => 'artpulse_event',
            'include_nonce' => true,
            'nonce_action'  => 'ap_submit_event',
            'nonce_field'   => 'ap_event_nonce',
            'submit_label'  => __('Submit Event', 'artpulse'),
            'submit_name'   => 'ap_submit_event',
            'extra_classes' => 'ap-event-form',
            'notices'       => self::get_fallback_notices(),
        ]);

        return ob_get_clean();
    }

    public static function maybe_handle_form() {
        if (!is_user_logged_in() || !isset($_POST['ap_submit_event'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['ap_event_nonce']) || !wp_verify_nonce($_POST['ap_event_nonce'], 'ap_submit_event')) {
            wp_die('Security check failed.'); // Or redirect with an error message
            return;
        }

        $payload = [
            'post_type'          => 'artpulse_event',
            'title'              => sanitize_text_field($_POST['title'] ?? ''),
            'content'            => wp_kses_post($_POST['content'] ?? ''),
            'event_date'         => sanitize_text_field($_POST['event_date'] ?? ''),
            'event_location'     => sanitize_text_field($_POST['event_location'] ?? ''),
            'event_organization' => absint($_POST['event_organization'] ?? 0),
        ];

        if (empty($payload['title'])) {
            self::add_notice(__('Please enter an event title.', 'artpulse'), 'error');
            return;
        }

        if (empty($payload['content'])) {
            self::add_notice(__('Please enter an event description.', 'artpulse'), 'error');
            return;
        }

        if (empty($payload['event_date'])) {
            self::add_notice(__('Please enter an event date.', 'artpulse'), 'error');
            return;
        }

        if (empty($payload['event_location'])) {
            self::add_notice(__('Please enter an event location.', 'artpulse'), 'error');
            return;
        }

        if (!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $payload['event_date'])) {
            self::add_notice(__('Please enter a valid date in YYYY-MM-DD format.', 'artpulse'), 'error');
            return;
        }

        if ($payload['event_organization'] <= 0) {
            self::add_notice(__('Please select an organization.', 'artpulse'), 'error');
            return;
        }

        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params(array_filter($payload, static fn($value) => $value !== '' && $value !== null));

        $response = SubmissionRestController::handle_submission($request);

        if (is_wp_error($response)) {
            self::add_notice($response->get_error_message(), 'error');
            return;
        }

        self::add_notice(__('Event submitted successfully! It is awaiting review.', 'artpulse'), 'success');

        if (function_exists('wc_add_notice')) {
            wp_safe_redirect(home_url('/thank-you-page'));
            exit;
        }
    }

    /**
     * Adds a notice using WooCommerce if available, otherwise falls back to an internal system.
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    protected static function add_notice($message, $type = 'error') {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, $type);
            return;
        }

        self::$fallback_notices[] = [
            'type'    => function_exists('sanitize_key') ? sanitize_key($type) : $type,
            'message' => function_exists('wp_strip_all_tags') ? wp_strip_all_tags($message) : $message,
        ];
    }

    /**
     * Retrieves notices stored in the fallback system.
     *
     * @return array<int, array{type: string, message: string}>
     */
    protected static function get_fallback_notices() {
        return self::$fallback_notices;
    }
}