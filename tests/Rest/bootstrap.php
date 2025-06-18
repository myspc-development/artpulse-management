<?php
// tests/bootstrap.php

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

// WP function stubs (safe to stub manually if not mocked in tests)
if (!function_exists('get_option')) {
    function get_option($key, $default = null) {
        return $default;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test-nonce';
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '') {
        return 'http://example.test/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'timestamp') {
        return $type === 'timestamp' ? time() : '';
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp) {
        $fmt = $format ?: 'Y-m-d';
        return date($fmt, $timestamp);
    }
}

if (!function_exists('register_post_meta')) {
    function register_post_meta($post_type, $meta_key, $args = []) {
        // no-op; this is a stub for tests
    }
}

if (!function_exists('register_taxonomy')) {
    function register_taxonomy($taxonomy, $object_type, $args = []) {
        // no-op; this is a stub for tests
    }
}

// Polyfill str_starts_with for PHP < 8.0
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

// PSR-4 autoloading for ArtPulse\Core\ classes in src/Core/
spl_autoload_register(function ($class) {
    $prefix  = 'ArtPulse\\Core\\';
    $baseDir = __DIR__ . '/../src/Core/';

    if (str_starts_with($class, $prefix)) {
        $rel  = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $rel) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
});
