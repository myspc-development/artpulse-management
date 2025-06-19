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
REST/JS-driven filterable directories

Post types: artist, event, artwork, organization

Shortcode: [ap_directory post_type="artist"]

📘 Example:

php
Copy
Edit
[ap_directory post_type="event" filter="tag" class="grid"]
📝 Submission Forms
REST endpoint for new content

Upload support

Shortcode: [ap_submission_form post_type="event"]

📘 Example:

php
Copy
Edit
[ap_submission_form post_type="artwork"]
👤 User Dashboard
Membership info + REST widgets

Shortcode: [ap_user_dashboard]

📘 Example:

php
Copy
Edit
[ap_user_dashboard class="dashboard-wrap"]
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