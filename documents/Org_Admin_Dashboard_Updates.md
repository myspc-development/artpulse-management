# Organization Admin Dashboard – Suggested Updates

This document outlines a few enhancements that would improve the organization administrator dashboard. The goal is to provide better event management tools, quick performance metrics and the ability to monitor RSVPs.

## 1. Events Management Improvements
- Add a sortable table listing all events belonging to the current organization.
- Include status badges (published, pending, draft) with quick links to edit or view the event.
- Provide filters for upcoming or expired events and bulk actions such as "feature" or "delete".

## 2. Performance Metrics
- Display widgets summarizing event statistics: total events, pending events, featured events and artwork counts.
- Surface analytic data from `ListingAnalytics` for each event, showing view and click totals.
- Offer an **Export CSV** button to download metrics for external reporting.

## 3. Event RSVPs
- Load RSVPs for the organization’s events using the existing AJAX endpoint `ead_get_my_rsvps`.
- Show attendee email and RSVP date in a styled table with pagination for large lists.
- Allow administrators to export RSVP data for a single event or all events.

These updates will bring the organization dashboard in line with the artist dashboard and provide administrators with a quick overview of event engagement.
