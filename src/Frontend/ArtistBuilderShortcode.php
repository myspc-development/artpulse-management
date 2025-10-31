<?php

namespace ArtPulse\Frontend;

use ArtPulse\Artists\ArtistDraftCreator;
use ArtPulse\Core\ProfileState;
use WP_Error;
use function add_shortcode;
use function current_user_can;
use function esc_html__;
use function esc_url;
use function get_current_user_id;
use function is_user_logged_in;
use function is_wp_error;
use function preg_replace;
use function sprintf;
use function strpos;
use function wp_unslash;
use function wp_validate_redirect;

/**
 * Shortcode wrapper for the unified artist profile builder.
 */
final class ArtistBuilderShortcode
{
    public static function register(): void
    {
        add_shortcode('ap_artist_builder', [self::class, 'render']);
    }

    public static function render(): string
    {
        self::maybe_autocreate_profile();

        $output = BaseProfileBuilder::render('artist');

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

        if (!current_user_can('edit_artpulse_artist')) {
            return;
        }

        $state = ProfileState::for_user('artist', $user_id);
        if (!empty($state['post_id'])) {
            return;
        }

        $result = ArtistDraftCreator::create_for_user($user_id);
        if ($result instanceof WP_Error || is_wp_error($result)) {
            return;
        }

        ProfileState::purge_by_post_id((int) $result);
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

        $pattern      = '#(<div class="ap-profile-builder__actions">)(.*?)(</div>)#s';
        $replacement  = '$1$2' . $cta . '$3';
        $updated      = preg_replace($pattern, $replacement, $markup, 1, $count);

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
