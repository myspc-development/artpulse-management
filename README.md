# ArtPulse Management Plugin

A feature-rich WordPress plugin for managing events, organizations, artists, and artworks.
Version **3.7.3** compatible with **WordPress 6.7.2+**.

## Features

- Directories for Events, Organizations, Artists and Artworks
- Organizer & artist dashboards with event management tools
- Featured listings with bulk admin actions
- Reviews system with moderation tools
- Favorites list shortcode
- CSV Import/Export tools
- Field Mapping settings in the CSV Import/Export page with reusable mapping presets
- Mapping confirmation step during CSV imports and option to save presets
- Data export page for CSV downloads
- Pending Events moderation with approve and reject actions
- Roles manager for custom capabilities
- Flexible shortcodes for registration forms, listings and dashboards
- Comprehensive REST API
- User search API for autocomplete fields
- Mobile routes served via REST endpoints
- Automated geocoding of addresses
- Automatic portfolio mirroring for published content
- Approved artist profiles display uploaded gallery images
- Listing analytics for views and clicks with CSV export
- Social auto-posting to Facebook, Instagram, Twitter and Pinterest
- WooCommerce payment option for featured listings
- Offline sync via the `/sync` endpoint

Introduced in version **3.7.0** – see [CHANGELOG.md](CHANGELOG.md) for details.

## Installation

1. Download the plugin .zip or clone this repository into your WordPress `wp-content/plugins` directory.
2. Activate the plugin via the WordPress admin panel.
3. Upon activation the plugin registers custom roles and capabilities.
4. Navigate to **ArtPulse Management** in the admin sidebar.
5. When deactivated these roles are removed automatically.

## Onboarding

Newcomers can follow the [Onboarding Guide](documents/Onboarding_Guide.md) for account setup and first steps.

## Usage

- Access features through the WordPress Admin Menu:
  - **ArtPulse Settings › Membership** — set fees and payment options.
  - **ArtPulse Settings › CSV Import/Export** — manage bulk data with a progress bar.
    After uploading a file you will preview the columns and confirm the field mapping.
    During the import a progress indicator shows completion and the raw log can be downloaded when finished.
  - **Pending Events** — approve or reject events.
  - **Moderate Reviews** — moderate reviews submitted by users.
  - **Bookings** — manage bookings submitted via the REST API.
  - **ArtPulse Settings** — plugin settings. See [Settings Tabs](documents/Settings_Tabs.md) for a breakdown of each section.
  - **Notifications** — toggle options like the new event submission email.

### RSVP Form

Single event pages include an AJAX powered RSVP form. When the plugin is
activated it creates an `ead_rsvps` table to store submissions. Copy the
`single-ead_event.php` template to your theme (or use the bundled one) to
display the form. The request is sent to the `ead_event_rsvp` AJAX action and
expects the following fields:

```
POST wp-admin/admin-ajax.php
action=ead_event_rsvp
email=user@example.com
event_id=123
ead_event_rsvp_nonce=... 
```

Users with the `ead_manage_rsvps` capability can view RSVPs from their dashboard.

## Development

This plugin uses:

- PHP 8.2+
- WordPress 6.7.2+
- Modern PSR-4 autoloading.

### Running Setup

Install Composer and Node dependencies then compile the plugin assets:

```bash
composer install
npm install
npm run build
```

### Building Assets

Use npm to bundle the JavaScript files:

```bash
npm run build
```

The source files are located in `assets/js` and `assets/css`. Running the build
command bundles the JavaScript from `assets/js/user-dashboard/main.js` into
`build/user-dashboard.bundle.js` using Webpack.

### Running Tests

Execute PHPUnit to run the unit test suite:

```bash
phpunit
```

During development you can run `npm run watch` to continuously build assets as files change.


## Shortcodes

