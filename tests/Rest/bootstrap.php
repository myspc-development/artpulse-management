<?php
// tests/bootstrap.php

$project_root = dirname(__DIR__);
$autoload     = $project_root . '/vendor/autoload.php';
$tests_dir    = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

require_once $autoload;

// If running WordPress integration tests:
if (file_exists($tests_dir . '/includes/functions.php')) {
    require_once $tests_dir . '/includes/functions.php';

    function _manually_load_plugin() {
        require dirname(__DIR__) . '/artpulse-management.php'; // Adjust if plugin file differs
    }

    tests_add_filter('muplugins_loaded', '_manually_load_plugin');

    require_once $tests_dir . '/includes/bootstrap.php';
} else {
    // Basic function stubs for isolated unit tests with Brain Monkey
    error_reporting(E_ALL);
    ini_set('display_errors', '1');

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
        function register_post_meta($post_type, $meta_key, $args = []) {}
    }

    if (!function_exists('register_taxonomy')) {
        function register_taxonomy($taxonomy, $object_type, $args = []) {}
    }

    if (!function_exists('str_starts_with')) {
        function str_starts_with($haystack, $needle) {
            return substr($haystack, 0, strlen($needle)) === $needle;
        }
    }
}
