# Changelog

All notable changes to this project will be documented in this file.

## [3.7.5] - 2025-06-16
### Fixed
- Loaded `RolesManager` alias on `plugins_loaded` with priority 0 for
  compatibility with early callbacks.

## [3.7.6] - 2025-06-17
### Added
- Bundled Select2 assets locally and updated admin and frontend forms to load them without using a CDN.

## [3.7.7] - 2025-06-18
### Added
- Simple Membership Manager page under Settings listing user memberships.

## [3.7.8] - 2025-06-21
### Fixed
- Assign first gallery image as featured thumbnail when saving artwork posts.
- Guard attachment lookups in artist and organization templates to avoid
  deprecation warnings.

## [3.7.4] - 2025-06-15
### Fixed
- Ensured `RolesManager` is loaded before aliasing to avoid activation errors.

## [3.7.3] - 2025-06-14
### Added
- Calendar view in the user dashboard to browse events visually.

## [3.7.2] - 2025-06-13
### Added
- Advanced CSV importer with progress tracking and optional log download.

## [3.7.1] - 2025-06-12
### Changed
- Removed deprecated `includes/meta-boxes.php` to avoid duplicate Artwork Details meta boxes.
- Updated plugin version constants.

## [3.7.0] - 2025-06-11
### Added
- Organizer and artist dashboards with event management tools.
- User reviews system with moderation dashboard and shortcodes.
- Featured listings support and admin bulk actions.
- Mobile routes served via REST endpoints.
- Roles manager for custom capabilities.
- Data export page for CSV downloads.

## [1.0.0] - 2025-06-01
### Added
- Initial release of Event Art Directory plugin.
- Custom Post Types: Events & Organizations.
- Advanced meta fields (dates, contacts, gallery).
- CSV import/export with field mapping and error logging.
- Frontend directory search with AJAX filter and pagination.
- React directory search component (optional).
- REST API endpoints for Events & Organizations.
- Frontend event submission with moderation.
- Admin moderation dashboard with bulk approve/reject and email notifications.
- Email customization with filters (HTML and attachments supported).
- Plugin header, translation-ready.

---

## Upcoming
### Planned
- Testing and QA.
- PHPDoc complete coverage.
- Deployment automation (GitHub Actions).
- Additional features: maps, payments, calendar export, custom Gutenberg blocks.

---

*(C) 2025 Your Name. Licensed under GPL-2.0-or-later.*
