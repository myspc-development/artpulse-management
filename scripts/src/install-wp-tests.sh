#!/bin/bash
set -e

# Input args
DB_NAME=${1:-wordpress_test}
DB_USER=${2:-root}
DB_PASS=${3:-root}
DB_HOST=${4:-127.0.0.1}
WP_VERSION=${5:-latest}

# Paths
WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}

# Download WordPress
download() {
  if [ ! -d "$WP_CORE_DIR" ]; then
    mkdir -p "$WP_CORE_DIR"
    curl -L -o /tmp/wordpress.tar.gz https://wordpress.org/wordpress-"$WP_VERSION".tar.gz
    tar -xzf /tmp/wordpress.tar.gz -C /tmp/
  fi
}

# Install test library
install_test_suite() {
  mkdir -p "$WP_TESTS_DIR"

  if [ ! -f "$WP_TESTS_DIR/includes/functions.php" ]; then
    svn export --quiet https://develop.svn.wordpress.org/tags/"$WP_VERSION"/tests/phpunit/includes/ "$WP_TESTS_DIR"/includes
  fi

  if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
    svn export --quiet https://develop.svn.wordpress.org/tags/"$WP_VERSION"/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
    sed -i "s:dirname( __FILE__ ) . '/wordpress/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
    sed -i "s/yourdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
    sed -i "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
    sed -i "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
    sed -i "s|localhost|$DB_HOST|" "$WP_TESTS_DIR"/wp-tests-config.php
  fi
}

# Create database
create_db() {
  mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" || true
}

# Execute steps
download
install_test_suite
create_db
