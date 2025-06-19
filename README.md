📦 ArtPulse Management Plugin
Version: 1.1.5
Author: Craig
License: GPL-2.0
Text Domain: artpulse

🎨 Overview
ArtPulse Management is a powerful WordPress plugin that enables seamless management of artists, artworks, events, organizations, and user engagement through customizable dashboards and automation tools. Built for modern art platforms and creative collectives.

🚀 Features
🔐 Membership Management — Free, Pro, and Org user roles with capabilities

🎭 Custom Post Types — Events, Artists, Artworks, Organizations

📊 Admin Dashboards — Analytics, Webhooks, and user engagement panels

💳 Stripe Integration — Subscription billing and webhook support

🌐 REST API — Extensible endpoints for frontend interaction

🧩 Gutenberg Blocks — Filterable taxonomies and listing blocks

🛠️ Role-based Access — Controls for frontend and backend capabilities

🗃️ User Directory — Filtered views of artist and organization profiles

🧑‍💻 Installation
Clone or download this repo into your WordPress plugins directory:

```bash
git clone https://github.com/myspc-development/artpulse-management.git
cd artpulse-management
# Install PHP and Node dependencies
composer install
npm install # or `npm ci` for reproducible builds
# Compile block assets
npm run build
```

Activate the plugin in WordPress Admin:
Plugins → ArtPulse Management → Activate

🛠️ Developer Setup

```bash
# Install dependencies
composer install
npm install # or `npm ci`
# Build block assets for development
npm run build

# Set up the testing environment
./setup-tests.sh
```
Before running this script, supply your database credentials using the
environment variables `DB_NAME`, `DB_USER`, `DB_PASSWORD` and
`DB_HOST`. If any of these are missing, the script falls back to
`wordpress_test`, `root`, `root` and `127.0.0.1`. Alternatively you can
create a `.env` file with the same keys to override the defaults.

Ensure that `curl` (or `wget`) and `svn` are installed as they are used
to download WordPress and the official test suite.

Optional tools:

phpunit for unit tests

phpcs for coding standards (composer run lint)

🔌 Plugin Structure
Folder	Purpose
src/	Core plugin classes
admin/	Admin-specific logic
templates/	HTML/PHP template views
tests/	Unit tests and mocks
assets/	CSS/JS used across admin/frontend
languages/	Translations (i18n)
docs/	Design notes, roadmap, changelog

🔒 Capabilities & Roles
Role	Key Capabilities
subscriber	View public profiles
artist	Submit/manage own artworks
org	Manage org events/artists
admin	Full plugin access

📈 REST API Highlights
GET /wp-json/artpulse/v1/submissions

POST /wp-json/artpulse/v1/submissions

GET /wp-json/artpulse/v1/analytics

Requires wp_rest nonce for authenticated operations.

🗺 Roadmap Highlights
✅ Enhanced analytics widgets

⏳ Member invoices and payment history

🔜 Public profile directory filtering

🔜 GDPR/data export tools

See docs/ROADMAP.md for full roadmap

📄 License
GNU General Public License v2.0
See LICENSE for full text.

🤝 Contributing
Fork this repo

Create a feature branch: git checkout -b feature/your-feature

Submit a PR with a clear description