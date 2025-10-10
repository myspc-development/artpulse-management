# Dashboard Upgrade & Profile Builder Review

## Overview
This document summarizes the current implementation of dashboard upgrade widgets and public profile builders for artists and organizations within the ArtPulse plugin.

## Member Dashboard Upgrade Widgets
- `RoleDashboards::getUpgradeWidgetData()` prepares the upgrade payload for member dashboards, populating intro copy and upgrade entries when options are available.【F:src/Core/RoleDashboards.php†L512-L543】
- `RoleDashboards::getUpgradeOptions()` builds upgrade options for artist and organization tiers, skipping the current membership level and sourcing purchase URLs from `MembershipUrls` before consolidating them.【F:src/Core/RoleDashboards.php†L1194-L1237】
- `mergeOrganizationUpgradeIntoArtistCard()` folds the organization upgrade call-to-action into the artist option as a secondary action to avoid duplicate cards while still exposing the organization path.【F:src/Core/RoleDashboards.php†L1240-L1287】
- `MemberDashboard::remove_org_upgrade_option()` removes the organization option from the shared widget because the member dashboard renders a dedicated organization upgrade card, preventing duplicate CTAs.【F:src/Frontend/MemberDashboard.php†L38-L66】
- `MemberDashboard::inject_dashboard_card()` augments dashboard data with `org_upgrade` state so templates can render upgrade progress or entry points.【F:src/Frontend/MemberDashboard.php†L27-L35】

## Organization Upgrade Flow from Member Dashboard
- `get_upgrade_state()` inspects current roles and the latest upgrade request, exposing status, linked organization IDs, and reasons for declined reviews to drive dashboard messaging.【F:src/Frontend/MemberDashboard.php†L68-L104】
- `handle_upgrade_request()` validates capability, nonce, and duplicates, then logs the request, notifies the member, and redirects with status-specific query parameters.【F:src/Frontend/MemberDashboard.php†L106-L157】
- `process_upgrade_request_for_user()` safeguards against existing roles or pending requests, creates a placeholder organization, and persists a review request, cleaning up on failure paths.【F:src/Frontend/MemberDashboard.php†L159-L199】

## Artist Profile Builder
- The `[ap_artist_builder]` shortcode enforces feature toggles, login state, and POST security (nonce and rate limiting) before loading the builder wrapper template for owned artist posts.【F:src/Frontend/ArtistBuilderShortcode.php†L16-L125】【F:src/Frontend/ArtistBuilderShortcode.php†L151-L194】
- Rate limiting triggers structured JSON error responses with headers when thresholds are exceeded, and disabled builders short-circuit with a 404 plus machine-readable payload for POST requests.【F:src/Frontend/ArtistBuilderShortcode.php†L21-L126】
- **Observation:** `respond_with_error()` assembles payload metadata (including optional nonce headers) but does not currently emit a response via `wp_send_json_*`, leaving callers without an actual HTTP response body or exit. This likely causes requests to hang after validation failures.【F:src/Frontend/ArtistBuilderShortcode.php†L129-L149】

## Organization Profile Builder
- The `[ap_org_builder]` shortcode gates access behind the org-builder flag, login requirements, ownership checks, and upgrade approval before rendering the multi-step builder with contextual messaging.【F:src/Frontend/OrgBuilderShortcode.php†L24-L119】
- `handle_save()` validates nonce, enforces rate limits, confirms ownership, and routes to step-specific handlers (`save_profile`, `save_images`, `publish_org`) while persisting errors across redirects.【F:src/Frontend/OrgBuilderShortcode.php†L121-L210】
- Organization saves support granular image validation, gallery management, and featured image selection with attachment ownership safeguards.【F:src/Frontend/OrgBuilderShortcode.php†L232-L360】
- Error pathways use `respond_with_error()` to emit structured JSON (with retry hints for nonce failures) and early termination, ensuring AJAX callers receive actionable feedback.【F:src/Frontend/OrgBuilderShortcode.php†L199-L264】

## Risks & Follow-Up Recommendations
1. **Incomplete Artist Error Responses:** Implement `wp_send_json()` (or `wp_send_json_error()`) plus an exit in `ArtistBuilderShortcode::respond_with_error()` so security or rate-limit failures surface correctly to clients.【F:src/Frontend/ArtistBuilderShortcode.php†L129-L149】
2. **Consistency Check:** After fixing the artist responder, confirm frontend scripts expect JSON payloads matching the org builder structure for consistent handling between role builders.

