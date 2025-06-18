#!/usr/bin/env bash

# Usage: bash bin/install-wp-tests.sh db_name db_user db_pass db_host wp_version

set -e

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=$4
WP_VERSION=${5:-latest}

WP_TESTS_DIR=/tmp/wordpress-tests-lib
WP_CORE_DIR=/tmp/wordpress/

download() {
  if [ "$(which curl)" ]; then
    curl -s "$1" > "$2"
  elif [ "$(which wget)" ]; then
    wget -nv -O "$2" "$1"
  else
    echo "❌ Neither curl nor wget is available. Please install one to continue."
    exit 1
  fi
}

install_wp() {
  if [ ! -d "$WP_CORE_DIR" ]; then
    mkdir -p "$WP_CORE_DIR"
  fi

  if [ ! -f "$WP_CORE_DIR/wp-load.php" ]; then
    echo "⬇️ Downloading WordPress..."
    download https://wordpress.org/latest.tar.gz /tmp/wordpress.tar.gz
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
  fi
}

install_test_suite() {
  if [ ! -d "$WP_TESTS_DIR" ]; then
    mkdir -p "$WP_TESTS_DIR"
    echo "⬇️ Installing WP test suite..."
    svn co --quiet https://develop.svn.wordpress.org/tags/"$WP_VERSION"/tests/phpunit/includes/ "$WP_TESTS_DIR"/includes
    download https://develop.svn.wordpress.org/tags/"$WP_VERSION"/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
    sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" "$WP_TESTS_DIR"/wp-tests-config.php
    sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
    sed -i "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
    sed -i "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
    sed -i "s|localhost|$DB_HOST|" "$WP_TESTS_DIR"/wp-tests-config.php
  fi
}

install_db() {
  echo "🛠 Creating test database if it doesn't exist..."
  mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" -f 2>/dev/null || {
    echo "⚠️ Skipped DB creation or already exists."
  }
}

install_wp
install_test_suite
install_db

echo "✅ WordPress test environment ready."
