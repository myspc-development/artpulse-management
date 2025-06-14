# Development Plan: Core Module

## Overview
The Core module contains the foundational logic and utilities required across all other modules. It handles plugin lifecycle management, configuration loading, shared helpers, and infrastructure scaffolding.

## Features
- Plugin initialization and teardown (activation/deactivation)
- Global settings and config loader
- Common utility functions (logging, validation, data helpers)
- Hook and filter registration for all modules

## Planned Enhancements
- Modular bootstrap system with dependency resolution
- Dynamic module loading based on configuration
- Centralized logging/debugging tool (admin-visible logs)
- Multisite-aware setup routines and isolated configurations
- Error handling/reporting system with admin alerts
- Developer mode with verbose logging, testing tools
- Core CLI commands for admin tasks (e.g., cleanup, sync, diagnostics)
- Version-aware upgrade system with rollback capability

## Technical Considerations
- Use plugin activation/deactivation hooks with error catching
- Register settings with WordPress Settings API or custom admin UI
- Define a clear module registration API (e.g., `register_module()` with metadata)
- Use class autoloading and namespaces for maintainability
- Bootstrap critical services (e.g., auth, routing, i18n) before module init
- Support external APIs configuration (e.g., Google Maps, Places, GeoNames)

## UI/UX Plans
- General settings page for site-wide configuration
- Admin notices and banners for system errors, upgrades, debug alerts
- Toggle for developer mode and debugging tools
- Module status and dependency check dashboard (optional)
- Settings tabs UI: API Keys (Maps, Places), defaults (event duration, geolocation), debug mode toggle

## Testing Strategy
- Activation/deactivation edge case testing
- Unit tests for core helpers and configuration parsers
- Module dependency and load-order validation
- Regression testing after core updates or WordPress version changes
- Compatibility tests for multisite, CLI, and REST interfaces
