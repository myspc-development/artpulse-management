<?php

namespace ArtPulse\Frontend;

use function add_shortcode;

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
        return BaseProfileBuilder::render('org');
    }
}
