# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

## Unreleased

### Fix
- Resolve PHP syntax issues in the organisation dashboard admin, community shortcodes, and REST portfolio controller to ensure clean bootstrap and hardened output sanitisation.

### Chore (ci)
- Harden the continuous integration workflow with caching, early syntax linting, and test artefact uploads to keep the matrix reliable.

### Test
- Repair shortcode unit tests and supporting stubs so Brain Monkey coverage runs without fatal errors.

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
