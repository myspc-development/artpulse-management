#!/bin/bash

# =============== CONFIGURATION =====================
USERNAME="myspchosting"
PASSWORD="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"  # Recommended: use a personal access token
REPO="myspc-development/artpulse-management"
BRANCH="main"
PLUGIN_DIR="/www/wwwroot/192.168.88.31/wp-content/plugins/artpulse-management"
# ===================================================

echo "🚀 Starting force-push to remote repository..."

cd "$PLUGIN_DIR" || { echo "❌ Failed to enter directory $PLUGIN_DIR"; exit 1; }

# Initialize Git if not already a repo
if [ ! -d ".git" ]; then
    echo "⚠️ Not a Git repo. Initializing..."
    git init
    git remote add origin https://$USERNAME:$PASSWORD@github.com/$REPO.git
fi

# Add all files
git add .

# Commit all changes
git commit -m "🔄 Force push: Sync local changes to remote"

# Force push to overwrite remote
git push --force https://$USERNAME:$PASSWORD@github.com/$REPO.git $BRANCH

echo "✅ Force push completed! Remote repository is now updated with local code."
