# ArtPulse Mobile API

Base path: `/wp-json/artpulse/v1/mobile`

All endpoints (including `POST /login`) must be invoked over HTTPS. Requests served over plain HTTP receive `403 ap_tls_required` unless the `artpulse_mobile_allow_insecure` filter is explicitly enabled for local development. CORS is restricted to configured mobile origins and the universal link domain — see [CORS & security](#cors--security).

## Authentication

### `POST /login`

Request body (JSON or form encoded):

```json
{
  "username": "user@example.com",
  "password": "secret",
  "push_token": "optional-device-token",
  "device_id": "optional-device-id",
  "device_name": "optional-human-label",
  "platform": "ios|android|etc",
  "app_version": "2.3.4"
}
```

Returns an access token (`token`), expiry (`expires`), refresh token (`refreshToken`), refresh expiry (`refreshExpires`) and the authenticated user profile. When device metadata is supplied it is stored on the associated refresh session and surfaced via the sessions endpoint. Each user may hold at most **10 active sessions**; when the cap is reached the oldest session is revoked during login.

### `POST /auth/refresh`

Accepts a `refresh_token`, rotates it and issues a new access token. Refresh validation honours ±120 seconds of client clock skew for `nbf` and `exp` checks. Reuse or expired tokens return `401 ap_refresh_expired`/`refresh_reuse`.

### `GET /me`

Requires `Authorization: Bearer <token>` header. Returns the authenticated user's profile, including stored push tokens per device and muted notification topics.

### `POST /me`

Allows clients to update the current device push token and mute notification topics. Body parameters:

- `push_token` – optional, stored against the calling device.
- `mute_topics` – optional array of topics (e.g. `starting_soon`, `new_followed_event`).

The response mirrors `GET /me`.

## Session management

### `GET /auth/sessions`

Lists active sessions grouped by device. Each entry includes `deviceId`, `deviceName`, `platform`, `appVersion`, timestamps (`createdAt`, `lastUsedAt`, `lastSeenAt`), `lastIp`, `pushToken` and `tokenCount`.

### `DELETE /auth/sessions/{device_id}`

Revokes all refresh tokens associated with the device.

## Events

### `GET /events`

Query params:

- `lat` and `lng` (required) – decimal degrees for the lookup.
- `radius` (optional, default 50 km) – search radius in kilometres.
- `limit` (optional, default 25, max 100) – number of events to return.

Results are ordered by distance (ascending) and then by start time. Each event includes the best available image URL, like/save counts, and the caller's interaction state.

### `POST /events/{id}/like`
### `DELETE /events/{id}/like`

Marks an event as liked/unliked. Responses include the updated counts and interaction flags. Duplicate likes are ignored (idempotent). When `ap_enable_mobile_write_routes` is `0` (or the `artpulse_mobile_write_routes_enabled` filter returns `false`) these routes respond with `503 ap_mobile_read_only`.

### `POST /events/{id}/save`
### `DELETE /events/{id}/save`

Persist or remove a save for the event. Responses mirror the like endpoints and are also governed by the write-route toggle.

## Following and Feed

### `POST /follow/{type}/{id}`
### `DELETE /follow/{type}/{id}`

`type` must be `artist` or `org`. Responses include the updated follower count and whether the caller is following. Disabled when `ap_enable_mobile_write_routes=0`.

### `GET /feed`

Returns upcoming events connected to the organisations and artists the user follows. When no matches are found the feed falls back to upcoming public events.

## Notifications

A cron hook (`artpulse/mobile/notifs_tick`) batches mobile notifications in log-only mode. Current topics:

- `new_followed_event` – newly published events by followed organisations or artists.
- `starting_soon` – saved events starting within the next two hours.

Notifications honour user-configured mutes from `/me` and the `artpulse_mobile_notifications_muted_topics` filter. Logged deliveries trigger the `artpulse/mobile/notification_logged` action for instrumentation.

## CORS & security

Allowed origins are supplied via the `artpulse_mobile_allowed_origins` filter and the `artpulse_mobile_universal_link_domain` filter (automatically converted to `https://<domain>`). Wildcards are ignored; only HTTPS origins are accepted. Requests from disallowed origins receive no `Access-Control-Allow-Origin` header.

All write endpoints are rate-limited per user and IP. If a limit is exceeded the API responds with HTTP `429 ap_rate_limited` and includes `Retry-After`. Structured logs include the route, user ID, IP and retry delay.

## Metrics & CLI

Per-route request metrics are captured for the mobile namespace, maintaining latency percentiles (p50/p95) and status buckets. Recent samples can be inspected with:

```bash
wp artpulse metrics dump --last=15m
```

## Operational runbook

- **Write route toggle** – set the `ap_enable_mobile_write_routes` option (or define `AP_ENABLE_MOBILE_WRITE_ROUTES`) to `0` to immediately disable like/follow/save mutations.
- **JWT key rotation** – use the admin tooling to mark a key as `Retiring` for at least one week before invoking `JWT::retire_key()`. Do not remove the key from storage until the retirement window has elapsed to maintain token validity.
- **Error codes** – API error identifiers (e.g. `ap_invalid_credentials`, `ap_missing_token`, `ap_tls_required`, `ap_mobile_read_only`) are stable; add new codes rather than mutating existing ones.

## Data backfill

CLI helpers:

```bash
wp artpulse backfill-event-geo
```

Rebuilds the `ap_event_geo` mirror table from stored event coordinates.
