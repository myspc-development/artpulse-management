<?php
namespace ArtPulse\Core;

/**
 * Utilities for working with post titles within ArtPulse directories.
 */
class TitleTools
{
    public const META_KEY = '_ap_letter_key';

    /**
     * Register hooks for maintaining normalized title metadata.
     */
    public static function register(): void
    {
        add_action('save_post_artpulse_artist', [self::class, 'update_post_letter_on_save'], 10, 3);
        add_action('save_post_artpulse_org', [self::class, 'update_post_letter_on_save'], 10, 3);
    }

    /**
     * Normalise a title to its directory letter bucket.
     *
     * @param string $title  The raw post title.
     * @param string $locale Optional locale hint.
     */
    public static function normalizeLetter(string $title, string $locale = ''): string
    {
        $title = trim($title);
        if ('' === $title) {
            return '#';
        }

        $locale = $locale ?: determine_locale();
        $articles = apply_filters('ap_articles', ['the ', 'a ', 'an '], $locale);

        $working = $title;
        if (!empty($articles)) {
            $lower = function_exists('mb_strtolower') ? mb_strtolower($working, 'UTF-8') : strtolower($working);
            foreach ($articles as $article) {
                $article = is_string($article) ? $article : '';
                if ('' === $article) {
                    continue;
                }
                $articleLower = function_exists('mb_strtolower') ? mb_strtolower($article, 'UTF-8') : strtolower($article);
                $length = function_exists('mb_strlen') ? mb_strlen($articleLower, 'UTF-8') : strlen($articleLower);
                if ($length > 0 && 0 === strpos($lower, $articleLower)) {
                    $working = ltrim(function_exists('mb_substr') ? mb_substr($working, $length, null, 'UTF-8') : substr($working, $length));
                    break;
                }
            }
        }

        if (class_exists('\\Normalizer')) {
            $normalized = \Normalizer::normalize($working, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $stripped = preg_replace('/\p{Mn}+/u', '', $normalized);
                if (is_string($stripped)) {
                    $working = $stripped;
                } else {
                    $working = $normalized;
                }
            }
        }

        $working = trim($working);
        if ('' === $working) {
            return '#';
        }

        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $working);
        if (false === $transliterated || '' === $transliterated) {
            $transliterated = $working;
        }

        $transliterated = trim($transliterated);
        if ('' === $transliterated) {
            return '#';
        }

        if (function_exists('mb_substr')) {
            $first = mb_substr($transliterated, 0, 1, 'UTF-8');
            if (false === $first) {
                $first = substr($transliterated, 0, 1);
            }
        } else {
            $first = substr($transliterated, 0, 1);
        }

        if (function_exists('mb_strtoupper')) {
            $upper = mb_strtoupper($first, 'UTF-8');
            if (false === $upper) {
                $upper = strtoupper($first);
            }
        } else {
            $upper = strtoupper($first);
        }

        $first = $upper;
        $first = apply_filters('ap_letter_map', $first, $title, $locale);

        return preg_match('/^[A-Z]$/', $first) ? $first : '#';
    }

    /**
     * Ensure the normalised letter meta for a post is up to date.
     */
    public static function update_post_letter(int $post_id, string $title = '', string $locale = ''): string
    {
        $title = $title ?: get_the_title($post_id);
        $letter = self::normalizeLetter($title, $locale);

        $current = get_post_meta($post_id, self::META_KEY, true);
        if ($current !== $letter) {
            update_post_meta($post_id, self::META_KEY, $letter);
        }

        return $letter;
    }

    /**
     * Backfill missing letter metadata for a post type.
     */
    public static function backfill_missing_letters(string $post_type, int $limit = 50): void
    {
        global $wpdb;

        $post_type = sanitize_key($post_type);
        if ('' === $post_type) {
            return;
        }

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} AS p
             LEFT JOIN {$wpdb->postmeta} AS m ON (p.ID = m.post_id AND m.meta_key = %s)
             WHERE p.post_type = %s AND p.post_status = 'publish' AND m.post_id IS NULL
             LIMIT %d",
            self::META_KEY,
            $post_type,
            $limit
        ));

        if (empty($ids)) {
            return;
        }

        foreach ($ids as $post_id) {
            $post = get_post((int) $post_id);
            if ($post && $post->post_type === $post_type) {
                self::update_post_letter((int) $post->ID, $post->post_title);
            }
        }
    }

    /**
     * Callback hooked to save_post to refresh the cached letter.
     */
    public static function update_post_letter_on_save(int $post_id, \WP_Post $post, bool $update): void
    {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        if (!in_array($post->post_type, ['artpulse_artist', 'artpulse_org'], true)) {
            return;
        }

        self::update_post_letter($post_id, $post->post_title);
    }
}
