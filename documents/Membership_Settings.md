# Membership Settings

The **Membership** tab under **ArtPulse Settings** controls fees and payment integrations for paid memberships. Open **ArtPulse Settings** in the admin menu and select the **Membership** tab. You can also navigate directly to `/wp-admin/admin.php?page=artpulse-settings&tab=membership`. Older slugs such as `ead-membership-settings` are not registered and will result in a 404.

## Fields

- **Basic Member Fee ($)** – Amount charged when a user selects the Basic level.
- **Pro Artist Fee ($)** – Cost for the Pro Artist membership level.
- **Organization Fee ($)** – Cost for Organization memberships.
- **Enable Stripe Integration** – When checked, members will be redirected to the mock Stripe checkout if a fee is required.
- **Enable WooCommerce Integration** – Toggle use of WooCommerce for payments.
- **Email Notification on Fee Change** – Send an email to the admin address whenever any fee setting changes.

When exported, these options are provided as a JSON file via the **Export Membership Fees** button at the bottom of the page.
