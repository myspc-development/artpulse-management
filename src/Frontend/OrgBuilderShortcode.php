<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\Capabilities;
use ArtPulse\Core\ProfileState;
use ArtPulse\Core\RoleUpgradeManager;
use WP_Error;
use WP_User;
use function add_shortcode;
use function current_user_can;
use function esc_html__;
use function esc_url;
use function __;
use function get_current_user_id;
use function get_user_by;
use function is_user_logged_in;
use function is_wp_error;
use function preg_replace;
use function sprintf;
use function strpos;
use function user_can;
use function wp_insert_post;
use function wp_unslash;
use function wp_validate_redirect;

/**
 * Shortcode wrapper for the unified organization profile builder.
 */
final class OrgBuilderShortcode
{
    public static function register(): void
    {
        add_shortcode('ap_org_builder', [self::class, 'render']);
    }

    public static function render(): string
    {
        self::maybe_autocreate_profile();

        $output = BaseProfileBuilder::render('org');

        return self::maybe_append_redirect_cta($output);
    }

    private static function maybe_autocreate_profile(): void
    {
        if (!self::should_autocreate()) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        if (!current_user_can('edit_artpulse_org')) {
            return;
        }

        $state = ProfileState::for_user('org', $user_id);
        if (!empty($state['post_id'])) {
            return;
        }

        $result = self::create_org_draft($user_id);
        if ($result instanceof WP_Error || is_wp_error($result)) {
            return;
        }

        ProfileState::purge_by_post_id((int) $result);
    }

    /**
     * @return int|WP_Error
     */
    private static function create_org_draft(int $user_id)
    {
        if ($user_id <= 0) {
            return new WP_Error(
                'ap_org_autocreate_invalid_user',
                __('You must be logged in to create an organization profile.', 'artpulse-management')
            );
        }

        if (!user_can($user_id, Capabilities::CAP_MANAGE_PORTFOLIO)) {
            return new WP_Error(
                'ap_org_autocreate_forbidden',
                __('You do not have permission to create an organization profile.', 'artpulse-management')
            );
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return new WP_Error(
                'ap_org_autocreate_invalid_user',
                __('You must be logged in to create an organization profile.', 'artpulse-management')
            );
        }

        $display = trim((string) ($user->display_name ?: $user->user_login));
        $title   = '' === $display
            ? __('New organization profile', 'artpulse-management')
            : sprintf(
                __('Organization profile for %s', 'artpulse-management'),
                $display
            );

        $post_id = wp_insert_post([
            'post_type'    => 'artpulse_org',
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => '',
            'post_author'  => $user_id,
            'meta_input'   => [
                '_ap_owner_user' => $user_id,
            ],
        ], true);

        if ($post_id instanceof WP_Error || is_wp_error($post_id)) {
            return $post_id;
        }

        RoleUpgradeManager::attach_owner((int) $post_id, $user_id);

        return (int) $post_id;
    }

    private static function maybe_append_redirect_cta(string $markup): string
    {
        if ('' === $markup) {
            return $markup;
        }

        if (false !== strpos($markup, 'ap-profile-builder__back')) {
            return $markup;
        }

        $redirect = self::get_redirect_url();
        if ('' === $redirect) {
            return $markup;
        }

        $cta = sprintf(
            '<a class="button button-secondary ap-profile-builder__back" href="%s">%s</a>',
            esc_url($redirect),
            esc_html__('Back to dashboard', 'artpulse-management')
        );

        $pattern     = '#(<div class="ap-profile-builder__actions">)(.*?)(</div>)#s';
        $replacement = '$1$2' . $cta . '$3';
        $updated     = preg_replace($pattern, $replacement, $markup, 1, $count);

        if (null !== $updated && $count > 0) {
            return $updated;
        }

        return $markup . $cta;
    }

    private static function should_autocreate(): bool
    {
        if (!isset($_GET['autocreate'])) {
            return false;
        }

        $value = (string) wp_unslash((string) $_GET['autocreate']);

        return '1' === $value;
    }

    private static function get_redirect_url(): string
    {
        if (!isset($_GET['redirect'])) {
            return '';
        }

        $raw = (string) wp_unslash((string) $_GET['redirect']);

        $validated = wp_validate_redirect($raw, '');

        return $validated ? $validated : '';
    }
}
