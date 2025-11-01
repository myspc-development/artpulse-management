#!/usr/bin/env bash
set -euo pipefail

changed_php_files=()
while IFS= read -r -d '' file; do
  case "$file" in
    *.php)
      changed_php_files+=("$file")
      ;;
  esac
done < <(git diff --cached --name-only --diff-filter=ACMR -z)

if [ ${#changed_php_files[@]} -eq 0 ]; then
  exit 0
fi

echo "\nRunning PHP pre-commit checks for staged files:"
for file in "${changed_php_files[@]}"; do
  printf '  • %s\n' "$file"
  if ! [ -f "$file" ]; then
    echo "    (file deleted or missing in working tree, skipping)"
  fi
done

echo "\nLinting PHP files with php -l..."
if ! command -v php >/dev/null 2>&1; then
  echo "php binary not found in PATH"
  exit 1
fi
for file in "${changed_php_files[@]}"; do
  if [ -f "$file" ]; then
    php -l "$file"
  fi
done

echo "\nRunning PHP_CodeSniffer..."
if [ -x vendor/bin/phpcs ]; then
  vendor/bin/phpcs --standard=WordPress --extensions=php "${changed_php_files[@]}"
else
  echo "vendor/bin/phpcs not found; skipping sniff step"
fi

echo "\nRunning composer audit:upgrades..."
if ! command -v composer >/dev/null 2>&1; then
  echo "composer binary not found in PATH"
  exit 1
fi
composer audit:upgrades
