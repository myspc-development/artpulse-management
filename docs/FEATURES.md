🎨 ArtPulse Management Plugin — Feature Overview
Empowering artists, organizations, and admins to manage memberships, content, and analytics through a modern WordPress plugin.

📊 Role-Based Feature Matrix
Feature	Admin	Artist	Org	Developer
Membership Management	✅	✅	✅	⚙️
Favorites & Follows	✅	✅	✅	✅
Notifications System	✅	✅	✅	✅
REST API Access	✅	✅	✅	✅
Submission Forms	✅	✅	✅	✅
Custom Post Types (Events, etc)	✅	✅	✅	✅
Dashboards (User & Org)	✅	✅	✅	⚙️
Stripe Billing Integration	✅	✅	✅	⚙️
WooCommerce Checkout Shortcodes	✅	✅	✅	✅
Directory Listings	✅	✅	✅	✅
Admin Panels	✅	❌	❌	⚙️
Testing & CI Support	⚙️	❌	❌	✅

🔐 Membership Management
Handles role-based access (Free, Pro, Org)

Expiry checks via cron

Stripe webhook syncing

Shortcode: [ap_membership_account]

📘 Example:

php
Copy
Edit
[ap_membership_account]
❤️ Favorites & Follows
REST-based favorite system

Stored in user meta

Shortcode: [ap_my_follows]

📘 Example:

php
Copy
Edit
[ap_my_follows]
🔔 Notifications System
REST-powered alerts

Supports read/unread/delete

Shortcode: [ap_notifications]

📘 Example:

php
Copy
Edit
[ap_notifications]
🗃️ Directory Listings
REST-enhanced, cache-backed directories for artists and organizations.

Shortcodes:

- `[ap_artists_directory]` — renders published `artpulse_artist` profiles.
- `[ap_orgs_directory]` — renders published `artpulse_org` profiles.

📘 Examples:

```php
[ap_artists_directory per_page="36" letter="C"]
[ap_orgs_directory per_page="18" letter="all"]
```

Key behaviour:

- Friendly letter routes are registered for each directory. Out of the box the
  plugin expects pages at `/artists/letter/{letter}/` and `/galleries/letter/{letter}/`
  (e.g. `/artists/letter/B/` or `/galleries/letter/all/`). Query parameters such as
  `?s=sculpture` or `?tax[artist_specialty][]=ceramics` continue to work for search and
  taxonomy filtering when permalinks are disabled.
- Every render outputs a canonical `<link>` tag for the active letter URL,
  including any search or taxonomy query arguments.
- Responses are cached in a WordPress transient for six hours. The cache is
  automatically invalidated when related posts are saved, their taxonomy terms
  change, or associated metadata is updated.
- Letter metadata is lazily generated when directories are viewed. To warm
  caches in bulk, run:

  ```bash
  wp artpulse backfill-letters --post_type=artpulse_artist --batch=100
  wp artpulse backfill-letters --post_type=artpulse_org --batch=250
  ```

  The command loops until all published posts have cached letters and prints a
  success message with the number of processed posts.
📝 Submission Forms
REST endpoint for new content

Upload support

Shortcode: [ap_submission_form post_type="event"]

Event submission:

Shortcodes: [ap_submit_event], [ap_org_submit_event]

📘 Example:

php
Copy
Edit
[ap_submission_form post_type="artwork"]
👤 Role Dashboards
Membership info + REST widgets tailored to each role

Shortcodes: [ap_member_dashboard], [ap_artist_dashboard], [ap_organization_dashboard], [ap_member_upgrades_widget]

📘 Example:

php
Copy
Edit
[ap_member_dashboard class="dashboard-wrap"]

Use `[ap_member_upgrades_widget]` anywhere on a member page to surface the standalone upgrade call-to-action for the current user. The shortcode automatically checks the logged-in visitor, builds the intro/options via the dashboard helper, and renders the shared widget UI. Optional attributes include `title`, `section_title`, `widget_intro`, and `empty_message`.

📘 Example:

```php
[ap_member_upgrades_widget title="Membership Upgrades" section_title="Upgrade Your Access"]
```
ℹ️ Publish dedicated submission pages so the dashboard buttons know where to send creators:

- Artist profile creation: `[ap_submission_form post_type="artpulse_artist"]`
- Organization profile creation: `[ap_submission_form post_type="artpulse_org"]`

If those pages are missing, the dashboard links fall back to the WordPress editor at `post-new.php` for the relevant custom post type.
🏢 Organization Dashboard
Org stats, artist links, billing info

Shortcode: [ap_org_dashboard]

📘 Example:

php
Copy
Edit
[ap_org_dashboard]
💳 WooCommerce Integration
Stripe checkout integration

Shortcode: [ap_membership_purchase level="Pro"]

📘 Example:

php
Copy
Edit
[ap_membership_purchase level="Pro" class="purchase-btn"]
🧪 Testing & CI
PHPUnit coverage via Brain Monkey

GitHub Actions for CI

Linting via phpcs and composer scripts

🔜 Planned Improvements
Multi-tier pricing

Push notifications

Caching on favorites

Directory export tools