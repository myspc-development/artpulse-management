#!/bin/bash
# Run this from your plugin's root directory

set -e

# Database credentials for WP test environment
DB_NAME=${DB_NAME:-wordpress_test}
DB_USER=${DB_USER:-root}
DB_PASSWORD=${DB_PASSWORD:-root}
DB_HOST=${DB_HOST:-127.0.0.1}

echo "=== Downloading WordPress core into ./wordpress ==="
mkdir -p wordpress
curl -O https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz -C wordpress --strip-components=1
rm latest.tar.gz

echo "=== Creating wp-tests-config.php with correct paths and DB settings ==="
cat > wp-tests-config.php <<EOF
<?php
// DB settings for your test database
define( 'DB_NAME', '$DB_NAME' );
define( 'DB_USER', '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASSWORD' );
define( 'DB_HOST', '$DB_HOST' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// Site constants required by WP test suite
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

// PHP binary for running tests
define( 'WP_PHP_BINARY', 'php' );

// Path to WordPress source
define( 'ABSPATH', dirname( __FILE__ ) . '/wordpress' );

// Bootstrap the WordPress environment for testing
require_once ABSPATH . '/wp-settings.php';
EOF

echo "=== Done setting up wp-tests-config.php ==="
echo "You can now run: vendor/bin/phpunit --testdox"

