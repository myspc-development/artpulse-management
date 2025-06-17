#!/bin/bash

echo "🔍 Checking directories that can usually be excluded from a WordPress GitHub repo..."

# List of common folders that shouldn't be committed
IGNORABLE_DIRS=("node_modules" "vendor" "build" "dist" ".vscode" ".idea" ".git" ".github" ".cache")

# Scan current project
for dir in */; do
    dirname=${dir%/}
    if [[ " ${IGNORABLE_DIRS[*]} " == *" $dirname "* ]]; then
        echo "❌ $dirname — typically ignored (add to .gitignore)"
    else
        echo "✅ $dirname — likely relevant"
    fi
done

echo -e "\n📄 Suggested .gitignore entries:"
for dir in "${IGNORABLE_DIRS[@]}"; do
    echo "$dir/"
done
