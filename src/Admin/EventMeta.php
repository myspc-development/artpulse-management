<?php
namespace EAD\Admin;

class EventMeta {

    /**
     * Sanitize gallery IDs.
     *
     * @param mixed $value The value to sanitize.
     * @return array Sanitized array of attachment IDs.
     */
    public static function sanitize_gallery_ids($value) {
        // If it's a string (e.g., "1,2,3"), turn it into an array.
        if (is_string($value)) {
            $ids = array_map('absint', explode(',', $value));
            return array_filter($ids);
        }

        // If it's already an array, sanitize each element.
        if (is_array($value)) {
            return array_map('absint', $value);
        }

        // Otherwise, return empty array.
        return [];
    }

    /**
     * Additional helper: Example of sanitizing boolean values.
     */
    public static function sanitize_boolean($value) {
        return (bool) $value;
    }

    /**
     * Example: Sanitize date strings to Y-m-d format.
     */
    public static function sanitize_date($date) {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        return date('Y-m-d', $timestamp);
    }
}
