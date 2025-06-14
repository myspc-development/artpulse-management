#!/bin/bash

# Configuration
USERNAME="myspchosting"
REPO_URL="https://github.com/myspc-development/artpulse-management.git"
CLONE_DIR="$HOME/artpulse-management"

# Prompt for GitHub password or personal access token (input hidden)
read -s -p "🔐 Enter GitHub password or token for $USERNAME: " PASSWORD
echo ""

# Construct authenticated URL
AUTH_URL="https://${USERNAME}:${PASSWORD}@github.com/myspc-development/artpulse-management.git"

# Clone if not exists
if [ ! -d "$CLONE_DIR/.git" ]; then
    echo "📁 Cloning repository to $CLONE_DIR..."
    git clone "$AUTH_URL" "$CLONE_DIR"
else
    echo "🔄 Pulling latest changes..."
    cd "$CLONE_DIR" || { echo "❌ Failed to enter directory."; exit 1; }
    git pull "$AUTH_URL"
fi

echo "✅ Git pull complete."
