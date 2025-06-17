#!/usr/bin/env bash
set -e

# Ensure these are set, or exit with an error
: "${GITHUB_USER:?Need to set GITHUB_USER}"
: "${GITHUB_PAT:?Need to set GITHUB_PAT}"

# Your repo details
REPO="myspc-development/artpulse-management-plugin"
BRANCH="main"

# Construct the authenticated HTTPS URL
AUTHED_URL="https://${GITHUB_USER}:${GITHUB_PAT}@github.com/${REPO}.git"

# Point origin to the authed URL
git remote set-url origin "${AUTHED_URL}"

# Push and set upstream
git push -u origin "${BRANCH}"

echo "✅ Pushed ${BRANCH} to GitHub over HTTPS with PAT."
gitpush
