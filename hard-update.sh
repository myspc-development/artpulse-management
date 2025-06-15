#!/bin/bash

# Configuration
REPO_URL="https://github.com/myspc-development/artpulse-management.git"
TARGET_DIR="/www/wwwroot/192.168.88.31/wp-content/plugins/artpulse-management-main"

echo "🚀 Starting hard update from remote 'main' branch..."

# Navigate to the target directory
cd "$TARGET_DIR" || { echo "❌ Failed to enter $TARGET_DIR"; exit 1; }

# Initialize Git repo if not present
if [ ! -d ".git" ]; then
    echo "🛠 Initializing Git repository..."
    git init
    git remote add origin "$REPO_URL"
fi

# Fetch and hard reset to origin/main
git fetch origin
git reset --hard origin/main

# Remove untracked files and directories
git clean -fd

echo "✅ Hard update complete. Local files now match origin/main exactly."
