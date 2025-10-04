# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

## Unreleased

### Fix
- Repair membership test fixtures and WooCommerce reflection usage so PHPUnit bootstrap succeeds without typos.
- Normalize REST test cases to use core `\WP_UnitTestCase` without redundant imports, preventing syntax lint warnings.

### Chore (ci)
- Document Composer and npm bootstrap order to support cached installs in CI.

### Test
- Align Brain Monkey tests with corrected fixtures to keep unit coverage compiling cleanly.

### [0.1.2](https://github.com/myspc-development/artpulse-management/compare/v0.1.1...v0.1.2) (2025-06-17)

### 0.1.1 (2025-06-17)

# Changelog

## v0.1.1 – 2025-06-16
### Added
- Salient‐style single & archive templates for all CPTs.
- Directory meta fields (date, location, bio, etc.).
- User dashboard shortcode & REST routes.
- PHPUnit scaffold & CI workflow.
- Release packaging & documentation manager.

## v0.1.0 – 2025-05-xx
- Initial plugin scaffolding: CPTs, meta-boxes, capabilities, settings page, membership & access control, dashboard, etc.
