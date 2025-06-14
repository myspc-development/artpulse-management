# Membership Development Plan

## Current Functionality

The plugin offers a **Membership** tab in the admin area. Administrators set pricing for Basic, Pro Artist and Organization levels and can toggle payment integrations. A simplified **ArtPulse Settings** page (`/wp-admin/admin.php?page=artpulse-settings`) now exposes core membership options. See [Membership Settings](Membership_Settings.md) for field descriptions. Key options include:

- **Basic Member Fee**, **Pro Artist Fee** and **Organization Fee**
- **Pro Plan Price** and **Org Plan Price** on the ArtPulse Settings page
- **Membership Duration (days)** and **Upgrade Policy** (auto or manual approval)
- **Enable Stripe Integration** and **Enable WooCommerce Integration**
- **Email Notification on Fee Change**
- Exporting all fee settings as a JSON file

Members update their level via the `[ead_membership_status]` shortcode. Roles grant different capabilities for artists and organizations.

## Planned Features

Based on [dev-plan.md](../dev-plan.md) and the [Monetization Plan](monetization-plan.md):

### Q3 2025
- **Payment Integration MVP** with refined checkout flows using **Stripe**, **PayPal** and WooCommerce.
- Introduce tiered roles: **Standard** and **Premium** for individuals, **Basic Org** and **Pro Org** for organizations.
- Begin work on a digital storefront add-on and artist sales dashboard.

### Q4 2025
- Release the storefront with cart and commission handling.
- Provide advanced analytics dashboards for Pro Org users.
- Add CAPTCHA and rate limiting to payment forms.

### Q1 2026
- Offer custom branding options and sponsored content manager.
- Integrate CRM connections for lead generation.

### Q2 2026
- Finalize mobile API updates for membership data.
- Review pricing models and collect feedback on upgrade paths.

This roadmap will evolve as membership features expand alongside the core plugin.
