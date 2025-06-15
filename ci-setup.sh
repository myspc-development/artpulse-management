#!/bin/bash
# Install Node and Composer dependencies for CI environments
set -e

# Install PHP dependencies
if command -v composer >/dev/null 2>&1; then
  composer install --no-interaction --prefer-dist
else
  echo "composer not found" >&2
  exit 1
fi

# Install Node dependencies
if command -v npm >/dev/null 2>&1; then
  npm install --no-progress
else
  echo "npm not found" >&2
  exit 1
fi
