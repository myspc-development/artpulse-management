#!/usr/bin/env bash
set -e

# Phase 8: Documentation & Release Packaging Script
# File: scripts/release.sh

PLUGIN_DIR="$(pwd)"
PLUGIN_NAME="artpulse-management-plugin"

# Extract version from plugin header (e.g. "1.1.5")
# The third field after splitting on whitespace contains the numeric version
VERSION=$(grep -m1 '^ \* Version:' artpulse-management.php | awk '{print $3}')

# Sanity check that we parsed a version number
if [[ ! "$VERSION" =~ ^[0-9]+(\.[0-9]+)*$ ]]; then
  echo "❌ Failed to parse plugin version" >&2
  exit 1
fi
RELEASE_DIR="$PLUGIN_DIR/release"
ZIP_FILE="$PLUGIN_NAME-$VERSION.zip"

echo "🔨 Building release package for version $VERSION …"

# 1. Prepare release directory
rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

# 2. Install production dependencies
composer install --no-dev --optimize-autoloader

# 3. Copy plugin files to temp directory
TMPDIR=$(mktemp -d)
echo "📂 Copying files to temp dir $TMPDIR"
# Vendor assets live under assets/vendor and are included by default
rsync -a --exclude 'scripts' --exclude 'tests' --exclude 'node_modules' --exclude '.git' --exclude 'phpunit.xml.dist' "$PLUGIN_DIR/" "$TMPDIR/"

# 4. Create ZIP archive
cd "$TMPDIR"
zip -r "$RELEASE_DIR/$ZIP_FILE" .
cd -

echo "✅ Release package created: $RELEASE_DIR/$ZIP_FILE"

# 5. Cleanup
rm -rf "$TMPDIR"

echo "🎉 Release script complete!"
