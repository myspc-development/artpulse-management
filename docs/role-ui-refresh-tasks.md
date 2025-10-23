# ArtPulse Role UI Refresh Tasks

This checklist tracks the implementation workstreams for the refreshed member, artist, and organization experiences.

## Shared dashboard layer
- [x] Extend role configuration with layout metadata and richer dashboard payloads (quick actions, notifications, progress summaries).
- [x] Refresh shared dashboard template structure to render hero, insights grid, activity, and journey cards.
- [x] Update dashboard stylesheet to support the new tile layout, badges, and responsive sections.

## Member experience
- [x] Enrich member dashboard data with artist/organization journey progress and contextual CTAs.
- [x] Redesign the upgrade card to surface per-state messaging, progress, and actionable next steps.

## Artist experience
- [x] Supply builder context data (per-profile status, actions, submission readiness) to the artist wrapper.
- [x] Replace the artist builder wrapper with a card grid design featuring status badges, progress bars, and inline actions.
- [x] Add a dedicated artist builder stylesheet aligned with the organization builder visual language.

## Organization experience
- [x] Calculate builder step completeness and expose it to the wrapper for progress indicators and lock states.
- [x] Rework the organization builder wrapper with step badges, contextual guidance, and publish checklists.
- [x] Expand the organization builder stylesheet with support for badges, progress pills, and inline guidance blocks.
