# Dashboard Role Tools Review

## Overview
This review focuses on the role upgrade dashboard and the builder flows that support artist and organization profiles. The goal is to summarize the current behaviour, highlight accessibility and capability checks, and outline targeted enhancements that can deepen tooling for both roles.

## Role Dashboard State Management
- `MemberDashboard::build_artist_state()` and `MemberDashboard::build_org_state()` assemble per-role upgrade CTAs, reasons, and profile links based on the user role, the most recent review, and portfolio status transitions.【F:src/Frontend/MemberDashboard.php†L92-L220】【F:src/Frontend/MemberDashboard.php†L223-L325】
- Both helpers correctly default to logged-out safe states and guard on invalid users before looking up upgrade data, preventing notices on anonymous dashboard visits.【F:src/Frontend/MemberDashboard.php†L115-L248】
- Upgrade review lookups reuse `UpgradeReviewRepository` for messaging and link building, so status transitions align with moderation tooling without duplicating logic.【F:src/Frontend/MemberDashboard.php†L143-L189】【F:src/Frontend/MemberDashboard.php†L268-L312】

### Suggested Enhancements
1. **Shared CTA builder utility.** The artist and organization states compute nearly identical CTA arrays for `requested`, `denied`, and `approved` statuses. Extracting a `formatJourneyCta($status, $urls)` helper would reduce drift and simplify future UI experiments.
2. **Expose progress metadata.** `build_artist_state()` only surfaces coarse `status` strings, while the org journey feeds checklists later in the flow. Returning a `progress` percentage and `next_step` slug directly from these helpers would let the dashboard surface progress bars without re-inspecting journeys downstream.
3. **Dual-role conflict hints.** When a user has both upgrade journeys available, the dashboard could display short guidance about managing two profiles. The file already sets `dual_role_message` later; wiring that into these state arrays (e.g., `state['notices'][]`) would keep messaging colocated with CTA logic.

## Artist Builder Tooling
- The shortcode verifies login status, respects pending/denied upgrade decisions, and filters accessible portfolios through `PortfolioAccess::can_manage_portfolio()` and `current_user_can('edit_post')` checks before exposing management URLs.【F:src/Frontend/ArtistBuilderShortcode.php†L45-L136】
- Autosave is conditionally initialized for the active artist ID and localizes REST endpoints, nonce, and retry strings to the `ap-autosave` module, keeping the builder responsive without loading scripts for every profile card.【F:src/Frontend/ArtistBuilderShortcode.php†L150-L183】
- Progress indicators on cards are currently hard-coded per post status (draft → 45%, pending → 80%, etc.), which gives users a rough sense of completeness but does not reflect checklist detail.【F:src/Frontend/ArtistBuilderShortcode.php†L91-L133】

### Suggested Enhancements
1. **Checklist-driven progress.** Mirror the organization builder’s checklist scoring to compute progress dynamically (e.g., from required field completion), giving artists clearer guidance than static percentages.
2. **Media management cues.** When `media_enabled` is false (no upload capability), display an inline notice or link to documentation so users understand how to request media permissions instead of seeing silent feature removal.【F:src/Frontend/ArtistBuilderShortcode.php†L134-L135】
3. **Accessible status badges.** Ensure the badge component announced in the template exposes `aria-live` or visually hidden text describing status changes, supporting screen readers when a draft moves to pending/published. Templates should incorporate the computed `status_label` in accessible markup.

## Organization Builder Tooling
- The organization builder enforces login, capability, and ownership before rendering, and blocks unauthorized edits via both synchronous notices and JSON responses for POST submissions.【F:src/Frontend/OrgBuilderShortcode.php†L37-L212】
- Autosave, checklist progress, and publish steps are initialized immediately with localized strings, keeping the workflow stateful between sections.【F:src/Frontend/OrgBuilderShortcode.php†L92-L135】
- Image uploads are constrained by MIME type, size, and minimum dimensions to prevent unoptimized assets from entering the library.【F:src/Frontend/OrgBuilderShortcode.php†L21-L28】【F:src/Frontend/OrgBuilderShortcode.php†L246-L310】

### Suggested Enhancements
1. **Step-specific guidance.** Surface contextual helper text for each step (profile/images/preview/publish) via the localized script payload, allowing dynamic instructions or accessibility tips without hardcoding them in templates.
2. **Upload error aggregation.** When validation fails (`respond_with_error()`), store structured error codes so the UI can map them to specific inputs, reducing guesswork for users relying on assistive technology.【F:src/Frontend/OrgBuilderShortcode.php†L246-L310】
3. **Consistent autosave HUD.** Align the autosave heads-up display between artist and organization builders by sharing a React/JS component (even if rendered server-side) so keyboard focus behaviour and announcements stay consistent across roles.

## Role Capability Verification
- Role creation and capability assignment ensure artists and organizations receive editing rights for their respective CPTs plus event management, while administrators/editors inherit shared management caps.【F:src/Core/RoleSetup.php†L40-L139】
- Both builder shortcodes confirm ownership through `PortfolioAccess` utilities before exposing edit forms, preventing privilege escalation if a role retains edit caps but lacks ownership metadata.【F:src/Frontend/ArtistBuilderShortcode.php†L76-L136】【F:src/Frontend/OrgBuilderShortcode.php†L56-L133】

### Suggested Enhancements
1. **Capability health check CLI.** Add a WP-CLI command that audits role capabilities against expected arrays. This would help operators confirm role setup after deployments or migrations.
2. **Dashboard self-test widget.** Surface a lightweight diagnostic card in the member dashboard that confirms the current user meets preconditions (role, ownership, pending review) and links to support if any checks fail.

## Accessibility Verification Summary
- Login, nonce, ownership, and capability checks ensure builder tools are only accessible to approved users, while pending requests receive dedicated notices instead of blank screens.【F:src/Frontend/ArtistBuilderShortcode.php†L45-L146】【F:src/Frontend/OrgBuilderShortcode.php†L37-L96】
- To strengthen accessibility, extend templates with ARIA annotations for status messaging, expose validation errors inline, and reuse autosave UI components so keyboard and screen reader workflows remain predictable across roles.

