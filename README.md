# ArtPulse Management Plugin

The ArtPulse Management Plugin is a modular WordPress plugin designed for creative communities and cultural organizations. It supports managing artists, artworks, events, memberships, directories, and more, with REST and WooCommerce integrations.

---

## 🔧 Features

- ✅ Custom Post Types: Events, Artists, Artworks, Organizations
- ✅ Taxonomies: Event Types, Mediums
- ✅ REST API Meta Fields
- ✅ Admin Settings Page
- ✅ Meta Boxes for CPT fields
- ✅ Membership System (Free, Pro, Enterprise)
- ✅ WooCommerce Integration (optional)
- ✅ Shortcodes: `[ap_user_dashboard]`, `[ap_directory]`, `[ap_user_favorites]`
- ✅ Favorites Button UI with REST Support
- ✅ Directory Listings (filtered, paginated)
- ✅ Modular architecture using PSR-4
- ✅ Custom DB tables for favorites and follows
- 🟡 Custom Capabilities (partially implemented)
- 🟡 REST API Extensions (basic coverage)
- 🔲 Gutenberg Blocks (not yet implemented)
- 🟡 Internationalization (i18n) in progress
- 🟡 Profile linking, public badges
- 🔲 WP-CLI Import Tools
- 🔲 CI/CD GitHub Actions Pipeline

---

## 🚀 Getting Started

1. Clone or download this repository.
2. Run:

    ```bash
    composer install
    ```

3. Activate the plugin in WordPress Admin.
4. Visit **Settings → Permalinks → Save Changes** to flush rewrite rules.

---

## 📁 Architecture

- Composer-based PSR-4 autoloading
- Folder layout:
  - `src/Core/`: Core plugin logic
  - `src/Community/`: Social features (follows, favorites)
  - `src/Admin/`: Admin UI and CPT customizations
  - `src/Blocks/`: Future Gutenberg block support
- Integrations:
  - WordPress REST API
  - WooCommerce (optional)
  - Memberships and roles

---

## 📌 Development & Roadmap

See [ROADMAP.md](ROADMAP.md) for full development plans, phases, and priorities.

---

## 🛠 To Do

- [ ] Capability mapping for roles
- [ ] Custom REST endpoints
- [ ] Gutenberg blocks
- [ ] i18n/translation support
- [ ] PHPUnit test coverage
- [ ] Profile linking and badges
- [ ] WP-CLI import tool
- [ ] GitHub Actions CI/CD pipeline

---

© 2025 ArtPulse. GPLv2 Licensed.
