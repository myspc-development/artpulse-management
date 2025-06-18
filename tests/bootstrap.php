<?php
// tests/bootstrap.php

// Explicitly load the wp-tests-config.php to ensure required constants are defined
require_once dirname(__DIR__) . '/wp-tests-config.php';

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Location of wp-phpunit package
$_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';

// Check for WP test functions file
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    fwrite(STDERR, "ERROR: Could not find {$_tests_dir}/includes/functions.php\n");
    exit(1);
}

// Load WordPress test functions
require_once $_tests_dir . '/includes/functions.php';

// Load your plugin manually for testing
tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/artpulse-management.php'; // Adjust if your main plugin file name is different
});

// Bootstrap the WordPress test environment; this will use the config loaded above
require $_tests_dir . '/includes/bootstrap.php';
