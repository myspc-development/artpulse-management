<?php

namespace ArtPulse\Core;

use function add_action;
use function add_query_arg;
use function apply_filters;
use function current_user_can;
use function esc_html__;
use function esc_html;
use function esc_url;
use function esc_url_raw;
use function admin_url;
use function get_option;
use function get_permalink;
use function has_action;
use function home_url;
use function sanitize_email;
use function sprintf;
use function wp_http_validate_url;

const OPTION_NAME = 'artpulse_pages';

/**
 * Returns the supported settings keys for configurable pages.
 *
 * @return array<string, string> Map of option keys to human friendly labels.
 */
function get_page_options(): array
{
    return [
        'dashboard_page_id'      => esc_html__('Member Dashboard', 'artpulse-management'),
        'artist_builder_page_id' => esc_html__('Artist Builder', 'artpulse-management'),
        'org_builder_page_id'    => esc_html__('Organization Builder', 'artpulse-management'),
        'contact_page_id'        => esc_html__('Support Contact', 'artpulse-management'),
    ];
}

/**
 * Retrieve a configured page ID.
 */
function get_page_id(string $key): ?int
{
    $options = get_option(OPTION_NAME, []);

    $page_id = null;

    if (is_array($options) && isset($options[$key])) {
        $candidate = (int) $options[$key];
        if ($candidate > 0) {
            $page_id = $candidate;
        }
    }

    /** @var int|null $filtered */
    $filtered = apply_filters("artpulse/page_id/{$key}", $page_id);

    if (is_numeric($filtered)) {
        $filtered = (int) $filtered;
    } else {
        $filtered = null;
    }

    return $filtered && $filtered > 0 ? $filtered : null;
}

/**
 * Retrieve the permalink for a configured page.
 */
function get_page_url(string $key): ?string
{
    $page_id = get_page_id($key);

    if (!$page_id) {
        /** @var string|null $url */
        $url = apply_filters("artpulse/page_url/{$key}", null, null);
        return is_string($url) && $url !== '' ? $url : null;
    }

    $permalink = get_permalink($page_id);

    if (!$permalink) {
        return null;
    }

    /** @var string|null $url */
    $url = apply_filters("artpulse/page_url/{$key}", $permalink, $page_id);

    return is_string($url) && $url !== '' ? $url : null;
}

/**
 * Provide a generic support URL when a configured page is missing.
 */
function get_support_url(): string
{
    $contact_url = get_page_url('contact_page_id');

    if ($contact_url) {
        return esc_url_raw($contact_url);
    }

    $admin_email = sanitize_email((string) get_option('admin_email'));
    $default     = $admin_email !== ''
        ? sprintf('mailto:%s', $admin_email)
        : home_url('/');

    /** @var string $support */
    $support = apply_filters('artpulse/support_contact_url', $default);

    return esc_url_raw($support);
}

/**
 * URL to the ArtPulse settings screen.
 */
function get_settings_page_url(): string
{
    return esc_url(admin_url('options-general.php?page=artpulse-settings'));
}

/**
 * Provide a fallback URL when a configured page is missing.
 */
function get_missing_page_fallback(string $key): string
{
    if (current_user_can('manage_options')) {
        add_missing_page_notice($key);
        return get_settings_page_url();
    }

    return get_support_url();
}

/**
 * Safely append query arguments when the URL is valid.
 *
 * @param array<string, string> $args
 */
function add_query_args(string $url, array $args): string
{
    if (!wp_http_validate_url($url)) {
        return $url;
    }

    return add_query_arg($args, $url);
}

/**
 * Ensure administrators are informed when a page mapping is missing.
 */
function add_missing_page_notice(string $key): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $store =& get_missing_page_notice_store();

    if (isset($store[$key])) {
        return;
    }

    $store[$key] = true;

    if (!has_action('admin_notices', __NAMESPACE__ . '\render_admin_missing_page_notices')) {
        add_action('admin_notices', __NAMESPACE__ . '\render_admin_missing_page_notices');
    }

    if (!has_action('wp_footer', __NAMESPACE__ . '\render_frontend_missing_page_notices')) {
        add_action('wp_footer', __NAMESPACE__ . '\render_frontend_missing_page_notices');
    }
}

/**
 * Internal store for missing page notices.
 *
 * @return array<string, bool>
 */
function &get_missing_page_notice_store(): array
{
    static $store = [];

    return $store;
}

/**
 * Render admin notices for missing page assignments.
 */
function render_admin_missing_page_notices(): void
{
    $messages = build_missing_page_messages();

    if (empty($messages)) {
        return;
    }

    foreach ($messages as $message) {
        printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html($message));
    }
}

/**
 * Render front-end notices for missing page assignments.
 */
function render_frontend_missing_page_notices(): void
{
    $messages = build_missing_page_messages();

    if (empty($messages)) {
        return;
    }

    echo '<div class="artpulse-missing-page-notices" style="margin:1em auto;max-width:960px;">';
    foreach ($messages as $message) {
        printf(
            '<div class="artpulse-missing-page-notice" style="background:#fff3cd;border:1px solid #ffeeba;padding:12px 16px;margin-bottom:12px;border-radius:4px;color:#856404;">%s</div>',
            esc_html($message)
        );
    }
    echo '</div>';
}

/**
 * Build human friendly messages for missing page assignments.
 *
 * @return string[]
 */
function build_missing_page_messages(): array
{
    $store =& get_missing_page_notice_store();
    $keys  = array_keys($store);
    $store = [];

    if (empty($keys)) {
        return [];
    }

    $options       = get_page_options();
    $settings_hint = esc_html__('Settings → ArtPulse', 'artpulse-management');

    $messages = [];

    foreach ($keys as $key) {
        $label = $options[$key] ?? $key;
        $messages[] = sprintf(
            /* translators: 1: Page label. 2: Settings hint. */
            esc_html__('The %1$s page is not configured. Update it in %2$s.', 'artpulse-management'),
            $label,
            $settings_hint
        );
    }

    return $messages;
}

