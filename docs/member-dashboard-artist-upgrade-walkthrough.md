# Member Dashboard Artist Upgrade Walkthrough

This guide describes how to manually verify that a logged-in member can request artist access from the dashboard and advance through the upgrade lifecycle.

## Prerequisites
- WordPress site with the ArtPulse plugin active.
- Test member account with only the `member` role (no artist/organization capabilities yet).
- Ability to sign in as an administrator for verifying data changes.

## 1. Load the Member Dashboard
1. Sign in as the test member and navigate to `/dashboard/`.
2. Confirm the "Next steps" card renders the artist journey with the **Request artist access** button in a form submission mode. The button is populated from `MemberDashboard::build_artist_state()` when the user lacks the `artist` role.【F:src/Frontend/MemberDashboard.php†L82-L153】
3. Ensure the form posts to `admin-post.php` with the hidden `action=ap_dashboard_upgrade` and the `upgrade_type` set to `artist` (see `member-org-upgrade.php`).【F:templates/dashboard/partials/member-org-upgrade.php†L42-L82】

## 2. Submit the Artist Access Request
1. Click **Request artist access**. This triggers `handle_dashboard_upgrade_request()` which validates login state, nonce, and rate limiting before dispatching to `process_artist_upgrade_request()` for artist upgrades.【F:src/Frontend/MemberDashboard.php†L240-L323】
2. On success you should be redirected back to `/dashboard/` with `?ap_artist_upgrade=pending` appended. A success entry is logged via `AuditLogger::info('artist.upgrade.requested', …)`.【F:src/Frontend/MemberDashboard.php†L307-L322】
3. In the dashboard UI, the artist journey should now display a pending notice because `build_artist_state()` detects the queued review request and swaps the CTA to **Check request status**.【F:src/Frontend/MemberDashboard.php†L134-L206】

## 3. Validate Back-End Artifacts
1. As an administrator, open the WordPress admin and confirm a new draft `artpulse_artist` post exists for the member. The draft is created by `create_placeholder_artist()` during the request workflow.【F:src/Frontend/MemberDashboard.php†L339-L372】
2. Check the corresponding upgrade review post (`artpulse_upgrade`) was created by `UpgradeReviewRepository::upsert_pending()`. The post stores a `_ap_placeholder_artist_id` meta entry linking back to the draft profile.【F:src/Frontend/MemberDashboard.php†L323-L358】

## 4. Approve or Deny the Request (Admin)
1. From the admin panel, open the new upgrade review and set its status to Approved or Denied (depending on the scenario you want to verify).
2. After approval, the member dashboard should update automatically because `build_artist_state()` inspects the latest review status: 
   - `approved` transitions the CTA to **Open artist tools** and exposes the artist dashboard link.
   - `denied` surfaces moderator feedback and offers a **Reopen artist builder** link.【F:src/Frontend/MemberDashboard.php†L148-L205】

## 5. Confirm Final Role Assignment
1. Once approved, verify that the member's WordPress user now includes the `artist` role, granting access to the artist dashboard at `/dashboard/?role=artist` as surfaced in the CTA URL.【F:src/Frontend/MemberDashboard.php†L119-L160】
2. If the request is denied, confirm the feedback text appears beneath the journey card. This renders the `reason` pulled from the review post and is displayed in the journey template.【F:templates/dashboard/partials/member-org-upgrade.php†L18-L108】

Following these steps ensures the member dashboard upgrade card and backend request pipeline operate correctly for the artist role.
