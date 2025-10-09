<?php

namespace ArtPulse\Core;

use function current_time;
use function delete_transient;
use function get_transient;
use function sanitize_title;
use function set_transient;
use function strtotime;

/**
 * Prevent duplicate event submissions across entry points.
 */
final class EventDuplicateGuard
{
    private const TRANSIENT_PREFIX = 'ap_event_dedupe_';
    private const WINDOW = 60;

    /**
     * Generate the dedupe transient key for a submission payload.
     */
    public static function generate_key(int $owner_id, string $title, string $event_start): ?string
    {
        if ($owner_id <= 0) {
            return null;
        }

        $sanitized_title = sanitize_title($title);
        $normalized_time = self::normalize_timestamp($event_start);

        if ('' === $sanitized_title && 0 === $normalized_time) {
            return null;
        }

        $hash = md5($sanitized_title . '|' . $normalized_time . '|' . $owner_id);

        return self::TRANSIENT_PREFIX . $hash;
    }

    /**
     * Determine if a duplicate submission is currently locked.
     */
    public static function is_duplicate(?string $key): bool
    {
        if (null === $key) {
            return false;
        }

        return false !== get_transient($key);
    }

    /**
     * Mark the submission as in-flight for the dedupe window.
     */
    public static function lock(?string $key): void
    {
        if (null === $key) {
            return;
        }

        set_transient($key, current_time('timestamp', true), self::WINDOW);
    }

    /**
     * Clear the transient lock.
     */
    public static function clear(?string $key): void
    {
        if (null === $key) {
            return;
        }

        delete_transient($key);
    }

    private static function normalize_timestamp(string $event_start): int
    {
        $timestamp = strtotime($event_start);

        if (false === $timestamp) {
            return 0;
        }

        return (int) $timestamp;
    }
}
