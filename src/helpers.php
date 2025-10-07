<?php
/**
 * Helper functions for ArtPulse directories.
 */

use ArtPulse\Core\TitleTools;

if (!function_exists('ap_normalize_letter')) {
    /**
     * Normalize a title into its directory letter bucket.
     *
     * @param string $title  Title to normalize.
     * @param string $locale Optional locale to use for normalization.
     */
    function ap_normalize_letter(string $title, string $locale = ''): string
    {
        return TitleTools::normalizeLetter($title, $locale);
    }
}
