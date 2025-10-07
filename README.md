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

🗃️ User Directory — Filtered views of artist and organization profiles powered by `[ap_artists_directory]` and `[ap_orgs_directory]`

🧭 Organization Onboarding — `[ap_register_organization]` shortcode to collect org sign-ups, auto-assign creators, notify admins, and promote follow/favorite actions

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
./setup-environment.sh
```
This project requires **PHP 8.2+** and **Node.js 18+** for development.
Before running this script, supply your database credentials using the
environment variables `DB_NAME`, `DB_USER`, `DB_PASSWORD` and
`DB_HOST`. If any of these are missing, the script falls back to
`wordpress_test`, `root`, `root` and `127.0.0.1`. Alternatively you can
create a `.env` file with the same keys to override the defaults.

The script installs required system packages (PHP, Node, curl, svn and
the MySQL client), fetches PHP and Node dependencies, builds the block
assets and configures the WordPress test environment.

Run the test suite with:

```bash
vendor/bin/phpunit --testdox
```

### Composer authentication

Composer requires GitHub authentication to avoid API rate limits when installing
packages from repositories hosted on GitHub. Configure OAuth once locally before
running `composer install`:

```bash
composer config -g github-oauth.github.com YOUR_GH_PAT
```

Alternatively, write the credentials directly to `~/.composer/auth.json`:

```bash
mkdir -p ~/.composer
printf '{ "github-oauth": { "github.com": "YOUR_GH_PAT" } }' > ~/.composer/auth.json
```

If you must work in an environment without GitHub access, restore a pre-built
`vendor/` directory or point Composer at an internal Packagist mirror:

```bash
composer config -g repos.packagist composer https://packagist.mycompany.com
```

Tests also run automatically in CI via GitHub Actions, which installs Composer
dependencies (`composer install --no-interaction --prefer-dist`) and executes
`vendor/bin/phpunit --testdox --colors=always` with `WP_PHPUNIT__DIR` pointing to
`vendor/wp-phpunit/wp-phpunit`. Build Composer vendors on CI or a development
machine and deploy the generated `vendor/` directory—avoid running Composer or
PHPUnit on production servers.

### Local development quickstart

```bash
# One-time Composer OAuth
composer config -g github-oauth.github.com YOUR_GH_PAT

# Install & test
composer install
composer test   # or test:unit / test:int
npm ci
npm run test:e2e  # if using wp-env locally
```

### WP-CLI utilities

Backfill cached directory letter metadata for artists or organizations. This keeps
canonical directory URLs fast when a large amount of content is imported.

```bash
wp artpulse backfill-letters --post_type=artpulse_artist --batch=100
wp artpulse backfill-letters --post_type=artpulse_org --batch=250
```

`--post_type` accepts any registered directory post type (`artpulse_artist` or
`artpulse_org`). `--batch` controls how many posts are processed per query and
defaults to `100`.

The command loops until all published posts have a cached letter and outputs a
success message summarising the number of records updated.

Optional tools:

phpunit for unit tests

phpcs for coding standards (composer run lint)

### Verification

This repository ships with PHPUnit, PHPCS, and Playwright checks that mirror the
expected production environment (WordPress 6.5+, PHP 8.1+). Run the full suite
locally before shipping directory changes:

```bash
composer test        # All suites
composer test:unit   # WordPress-aware unit tests
composer test:int    # Integration/UI layer coverage
npm run test:e2e     # Lightweight Playwright smoke run (requires wp-env)
```

Handy WP-CLI and curl commands for manual spot checks:

```bash
wp rewrite flush --hard

curl -I https://example.test/artists/letter/a/ \
  | grep -i "rel=\"canonical\""
curl -s https://example.test/artists/letter/a/ \
  | grep -i "aria-current=\"page\""
curl -s https://example.test/sitemap-artpulse-directories.xml \
  | grep '/artists/letter/a/'
```

The curl examples confirm that canonical URLs, active letter states, and the
directory sitemap respond with server-rendered HTML (no JavaScript required).

📘 Directory Shortcode Examples

```
[ap_artists_directory]
[ap_artists_directory per_page="36" letter="B"]
[ap_orgs_directory]
[ap_orgs_directory per_page="18" letter="all"]
```

Both directory shortcodes honour the `per_page` attribute and use query
parameters for deep filtering (e.g. `?s=sculpture` or `?tax[artist_specialty][]=ceramics`).
When permalinks are enabled, letters map to friendly URLs such as
`/artists/letter/B/` and `/organizations/letter/all/`. Legacy installs can
continue using `/galleries/letter/{letter}/` by filtering the
`ap_galleries_directory_base` hook. Each directory renders a
canonical `<link>` tag pointing to the active letter URL (including search and
taxonomy query strings) and caches rendered output for six hours. Caches flush
when relevant posts, taxonomies, or metadata change.

📅 Events Calendar & Portfolio

Embed the FullCalendar-powered interface or Salient-ready portfolio grid using the
new `[ap_events]` shortcode or the matching "ArtPulse Events" block.

```
[ap_events layout="calendar" view="dayGridMonth" show_filters="true"]
[ap_events layout="grid" per_page="12" category="openings" orderby="event_start" order="ASC"]
[ap_events layout="tabs" favorites="true" show_filters="true"]
```

Supported shortcode/block attributes:

* `layout` – `calendar`, `grid`, or `tabs` (default `calendar`).
* `start` / `end` – Pre-filter by ISO date strings (falls back to filter UI values).
* `category` – Comma-separated list of `artpulse_event_type` slugs.
* `org` – Organization post ID to scope events.
* `favorites` – `true` to show only the current user's favourites.
* `view` – Default FullCalendar view (e.g. `dayGridMonth`, `timeGridWeek`, `listWeek`).
* `initialDate` – ISO date for the initial calendar focus.
* `show_filters` – Toggle the Salient-styled filter toolbar.
* `per_page` – Number of expanded event occurrences rendered per page.

The block exposes the same controls inside the Gutenberg inspector for editors who
prefer point-and-click configuration. Filters sync with URL query arguments such as
`?ap_event_start=2024-09-01&ap_event_category[]=openings`, making curated views easy
to bookmark or share.

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

Before committing changes that impact block assets, run `npm run build` and include the generated files in your commit. The CI workflow validates that the compiled bundles in `assets/js` and `assets/css` are up to date, and the build will fail if they are not.

Run `composer validate --no-check-publish` before pushing updates to ensure the Composer metadata remains valid. The CI workflow stops early when the manifest contains errors.
