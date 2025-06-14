# Creating Browsable Directories

ArtPulse offers multiple ways to display searchable lists of events, organizations, artists and artworks. These directories can be embedded on any page or customized in your theme.

## Shortcodes

Use the built-in shortcodes to quickly add a directory:

- `[ead_organization_list]` – shows organizations with search fields for city, day and social networks.
- `[ead_events_list]` – lists events and supports filtering by type and date range via a REST powered interface.
- `[ead_organization_dashboard]`, `[ead_artist_dashboard]` – dashboards include links to manage listings and view directory pages.

## Archive Templates

Template files in the `templates/` folder include `archive-ead_event.php`, `archive-ead_artwork.php`, and others. Copy these into your theme to adjust the layout, number of posts per page or the look of directory grids.

## Map & Filtering

The organization directory outputs coordinates that can feed JavaScript maps. Combine it with `templates/EadMap.php` or your own code to display markers and advanced location searches.

## REST API

Endpoints under `/artpulse/v1/` expose all core post types. Custom applications or advanced front-end code can query these endpoints to build fully custom directories.

## Page Builder Blocks

WPBakery integration registers blocks like "ArtPulse Organization Directory" so you can insert directories via a drag-and-drop editor with the same options as the shortcodes.
