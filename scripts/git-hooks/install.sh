#!/usr/bin/env bash
set -euo pipefail

git config core.hooksPath scripts/git-hooks

echo "Git hooks path configured to scripts/git-hooks."
echo "Run 'git config --unset core.hooksPath' to undo."
