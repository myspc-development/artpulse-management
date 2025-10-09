# ArtPulse Mobile API

Base path: `/wp-json/artpulse/v1/mobile`

All endpoints (including `POST /login`) must be invoked over HTTPS. Requests served over plain HTTP receive `403 ap_tls_required`
 unless the `artpulse_mobile_allow_insecure` filter is explicitly enabled for local development. CORS is restricted to configured
 mobile origins and the universal link domain — see [Transport, CORS & security](#transport-cors--security).

## Transport, CORS & security

- **TLS enforcement** – plaintext requests are rejected with `ap_tls_required`. Use the `artpulse_mobile_allow_insecure` filter
  for local-only overrides.
- **Allowed origins** – origins are sourced from the `artpulse_mobile_allowed_origins` filter, the
  `artpulse_mobile_universal_link_domain` filter (converted to `https://<domain>`) and the newline-delimited "Approved origins"
  setting in the admin UI. Only exact HTTPS origins are honoured; wildcards are ignored. Disallowed origins receive a formatted
  `403 cors_forbidden` error and no `Access-Control-Allow-Origin` header.
- **Response headers** – successful CORS handshakes emit:

  ```text
  Access-Control-Allow-Origin: https://mobile.example.com
  Access-Control-Allow-Credentials: true
  Vary: Origin
  ```

  Preflight (`OPTIONS`) requests additionally return:

  ```text
  Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
  Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-Device-Id, X-Forwarded-For
  Access-Control-Max-Age: 600
  ```

  Extend the header whitelist via the `artpulse_mobile_allowed_headers` filter. All CORS headers are emitted before the REST
  response body is streamed.
- **Rate limiting** – write endpoints are throttled per user and IP. When limits are exceeded the API responds with HTTP `429`,
  the `ap_rate_limited` error code and a `Retry-After` header.

Structured request logs capture the route, user ID, IP address, retry delay and CORS decisions for operational monitoring.

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

Returns an access token (`token`), expiry (`expires`), refresh token (`refreshToken`), refresh expiry (`refreshExpires`) and the
 authenticated user profile. Device metadata is stored on the refresh session and surfaced via [`GET /auth/sessions`](#get-authsessions).

Example response:

```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires": 1700006400,
  "refreshToken": "9c8b5c2c-6f94-4b20.a24d630c9d1c.eXZlcnktc2VjcmV0",
  "refreshExpires": 1702598400,
  "user": {
    "id": 42,
    "displayName": "Ada Lovelace",
    "email": "user@example.com",
    "roles": ["subscriber"],
    "pushTokens": [
      {"deviceId": "ios-ada", "token": "apns-token", "updatedAt": 1699992800}
    ],
    "mutedTopics": []
  },
  "session": {
    "device_id": "ios-ada",
    "device_name": "Ada’s iPhone",
    "platform": "ios",
    "app_version": "2.3.4",
    "last_ip": "203.0.113.10",
    "last_seen_at": 1699992800
  },
  "device_id": "ios-ada",
  "device_name": "Ada’s iPhone",
  "platform": "ios",
  "app_version": "2.3.4",
  "last_ip": "203.0.113.10",
  "last_seen_at": 1699992800,
  "evicted_device_id": null
}
```

### `POST /auth/refresh`

Accepts `refresh_token`, rotates it and issues a new access token. The response mirrors `POST /login` and includes
`evicted_device_id` when the session cap is enforced. Refresh validation honours ±120 seconds of client clock skew for `nbf` and
`exp`. Reuse or expired tokens return `401` with codes `ap_refresh_expired` (`AUTH_EXPIRED`) or `refresh_reuse` (`REFRESH_REUSE`).

### `GET /me`

Requires `Authorization: Bearer <token>`. Returns the authenticated user's profile, including stored push tokens per device and
muted notification topics.

### `POST /me`

Allows clients to update the current device push token and mute notification topics. Body parameters:

- `push_token` – optional, stored against the calling device.
- `mute_topics` – optional array of topics (e.g. `starting_soon`, `new_followed_event`).

The response mirrors `GET /me`.

## Session management

### `GET /auth/sessions`

Lists active sessions grouped by device in reverse-last-used order. Each entry includes:

- `deviceId` / `device_id` – canonical device identifier (snake_case retained for backwards compatibility).
- `deviceName` / `device_name`, `platform`, `appVersion` / `app_version` – latest metadata provided during login or refresh.
- `createdAt`, `lastUsedAt`, `lastSeenAt`, `expiresAt` – UNIX timestamps.
- `lastIp`, `pushToken`, `tokenCount` – network fingerprint, push registration and number of active refresh tokens.

Example response:

```json
{
  "sessions": [
    {
      "deviceId": "ios-ada",
      "deviceName": "Ada’s iPhone",
      "platform": "ios",
      "appVersion": "2.3.4",
      "createdAt": 1697308800,
      "lastUsedAt": 1699992800,
      "lastSeenAt": 1699992800,
      "expiresAt": 1702598400,
      "lastIp": "203.0.113.10",
      "pushToken": "apns-token",
      "tokenCount": 1,
      "device_id": "ios-ada",
      "device_name": "Ada’s iPhone",
      "app_version": "2.3.4",
      "last_ip": "203.0.113.10",
      "last_seen_at": 1699992800
    }
  ]
}
```

### `DELETE /auth/sessions/{device_id}`

Revokes all refresh tokens associated with the device. Clients should re-authenticate on that device once revoked.

### Session eviction & purging

- Users may hold at most **10 active sessions**. When login or refresh would exceed this cap the least recently used active
  session is revoked, and the response includes `evicted_device_id` so clients can notify displaced users.
- Inactive sessions are purged automatically after 90 days of inactivity and can be trimmed manually via
  `wp artpulse mobile purge --sessions`.
- Password resets, email changes and profile updates revoke all refresh tokens and emit `mobile_sessions_revoked` audit logs.

## Events

### `GET /events`

Query parameters:

- `lat` and `lng` (required) – decimal degrees for the lookup.
- `radius` (optional, default 50 km) – search radius in kilometres.
- `limit` (optional, default 25, max 100) – number of events to return.
- `cursor` (optional) – opaque pagination token returned as `next_cursor`.

Results are ordered by distance (ascending) and then by start time. Each event includes the best available image URL, like/save
counts, the caller's interaction state and timezone metadata. Event `start`/`end` values are returned as ISO 8601 timestamps with
offsets. Responses include the `server_tz` and `server_tz_offset_minutes` context at both the item and top level.

Example response:

```json
{
  "items": [
    {
      "id": 101,
      "title": "Sculpture Garden Late Night",
      "excerpt": "Join us for an evening of light installations...",
      "start": "2023-11-14T19:00:00-05:00",
      "end": "2023-11-14T22:00:00-05:00",
      "location": "Gallery Plaza",
      "distanceKm": 1.24,
      "distance_m": 1240,
      "isOngoing": false,
      "likes": 12,
      "liked": true,
      "saves": 7,
      "saved": true,
      "image": "https://cdn.example.com/images/101.jpg",
      "organization": {
        "id": 9,
        "title": "City Arts Collective"
      },
      "server_tz": "America/New_York",
      "server_tz_offset_minutes": -300
    }
  ],
  "next_cursor": "eyIwIjoyMDIzLTExLTE0VDE5OjAwOjAwLTA1OjAwIiwiMSI6MS4yNCwiMiI6MTAxLCIzIjoxfQ",
  "has_more": true,
  "server_tz": "America/New_York",
  "server_tz_offset_minutes": -300
}
```

### `POST /events/{id}/like`
### `DELETE /events/{id}/like`

Marks an event as liked/unliked. Responses include the updated counts and interaction flags. Duplicate likes are ignored
(idempotent). When `ap_enable_mobile_write_routes` is `0` (or the `artpulse_mobile_write_routes_enabled` filter returns `false`)
these routes respond with `503 ap_mobile_read_only`.

### `POST /events/{id}/save`
### `DELETE /events/{id}/save`

Persist or remove a save for the event. Responses mirror the like endpoints and are also governed by the write-route toggle.

## Following and Feed

### `POST /follow/{type}/{id}`
### `DELETE /follow/{type}/{id}`

`type` must be `artist` or `org`. Responses include the updated follower count and whether the caller is following. Disabled when
`ap_enable_mobile_write_routes=0`.

### `GET /feed`

Returns upcoming events connected to the organisations and artists the user follows. When no matches are found the feed falls
back to upcoming public events. Feed responses share the same cursor pagination contract, event structure and timezone metadata as
[`GET /events`](#get-events).

## Notifications

A cron hook (`artpulse/mobile/notifs_tick`) batches mobile notifications in log-only mode. Current topics:

- `new_followed_event` – newly published events by followed organisations or artists.
- `starting_soon` – saved events starting within the next two hours.

Notifications honour user-configured mutes from `/me` and the `artpulse_mobile_notifications_muted_topics` filter. Logged
 deliveries trigger the `artpulse/mobile/notification_logged` action for instrumentation.

## Cursor pagination contract

- Cursor tokens are URL-safe base64 strings generated by the API. Treat them as opaque.
- The API ignores cursors that do not advance beyond the previous ordering key and resumes from the new anchor.
- Invalid or malformed cursors respond with `400 ap_invalid_cursor`.
- When `next_cursor` is `null`, `has_more` will also be `false`; subsequent calls with the same cursor return an empty page.

## Timezone fields

- Event payloads embed `server_tz` (IANA identifier) and `server_tz_offset_minutes` (signed minutes) to stabilise conversions even
  when cached.
- List responses repeat the same timezone context at the root level for convenience.
- Datetime values are formatted with `wp_date('c', $timestamp)`, so daylight-saving offsets are preserved.

## Error code catalogue

Error responses are normalised through `RestErrorFormatter` and have the shape:

```json
{
  "code": "ap_invalid_credentials",
  "message": "Invalid credentials.",
  "details": {
    "ap_invalid_credentials": {
      "messages": ["Invalid credentials."]
    }
  }
}
```

The following codes are considered stable. Extend (do not mutate) this catalogue when introducing new errors:

| Constant / source | Error code | HTTP status | Description |
| --- | --- | --- | --- |
| `RestErrorFormatter::AUTH_EXPIRED` | `ap_refresh_expired` | 401 | Refresh token expired during validation. |
| `RestErrorFormatter::REFRESH_REUSE` | `refresh_reuse` | 401 | Refresh token reuse detected; offending session revoked. |
| `RestErrorFormatter::AUTH_REVOKED` | `auth_revoked` | 401 | Token revoked due to password/email change or manual eviction. |
| `RestErrorFormatter::RATE_LIMITED` | `ap_rate_limited` | 429 | Rate limit exceeded; includes `Retry-After`. |
| `RestErrorFormatter::GEO_INVALID_BOUNDS` | `ap_geo_invalid` | 400 | Geosearch bounds invalid or unsupported. |
| `RestErrorFormatter::CORS_FORBIDDEN` | `cors_forbidden` | 403 | Request origin is not permitted for the mobile API. |
| Login | `ap_invalid_credentials` | 401 | Username/password combination not recognised. |
| Refresh | `ap_invalid_refresh` | 401 | Refresh token malformed, expired or user deleted. |
| Auth guard | `ap_missing_token` | 401 | `Authorization` header missing. |
| Auth guard | `ap_invalid_token` | 401 | Access token invalid or user missing. |
| TLS enforcement | `ap_tls_required` | 403 | HTTPS required for the mobile API. |
| Write guard | `ap_mobile_read_only` | 503 | Mobile write routes temporarily disabled. |
| Cursor parsing | `ap_invalid_cursor` | 400 | Cursor parameter malformed or not base64url encoded. |

## Operational runbook

- **Write route toggle** – set the `ap_enable_mobile_write_routes` option (or define `AP_ENABLE_MOBILE_WRITE_ROUTES`) to `0` to
  immediately disable like/follow/save mutations.
- **JWT key rotation** – use the admin tooling to mark a key as `Retiring` for at least one week before invoking
  `JWT::retire_key()`. Do not remove the key from storage until the retirement window has elapsed.
- **Error catalogue** – see [Error code catalogue](#error-code-catalogue). Add new codes rather than mutating existing ones.

## CLI operations

- **Metrics inspection** – aggregate request metrics for the mobile namespace:

  ```bash
  wp artpulse metrics dump --last=15m
  wp artpulse metrics dump --last=1h --route=/artpulse/v1/mobile/events --method=GET
  ```

  Results are grouped by route/method, reporting call counts, P50/P95 latency and status buckets (e.g. `2xx:120, 4xx:3`).
- **Manual purging** – trim stale sessions or metrics immediately:

  ```bash
  wp artpulse mobile purge --sessions
  wp artpulse mobile purge --metrics
  wp artpulse mobile purge --sessions --metrics
  ```

  `--sessions` removes refresh records inactive for 90+ days; `--metrics` prunes latency logs older than 14 days and trims stale
  route summaries. Both tasks run automatically via scheduled events.
- **Event geo backfill** – rebuild the geospatial cache when required:

  ```bash
  wp artpulse backfill-event-geo
  ```
