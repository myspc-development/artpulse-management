<?php

namespace ArtPulse\Frontend;

use function add_shortcode;

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
        return BaseProfileBuilder::render('artist');
    }
}
