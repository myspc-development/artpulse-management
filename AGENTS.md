# ArtPulse Organizations Module

This folder contains the code for managing organizations inside the ArtPulse Management plugin. Key features implemented in `organizations.php` include:

- **Custom Post Type** for organizations with REST API support.
- **Meta fields** to store website, logo URL, mission statement, address, phone, admin users, and team members.
- **Meta boxes** for editing these fields in the WordPress admin.
- **Shortcodes** for displaying organization profiles, selecting organizations, and editing details from the frontend.
- **Permission checks** so only assigned admin users can edit their organization posts.

Use these components to integrate organization listings with custom templates or dashboards. See the PHP file for details on each hook and filter.
