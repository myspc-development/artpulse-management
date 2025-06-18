# Features of ArtPulse Management Plugin

## Membership Management
- Assign Free and Pro membership levels.
- Automatic membership expiry processing via daily cron.
- Stripe webhook integration for subscription lifecycle events.
- User roles and capabilities tailored for membership tiers.

## Favorites System
- Add and remove favorites via REST API.
- Store favorites in user meta.
- Shortcode and JavaScript for frontend favorites interaction.

## Notifications
- REST API to list, mark read/unread, and delete notifications.
- Notifications stored in user meta.
- User dashboard integration for notification display.

## Directory Management
- Directory shortcode with REST API filtering by type, limit, and more.
- Supports events, artists, artworks, and organizations.
- Metadata retrieval for each post type.
- Enqueued scripts and styles for frontend interactivity.

## Submission Forms
- REST API endpoint to handle submissions for multiple post types.
- Supports image attachments with validation.
- Metadata handling for different submission types.

## User Dashboard
- Shortcode to display membership info and user content.
- REST API endpoints for dashboard data retrieval and profile updates.
- Enqueued scripts for dynamic dashboard UI.

## WooCommerce Integration
- Conditional loading of WooCommerce features.
- Shortcodes and hooks prepared for e-commerce membership sales.

## Testing & CI
- PHPUnit test suite with Brain Monkey for mocking.
- Code coverage reporting.
- GitHub Actions workflow for continuous integration.

---

# Planned Improvements
- Expanded membership tiers and capabilities.
- Advanced notification types and push notifications.
- Enhanced directory filtering and sorting.
- Bulk favorites management and caching.
- User activity logs and extended profile editing.
- Comprehensive API documentation and example snippets.
