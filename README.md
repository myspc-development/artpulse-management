# ArtPulse Management Plugin

**Version:** 1.1.5  
**Requires:** WordPress 6.8+, PHP 8.3+  
**Description:**  
A comprehensive management plugin for ArtPulse, providing membership handling, favorites, notifications, REST API endpoints, directory management, user dashboards, and integration with Stripe for subscriptions.

---

## Features

- Custom post types: Events, Artists, Artworks, Organizations  
- REST API endpoints for favorites, notifications, submissions, artists, and more  
- Membership management with Stripe webhook integration
- User dashboard with profile editing and content overview
- Organization dashboard for linked artists, event management, analytics, and billing history
- Directory filters with shortcodes and REST queries
- Notification system with read/unread status  
- Submission forms supporting image attachments and metadata  
- WooCommerce integration support (optional)  
- Robust testing suite with PHPUnit and Brain Monkey  
- CI/CD workflow configured for automated tests and coverage  

---

## Installation

1. Upload the `artpulse-management-plugin` folder to your `/wp-content/plugins/` directory.  
2. Activate the plugin via the WordPress Admin > Plugins page.  
3. Configure Stripe API keys and webhook secrets in the plugin settings page.  
4. (Optional) Setup WooCommerce if using e-commerce features.  

---

## Usage

### Shortcodes

- `[ap_directory type="event" limit="10"]` — Display a directory of events (types: event, artist, artwork, org).
- `[ap_user_dashboard]` — Show the logged-in user’s dashboard with membership and content overview.
- `[ap_my_follows]` — List items the current user follows. Output is a container populated via JavaScript.
- `[ap_membership_purchase level="Pro"]` — Render a purchase link to the WooCommerce checkout.
  - **level**: Membership tier to purchase (default `Pro`).
  - **class**: Optional CSS class for the link. Output is an `<a>` tag linking to checkout with the level query parameter.
- Other shortcodes include favorites list, notification display, and submission forms (refer to documentation).

### REST API Endpoints

- `POST /artpulse/v1/favorites` — Add a favorite  
- `DELETE /artpulse/v1/favorites` — Remove a favorite  
- `GET /artpulse/v1/notifications` — List user notifications  
- `POST /artpulse/v1/notifications/read` — Mark notification read  
- `POST /artpulse/v1/stripe-webhook` — Handle Stripe webhook events  
- And others for submissions, artists, directory filters (see REST API docs)

---

## Configuration

- Stripe settings: Set your Stripe secret key and webhook secret in plugin settings.  
- Membership Levels: Users start with "Free" and can upgrade via Stripe to "Pro".  
- Daily cron job for membership expiration checks (`ap_daily_expiry_check`) runs automatically.  

---

## Development setup

1. Install PHP and JavaScript dependencies:

```bash
composer install
npm install
```

2. Build the plugin assets:

```bash
npm run build
```

3. Run the test suite:

```bash
vendor/bin/phpunit --testdox
```

## Testing

### Local Setup

- Install WordPress test suite using the provided `bin/install-wp-tests.sh` script.  
- Configure database and WP test constants in `wp-tests-config.php`.  
- Run tests with:  

```bash
vendor/bin/phpunit --testdox
```

## Documentation

Admin and member guides should be placed in the `assets/docs/` directory:

- `assets/docs/Admin_Help.md` – Instructions for site administrators.
- `assets/docs/Member_Help.md` – Instructions for regular members.

Customize these files to fit your deployment.
