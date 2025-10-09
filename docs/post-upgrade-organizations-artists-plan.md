# Post-upgrade Organizations & Artists Implementation Plan

## 1. Access Model & Ownership Enforcement
- **Roles**: `artist`, `organization`, `administrator`.
- **Capabilities**:
  - Shared: `ap_manage_own_org`, `ap_manage_own_artist`, `ap_submit_events`, `ap_manage_portfolio`.
  - Admin-only: `ap_moderate_events`, `ap_impersonate_creator` (optional).
- **Ownership helper**: `PortfolioAccess::can_manage_portfolio( $user_id, $post_id )` returns true when the user is an administrator, the `post_author`, or matches the `_ap_owner_user` meta.
- **Guards**:
  - Apply the helper to every portfolio write (web + REST).
  - Enforce nonces on shortcode forms; JWT auth + ownership middleware for REST/mobile routes.
  - Audit every mutation through `AuditLogger`, including before/after diffs for fields and media.

## 2. Mobile-ready Builder Surfaces
- Extend current `[ap_org_builder]` and add `[ap_artist_builder]` shortcode.
- Provide a mobile-first layout toggle via `?view=mobile` for webview parity.
- UI steps: Profile, Media, Preview, Publish.
- Mobile layout specifics: single-column, large tap targets, sticky Save/Preview bar.
- Event submission CTA opens an ownership-locked event form.
- Mobile API endpoints:
  - `GET/PUT /mobile/portfolio/{type}/{id}` for meta.
  - `POST /mobile/portfolio/{type}/{id}/media` for uploads, reorder, featured selection.
  - `POST /mobile/events` to create pending events (org/artist pre-bound).
- All write routes rate-limited and guarded by ownership middleware.

## 3. Event Submission Workflow
- **Creator experience**:
  - “Submit Event” button within builders pre-binds `org_id` or `artist_id`.
  - Fields: Title, Start/End, Venue + geocoder, Description, Category/Tags, Ticket URL, Flyer.
  - Submission creates `artpulse_event` posts with `post_status=pending` and owner metadata.
  - Confirmation screen highlights pending status with read-only preview link.
- **Admin experience**:
  - Moderation queue with filters: Pending, Changes Requested, Approved.
  - Row actions: Approve (publish), Request changes (comment + email), Deny; support bulk actions.
  - Include image thumbnail for moderation context.
  - Auto-email creators and record all state changes via `AuditLogger`.
- **Notifications**: MVP logs, optionally push/email (“Event approved”, “Changes requested”).

## 4. Portfolio Builder Permissions & Scope
- Editable entities: profile meta (tagline, bio, contacts, links, location), media assets (logo, cover, gallery, featured), widget order.
- Restricted to owners and administrators; read-only for others.
- Public output: Salient single profile template + grid cards.
- Versioning (optional): store last published snapshot, support Save Draft vs Publish states.

## 5. Widget System
- Central registry (`PortfolioWidgetRegistry`) that exposes drag-and-drop modules:
  - Hero, About, Gallery, Upcoming Events, Map & Location, Contact & Links, Press/Highlights, Sponsors/Partners.
- Each widget supports enable/disable, ordering, minimal configuration, and live preview.
- Persist widget configuration per portfolio; disable removes module from public template.

## 6. Security & Media Handling
- Restrict uploads to images (mimetypes), max 10MB, minimum 200×200 resolution.
- Revalidate server-side; use `wp_get_attachment_image()` with lazy-loading and sanitized alts.
- Maintain placeholders to avoid layout shifts.
- Set `post_parent` on media to portfolio post; only owner/admin can delete.

## 7. Settings & Feature Flags
- Options: `ap_enable_org_builder`, `ap_enable_artist_builder`, `ap_require_event_review`, `ap_widget_whitelist`.
- Confirm approved mobile origins and write-route kill switch are honored.
- Flags drive shortcode availability, mobile endpoints, and moderation requirements.

## 8. Key Classes & Files
- `src/Frontend/ArtistBuilderShortcode.php` (mirrors org builder).
- `templates/artist-builder/*` for UI markup.
- `src/Frontend/Shared/PortfolioWidgetRegistry.php` and `PortfolioAccess.php`.
- `src/Rest/PortfolioController.php` for mobile/web meta & media endpoints.
- Extend `OrganizationEventForm` to support artist context (or shared base class).

## 9. Quality Assurance Checklist
- Access control: subscriber blocked (403); owner/admin allowed (200) for portfolio writes.
- Ownership spoof prevention for `org_id`/`artist_id` payloads.
- Event lifecycle: submit → pending → admin approval publishes → notifications fired.
- Widgets: ordering persists, disabling hides module publicly.
- Media validation: reject invalid MIME/size; featured flag yields correct Salient markup.
- Mobile API: 200 on profile/media updates, 201 with pending status on event creation.
- Rate limiting: repeated writes return 429 with headers.

## 10. Builder Access Options
- **Option A**: Role-based (simple, lacks multi-ownership nuance).
- **Option B** (recommended): Role + ownership; builder lists owned orgs/artists via `_ap_owner_user` or author.
- **Option C**: Team members via `_ap_owner_users[]`; extend ownership helper accordingly.
