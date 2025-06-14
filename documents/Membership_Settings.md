# Membership Settings

The **Membership** tab under **ArtPulse Settings** controls fees and payment integrations for paid memberships. Open **ArtPulse Settings** in the admin menu and select the **Membership** tab. You can also navigate directly to `/wp-admin/admin.php?page=artpulse-settings&tab=membership`. Legacy slugs such as `ead-membership-settings` and `ead-membership-overview` now redirect to the correct page. Stripe API keys and the test mode option previously lived under **Payment Integration** but are now located here.

## Fields

- **Basic Member Fee ($)** – Amount charged when a user selects the Basic level.
- **Pro Artist Fee ($)** – Cost for the Pro Artist membership level.
- **Organization Fee ($)** – Cost for Organization memberships.
- **Currency** – ISO code used when charging membership fees.
- **Enable Stripe Integration** – When checked, members will be redirected to the mock Stripe checkout if a fee is required.
- **Stripe Publishable Key** – Your Stripe API publishable key.
- **Stripe Secret Key** – Your Stripe API secret key.
- **Stripe Test Mode** – When enabled, Stripe operates in test mode.
- **Enable WooCommerce Integration** – Toggle use of WooCommerce for payments.
- **Email Notification on Fee Change** – Send an email to the admin address whenever any fee setting changes.

When exported, these options are provided as a JSON file via the **Export Membership Fees** button at the bottom of the page.
