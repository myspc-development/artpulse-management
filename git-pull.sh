#!/bin/bash

# =============== CONFIG =====================
USERNAME="myspchosting"
REPO="myspc-development/artpulse-management"
CLONE_DIR="$HOME/artpulse-management"
# ============================================

# Prompt for GitHub token or password
read -s -p "🔐 Enter GitHub token or password for $USERNAME: " PASSWORD
echo ""

# Authenticated URL
REPO_URL="https://${USERNAME}:${PASSWORD}@github.com/${REPO}.git"

# Clone if needed
if [ ! -d "$CLONE_DIR/.git" ]; then
    echo "📁 Cloning repository..."
    git clone "$REPO_URL" "$CLONE_DIR" || { echo "❌ Clone failed"; exit 1; }
else
    echo "🔄 Pulling latest changes..."
    cd "$CLONE_DIR" || { echo "❌ Failed to enter repo directory"; exit 1; }

    # Use no-rebase strategy to avoid divergence error
    git pull --no-rebase origin main || {
        echo "❌ Git pull failed: divergent branches.";
        exit 1;
    }
fi

# Show recent commit dates
cd "$CLONE_DIR" || exit
echo "📅 Recent commit dates:"
git log --pretty=format:"%ad - %s" --date=short | head -10
