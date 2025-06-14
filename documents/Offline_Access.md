# Offline Access Guide

Mobile clients can cache REST API responses for offline use. Fetch JSON from the plugin endpoints and store it locally using your preferred storage layer (e.g. AsyncStorage or SQLite).

1. Request data from any `artpulse/v1` route. Frequently accessed collections such as `/events` and `/artwork` now support transient caching server side, so repeated calls are inexpensive.
2. Save the JSON payload to a local database or file on the device.
3. When offline, read the stored data and render it in the UI.
4. Periodically call the new `/artpulse/v1/sync` endpoint with a `since` parameter containing the last modified timestamp you have cached. The response lists recently updated listings so you can refresh only those records.

This approach minimizes network usage and allows the mobile app to operate when connectivity is limited.
