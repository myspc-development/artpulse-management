#!/usr/bin/env bash
set -e

BASE="$(pwd)"

echo "🚀 Scaffolding Phase 7: Automated Testing & CI…"

# 1) Install PHPUnit via Composer
echo "Installing PHPUnit..."
composer require --dev phpunit/phpunit:^9 --prefer-dist

# 2) Create tests directory
mkdir -p "$BASE/tests"

# 3) Create phpunit.xml.dist
cat > "$BASE/phpunit.xml.dist" << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true" verbose="true">
    <testsuites>
        <testsuite name="ArtPulse Test Suite">
            <directory>./tests/</directory>
        </testsuite>
    </testsuites>
</phpunit>
EOF

echo "✅ Created phpunit.xml.dist"

# 4) Create tests/bootstrap.php
cat > "$BASE/tests/bootstrap.php" << 'EOF'
<?php
// Autoload plugin code
require __DIR__ . '/../vendor/autoload.php';
// TODO: Include WP test loader if using WP testing framework
EOF

echo "✅ Created tests/bootstrap.php"

# 5) Create a sample test file
echo "Creating sample test..."
cat > "$BASE/tests/TestPostTypeRegistrar.php" << 'EOF'
<?php
use PHPUnit\Framework\TestCase;
use ArtPulse\Core\PostTypeRegistrar;

class TestPostTypeRegistrar extends TestCase
{
    public function testRegisterMethodExists()
    {
        \$this->assertTrue(
            method_exists(PostTypeRegistrar::class, 'register'),
            'PostTypeRegistrar::register method should exist'
        );
    }
}
EOF

echo "✅ Created sample test"

# 6) Remind to update CI workflow
cat << 'MSG'

🎉 Phase 7 scaffolding complete!

Next manual steps:
  • Update your GitHub Actions workflow (.github/workflows/ci.yml) to run phpunit:
      - composer install --prefer-dist --no-interaction
      - vendor/bin/phpunit --configuration phpunit.xml.dist
  • Expand tests under tests/ for other core classes.

Commit and push these changes:
  git add composer.json composer.lock phpunit.xml.dist tests/
  git commit -m "Phase 7: scaffold testing framework and sample test"
  ./push-with-pat.sh

MSG