| Shortcode | Attributes | Example |
|-----------|------------|---------|
| `[ap_artist_registration_form]` | – | `[ap_artist_registration_form]` |
| `[ead_reviews_form]` | – | `[ead_reviews_form]` |
| `[ead_reviews_table]` | – | `[ead_reviews_table]` |
| `[ead_org_review_form]` | `org_id` | `[ead_org_review_form org_id="123"]` |
| `[ead_organization_registration_form]` | – | `[ead_organization_registration_form]` |
| `[ead_events_list]` | – | `[ead_events_list]` |
| `[ead_edit_event_form]` | – | `[ead_edit_event_form]` |
| `[ead_favorites]` | – | `[ead_favorites]` |
| `[ead_organization_list]` | – | `[ead_organization_list]` |
| `[ead_artwork_submission_form]` | `id` | `[ead_artwork_submission_form id="10"]` |
| `[ead_submit_event_form]` | – | `[ead_submit_event_form]` |
| `[ead_organization_form]` | – | `[ead_organization_form]` |
| `[ead_organizer_dashboard]` | – | `[ead_organizer_dashboard]` |
| `[ead_artist_dashboard]` | – | `[ead_artist_dashboard]` |
| `[ead_organization_dashboard]` | – | `[ead_organization_dashboard]` |
| `[ead_user_dashboard]` | – | `[ead_user_dashboard]` |
| `[ead_membership_status]` | – | `[ead_membership_status]` |
Use `[ead_organization_registration_form]` to display a front-end organization registration form. The form submits via JavaScript to the `artpulse/v1/organizations` endpoint.
Place `[ead_membership_status]` on a page to let logged-in users choose or update their membership level.

### Organization Dashboard

The dashboard provides an at-a-glance summary of organization activity. Widgets display totals for published, pending and draft events, featured events, upcoming and expired events, artworks, pending reviews, total RSVPs and bookings.
### Creating Browsable Directories

Use `[ead_organization_list]` or `[ead_events_list]` to embed directories with search and filtering on any page. Copy the `archive-ead_*` templates from the `templates/` folder to your theme for custom layouts. Map integration and page builder blocks are also available. See [Directory Options](documents/Directory_Options.md) for a full overview.


## REST API

Below is a high-level view of the custom REST endpoints. All routes are prefixed with `/artpulse/v1/`.

| Route | Methods | Authentication |
|-------|---------|---------------|
| `/dashboard` | `GET` | logged-in users |
| `/artists/dashboard` | `GET` `POST` | logged-in users (extra capabilities for actions) |
| `/organizations/dashboard` | `GET` | users with `view_dashboard` |
| `/reviews` | `GET` public, `POST` logged-in users |
| `/organizations` | `POST` logged-in users |
| `/artists` | `POST` public with nonce |
| `/bookings` | `GET` `POST` logged-in users |
| `/comments` | `GET` users with `read` |
| `/comments/moderate` | `POST` users with `moderate_comments` |
| `/events` | `GET` public, `POST/PUT/DELETE` users with `edit_posts` |
| `/events/submit` | `POST` logged-in users |
| `/likes` | `POST` logged-in users |
| `/moderation` | `POST` logged-in users |
| `/notifications` | `GET` logged-in users |
| `/settings/notification` | `GET` `POST` admins |
| `/settings` | `GET` `POST` admins |
| `/taxonomies` | `GET` users with `read` |
| `/taxonomies/{taxonomy}` | `GET` users with `read` |
| `/user/profile` | `GET` `POST` logged-in users |
| `/user-profile` | `GET` `POST` pro members (`POST` also org) |
| `/user-badges` | `GET` pro members |
| `/membership-status` | `GET` pro members |
| `/artworks` | `GET` users with `read`, `POST` users with `edit_posts` |
| `/artwork/{id}` | `GET` users with `read`, `PUT/DELETE` author or editors |
| `/auth/token` | `POST` | credentials |

### Bookings Endpoint

