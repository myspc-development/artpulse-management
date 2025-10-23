# ArtPulse Role UI Refresh Plan

## Purpose
This document captures the proposed UI blueprint for the Member, Artist, and Organization experiences within the ArtPulse plugin and outlines the primary implementation workstreams required to deliver the redesign.

## Member Experience
### UI Blueprint
- Preserve the existing member hero block (display name, contact data, membership tier, and bio) while introducing compact quick-action cards that link into artist and organization flows without disrupting the current dashboard layout.
- Keep the dual-path upgrade card but strengthen the visual hierarchy for each state (not started, pending, denied, approved) with distinctive copy and CTAs.
- Reimagine community activity metrics (favorites, follows, submissions) as interactive tiles with inline filters so members can take action directly from the dashboard.
- Expand the quick creation section by surfacing role-aware submission links and guidance to clarify when additional profiles must be completed before submitting.

### Implementation Highlights
- Extend the role registry to carry UI metadata (default tabs, quick actions) so dashboards derive layout decisions from configuration rather than template conditionals.
- Enhance `prepareDashboardData()` to include quick actions, notification summaries, and progress indicators exposed via the existing `artpulse/dashboard/data` filter.
- Update the shared dashboard template to render modular sections (profile hero, insights grid, activity timelines) using new partials, and refresh `assets/css/ap-user-dashboard.css` for card and grid support.
- Augment `MemberDashboard::inject_dashboard_card()` with progress flags (e.g., artist profile draft existence) and expand the upgrade state payload so templates can emit richer CTAs per status.

## Artist Experience
### UI Blueprint
- Convert the managed profile list into a grid of cards that includes status tags (draft/published) and highlights the "Submit Event" CTA only for published portfolios.
- Elevate the existing four-step builder navigation (Profile → Media → Preview → Publish) with explicit progress indicators and validation summaries at each stage.
- Introduce inline guidance drawers and autosave status messaging to the builder template to provide timely feedback while leveraging the current POST handling safeguards.

### Implementation Highlights
- Feed step-completion metadata from the artist builder shortcode into the wrapper template to drive progress UI and validation summaries.
- Rework the builder wrapper into modular panels per step (profile basics, media library, preview snapshot, publish checklist) while preserving current data bindings.
- Extend builder styling—mirroring patterns from `ap-org-builder.css`—to a dedicated artist stylesheet that covers status badges, progress bars, and responsive cards.

## Organization Experience
### UI Blueprint
- Maintain the four-step workspace but add progress dots and lock icons to communicate prerequisites before steps become available.
- Restructure the profile form into grouped panels (identity, contact, storytelling) with inline validation hints, and enhance the media step with upload guidance plus reorder controls.
- Enrich the preview step with callouts for unpublished changes and a publish checklist summarizing outstanding tasks.

### Implementation Highlights
- Calculate completeness scores within `OrgBuilderShortcode::render()` to control progress indicators and lock states for each step.
- Expand the builder wrapper to render checklists, validation notices, and preview summaries using existing step-specific branches.
- Update the organization builder stylesheet to support the new checklists, progress pills, and inline media management controls.

## Shared Workstreams
- Align dashboard, artist, and organization builders on a shared component vocabulary (card grids, progress pills, checklists) to reduce template divergence.
- Document new payload structures and template expectations to support QA and downstream theme integrations.
- Coordinate accessibility (focus order, ARIA attributes for progress components) and localization updates alongside template refactors.

## Next Steps
1. Socialize this plan with design and engineering stakeholders for feedback.
2. Break implementation highlights into discrete tickets grouped by shared, member, artist, and organization workstreams.
3. Schedule incremental releases, starting with backend data model updates, followed by template refactors, and concluding with visual polish and QA.
