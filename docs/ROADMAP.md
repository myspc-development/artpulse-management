# ArtPulse Management Plugin — Development Plan

## Overview

This document outlines the next set of enhancements and features for the ArtPulse Management plugin, building on the existing functionality and test coverage. It breaks features into logical streams, estimates priorities, and suggests rough timelines.

---

## Roadmap & Phases

| Phase        | Feature Stream               | Deliverables                                                    | Priority |
|--------------|------------------------------|-----------------------------------------------------------------|----------|
| **A: Testing**     | Expand Test Coverage         | • `processExpirations()` tests<br>• `handleStripeWebhook()` tests | High     |
| **B: CI / CD**     | Automation & Quality         | • GitHub Actions workflow<br>• Code coverage report (coverage/) | High     |
| **C: Performance** | Caching & Optimization       | • Transient caching for REST endpoints<br>• Asset minification  | Medium   |
| **D: UX Polish**   | Front‐end Enhancements       | • Loading spinners & error messages<br>• Accessibility tweaks   | Medium   |
| **E: CLI & Import**| WP‐CLI & Bulk Data           | • `wp artpulse import <file>` command<br>• CSV / JSON importer  | Low      |
| **F: International**| i18n & Localization         | • Translate strings in code & blocks<br>• PO/MO file packaging  | Low      |
| **G: Org Experience** | Admin Tools & Analytics     | • Dashboard widgets<br>• Billing insights<br>• Linked artist stats | Medium   |
| **H: Engagement** | Community Features           | • Favorites & follows<br>• Profile linking<br>• Public badges   | Medium   |
| **I: Monetization Expansion** | Tiered Upgrades      | • Pay-per-feature unlocks<br>• Renewal toggles<br>• Paid analytics | Medium   |

---

## A. Expand Test Coverage (1–2 weeks)

1. **processExpirations()**  
   - Mock `get_users()` and test downgrades, meta updates, email notifications.  
2. **handleStripeWebhook()**  
   - Feed sample JSON payloads for `checkout.session.completed`, subscription renewals, cancellations.  
3. **AccessControlManager**  
   - Verify capability assignment and role enforcement.  
4. **ShortcodeManager & DirectoryManager**  
   - PHP‐side rendering functions, REST response shapes.

---

## B. CI / CD (1 week)

- **GitHub Actions**  
  - `on: [push, pull_request]`  
  - Steps: composer install, npm ci & build, phpunit, wp‐scripts lint.  
- **Code Coverage**  
  - Integrate [phpunit-coverage] to output HTML in `coverage/`.  
- **Badge**  
  - Add build & coverage badge to `README.md`.

---

## C. Performance (1–2 weeks)

- **Transient Caching**  
  - Cache `[ap_directory]` REST responses per filter params for 5–10 minutes.  
- **Asset Bundling**  
  - Ensure `wp-scripts build` output is minified for production.  
- **Query Optimization**  
  - Add pagination, avoid `get_posts` by using `WP_Query` with `fields=ids` when only IDs needed.  
- **Offline Access**  
  - Cache REST content using Service Workers for offline access on mobile or kiosk views.

---

## D. UX Polish (1–2 weeks)

- **Loading Indicators**  
  - Show spinner in `.ap-directory-results` and `.ap-membership-account` until API returns.  
- **Error Handling**  
  - Display friendly messages on REST or network failures.  
- **Accessibility**  
  - Ensure filters have `<label>`s, buttons have ARIA attributes, color contrast passes WCAG.  
- **User Onboarding**  
  - Role-based guided setup modals post-registration.  
  - Profile progress tracker with completeness bar.  
- **Contextual Help**  
  - Help sidebar with Markdown/HTML content linked to screen context.

---

## E. WP-CLI & Bulk Import (2–3 weeks)

- **Command**: `wp artpulse import <path>`  
  - Supports CSV/JSON to create Events, Artists, Artworks, Orgs in batch.  
- **Options**  
  - `--preview`, `--dry-run`, `--skip-existing`.  
- **Testing**  
  - Unit tests for importer class, WP-CLI integration tests using WP-CLI test harness.

---

## F. Internationalization (1 week)

- **Scan strings** with `xgettext` and generate `.pot` file in `languages/`.  
- **Load text domain** in plugin bootstrap: `load_plugin_textdomain('artpulse', false, dirname(plugin_basename(__FILE__)) . '/languages');`  
- **Block translations**: ensure `block.json` references i18n strings.  
- **Publish** translation files.

---

## G. Organizational Experience (1–2 weeks)

- **Org Admin Dashboard Enhancements**
  - Linked artist and artwork summary cards
  - Centralized billing view with history
  - Org-level analytics: member counts, event metrics

---

## H. Community & Engagement (2 weeks)

- **Favorites & Follows**
  - Allow users to favorite artworks, events, or artists
  - Directory filters for "Followed" or "Favorited" content
  - REST endpoints for follow actions
- **Profile Linking Requests**
  - Artists can request to be linked to organizations
  - Org admins approve/reject via dashboard

---

## I. Monetization Expansion (1–2 weeks)

- **Pay-per-Feature Unlocks**
  - Sell upgrades (e.g. additional artworks or visibility boosts)
- **Auto-Renewal Settings**
  - Users can toggle subscription renewal on/off
  - Reminder emails before renewal
- **Analytics for Paid Members**
  - Views/clicks data for artists, artworks, events

---

## Timeline & Milestones

- **Sprints**: 2-week cadence  
  - **Sprint 1**: Phases A + B  
  - **Sprint 2**: Phases C + D  
  - **Sprint 3**: Phases E + F  
  - **Sprint 4**: Phases G + H  
  - **Sprint 5**: Phase I  
- **Milestone Reviews**: end of each sprint, demo & merge.

---

## Notes & Dependencies

- Ensure Composer & npm versions locked in `composer.lock` and `package-lock.json`.  
- Maintain backward compatibility with WP 6.8 and PHP 8.3.  

---

*End of Development Plan*