The `/bookings` REST route lets authenticated users retrieve and create
`ead_booking` posts. The following capabilities are checked:

- `ead_view_bookings` for reading bookings.
- `ead_create_bookings` for creating a booking.

Example request to list current user bookings:

```bash
curl -H "X-WP-Nonce: <nonce>" \
  https://example.com/wp-json/artpulse/v1/bookings
```

Example request to create a booking:


```bash
curl -X POST -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <nonce>" \
  -d '{"title":"Studio visit","date":"2024-07-25","booking_details":"2 seats"}' \
  https://example.com/wp-json/artpulse/v1/bookings
```

### Artists Endpoint

The `/artists` route registers a new WordPress user and pending `ead_artist` post.
Authentication is not required, but a valid REST nonce must be provided in the
`X-WP-Nonce` header.

Example request:

```bash
curl -X POST -H "X-WP-Nonce: <nonce>" \
  -F "artist_username=newartist" \
  -F "registration_email=user@example.com" \
  https://example.com/wp-json/artpulse/v1/artists
```

The `ap_artist_registration_form` shortcode now submits to this endpoint via AJAX.

### Obtaining Social Auto-Posting Tokens

To enable auto-posting to social networks you must create access tokens in each service's developer portal:

- **Facebook** — create an app in the Facebook Developer dashboard and generate a user access token.
- **Instagram** — use the Meta developer portal to create an Instagram Basic Display token.
- **Twitter** — generate an API key or OAuth token from your Twitter developer account.
- **Pinterest** — create an app on Pinterest and obtain the access token from the developer console.

### Configuring Email Providers

From the **ArtPulse Settings** page, open the **Email Providers** tab to select the default service used for outgoing emails. Supported providers include **SendGrid** and **Mailgun**. Enter the corresponding API keys and domain settings to enable delivery through these platforms.

### Mobile Authentication

Use the `/artpulse/v1/auth/token` route to obtain a JWT for the mobile app. Send a `POST` request with `username` and `password` parameters:

```bash
curl -X POST \
  -d "username=user" -d "password=pass" \
  https://example.com/wp-json/artpulse/v1/auth/token
```

The response includes a `token` which remains valid for one week. Pass this value in the `Authorization: Bearer <token>` header when calling other endpoints.

## Mobile App Development

See the [Mobile App Development Plan](documents/mobile-app-dev-plan) for a detailed schedule. According to [dev-plan.md](dev-plan.md), **Phase&nbsp;4** begins the mobile companion app with requirements gathering, API refinements, and UX/UI planning. The dedicated plan expands this work into seven phases:

1. **Planning** – requirements, user stories, API audit.
2. **UI/UX** – wireframes and prototypes.
3. **Core Development** – authentication, event directory, profiles, and push notifications.
4. **QA & Testing** – automated and manual tests.
5. **Beta Release** – TestFlight and Google Play beta programs.
6. **Public Launch** – app store submissions and marketing.
7. **Support & Maintenance** – ongoing fixes and feature updates.

### Mobile Endpoints & Features

- REST routes under `/artpulse/v1/` provide access to events, organizations, bookings, comments, and settings. See the table above for details.
- JWT authentication will secure requests via `/artpulse/v1/auth/token` once implemented.
- Notification preferences are managed through the `/settings/notification` endpoint and will power push alerts for new events and approvals.
- Use `/sync` with a `since` timestamp to fetch recently modified listings and keep local records up to date. See the [Offline Access Guide](documents/Offline_Access.md) for details.
- Cache API responses locally: request data from any `/artpulse/v1` route, save the JSON payload, then read from storage when offline. Periodically call `/sync` to refresh changes.

## Help Guides

- [Member Help](documents/Member_Help.md)
- [Membership Settings](documents/Membership_Settings.md)
- [Directory Options](documents/Directory_Options.md)
- [Admin Help](documents/Admin_Help.md)
- [Settings Tabs](documents/Settings_Tabs.md)
