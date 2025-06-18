# Changelog

All notable changes to this project will be documented in this file.

## [1.1.5] - 2025-06-18
### Added
- Membership management with Stripe webhook integration for subscription handling.
- REST API endpoints for favorites (add/remove), notifications (list, mark read), submissions, and artists.
- User dashboard shortcode and REST endpoints to display membership and user content.
- Directory shortcode with REST API filters for events, artists, artworks, and organizations.
- Profile link request handling with REST API.
- WooCommerce integration support.
- PHPUnit testing suite with Brain Monkey mocks.
- GitHub Actions workflow for CI/CD including PHPUnit tests and code coverage reporting.

### Changed
- Refactored core classes with PSR-4 autoloading and namespaces.
- Improved REST API permission callbacks and argument validation.
- Added code coverage generation and static analysis checks in CI pipeline.
- Enhanced error handling and response formatting in REST controllers.

### Fixed
- Fixed autoloading issues for some classes to comply with PSR-4.
- Corrected REST route registration timing to run on `rest_api_init` hook.
- Resolved duplicate test class names and test file organization.

---

## [1.0.0] - Initial release
- Basic plugin setup with custom post types: events, artists, artworks, organizations.
- Shortcodes for directory display.
- Membership roles and simple favorites system.
- Basic REST API endpoints.
- Initial testing setup.
