# ArtPulse Settings Page

This guide explains each section available on the **ArtPulse Settings** admin page.

## API Keys & Services

Configure third‑party service credentials used throughout the plugin.

- **Google Maps API Key** – Enables map displays and geocoding.
- **GeoNames Username** – Required for country and city lookups.
- **Google Places API Key** – Used for place autocompletion and details.

## Social Auto-Posting

Automatically publish new events or announcements to social networks. Enable each platform you wish to use and supply the access token generated in its developer portal.

- Facebook
- Instagram
- Twitter
- Pinterest

## Email Providers

Select which service handles outgoing email.

- **WordPress wp_mail** – Uses the host’s email configuration.
- **SendGrid** – Provide your SendGrid API key.
- **Mailgun** – Enter both the API key and domain registered with Mailgun.

## Field Mapping

Define how CSV columns map to post meta fields when importing data.
Provide JSON snippets for each post type:

- Events
- Organizations
- Artists
- Artworks

## Data Management

Controls for cached JSON data and AJAX limits.

- **Auto-update fallback JSON** – Maintain bundled location data.
- **Requests Per Window** and **Window Seconds** – Rate limit AJAX handlers.
- Clear fallback JSON files from this tab when necessary.

## CSV Import/Export

Import and export plugin data in CSV format.

- Download lists of events, organizations, artists and artworks.
- Upload CSV files to create or update posts in bulk.
- Confirm the column mapping before running an import and optionally save it as a preset.

## Email Templates

Edit the content used for automated emails. Each template has a textarea with **Preview** and **Save** buttons.

1. Enter your message using shortcodes like `[site_title]` or `[current_date]`.
2. Click **Preview** to see the rendered output.
3. Click **Save** to store the template.

## Payment Integration

Settings for WooCommerce and Stripe.

- **Featured Product ID** – WooCommerce product to purchase featured listings.
- **Featured Duration (days)** – How long a listing stays featured after payment.
- **Stripe Publishable Key** and **Stripe Secret Key** – Required if using Stripe directly.

## Location Data Files

The plugin ships with fallback JSON files inside the `data/` folder:

- `countries.json`
- `states.json`
- `cities.json`

These files populate the country, state and city select boxes used in registration forms and address metaboxes. If a requested state or city is not found locally, ArtPulse queries the GeoNames API using the username provided on the **API Keys & Services** tab. The response is cached as a transient and written back to the JSON file so future lookups work offline.

When a Google Maps key is entered you may also enable Google Places autocomplete. This enhances the address field, filling latitude and longitude and suggesting complete addresses.

Cached location files update automatically whenever new data is retrieved, provided **Auto-update fallback JSON** is enabled in the **Data Management** tab. You can manually clear these files from the same tab.

### Supplying API Keys

1. Go to **ArtPulse › Settings** in the WordPress admin.
2. Open the **API Keys & Services** tab.
3. Enter your **Google Maps API Key**, optional **Google Places API Key** and **GeoNames Username**.
4. Click **Save Changes**.
