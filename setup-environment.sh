#!/bin/bash

set -e

# Install required system packages using apt
if command -v apt-get >/dev/null; then
    echo "=== Installing system packages via apt ==="
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
        php php-cli php-mysql \
        nodejs npm \
        curl subversion \
        mysql-client
else
    echo "apt-get not found. Please install PHP 8.2+, Node.js/npm, curl, svn, and mysql client manually."
fi

# Install PHP dependencies
if command -v composer >/dev/null; then
    echo "=== Installing PHP dependencies with composer ==="
    composer install --no-interaction --prefer-dist
else
    echo "composer not found; attempting to install locally"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    composer install --no-interaction --prefer-dist
fi

# Install Node packages
if [ -f package-lock.json ]; then
    echo "=== Installing Node packages with npm ci ==="
    npm ci
else
    echo "=== Installing Node packages with npm install ==="
    npm install
fi

# Build block assets
npm run build

# Set up WordPress test environment
./setup-tests.sh

echo "Environment setup complete. Run tests with: vendor/bin/phpunit --testdox"
