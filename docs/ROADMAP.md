# ArtPulse Management Plugin — Development Plan

## Overview

This document outlines the next set of enhancements and features for the ArtPulse Management plugin. Features are broken into streams, prioritized, and tagged with status markers.

---

## Roadmap & Phases

| Phase         | Feature Stream             | Deliverables                                                    | Priority | Status     |
|---------------|----------------------------|-----------------------------------------------------------------|----------|------------|
| **A: Testing**        | Expand Test Coverage         | • `processExpirations()` tests<br>• `handleStripeWebhook()` tests | High     | [Planned]   |
| **B: CI / CD**        | Automation & Quality         | • GitHub Actions workflow<br>• Code coverage report              | High     | [In Progress] |
| **C: Performance**    | Caching & Optimization       | • Transient REST caching<br>• Asset minification                 | Medium   | [Planned]   |
| **D: UX Polish**      | Front-end Enhancements       | • Spinners<br>• Accessibility tweaks                             | Medium   | [Planned]   |
| **E: CLI & Import**   | WP-CLI & Bulk Data Tools     | • CLI importer command<br>• CSV/JSON support                     | Low      | [Planned]   |
| **F: International**  | i18n & Localization          | • Translate strings<br>• Load `.pot` file                        | Low      | [Planned]   |
| **G: Org Experience** | Admin Tools & Analytics      | • Billing, stats, artist links                                   | Medium   | [Planned]   |
| **H: Engagement**     | Community Features           | • Follows<br>• Profile linking<br>• Badges                       | Medium   | [Planned]   |
| **I: Monetization**   | Tiered Upgrades              | • Paid analytics<br>• Renewals<br>• Feature unlocks              | Medium   | [Planned]   |

---

## Sprint Breakdown

### 🧪 A. Expand Test Coverage [Planned]
- `processExpirations()` tests
- `handleStripeWebhook()` mock testing
- Role and capability checks
- Shortcode rendering logic

### 🔁 B. CI/CD Setup [In Progress]
- GitHub Actions workflow
- Composer + npm install
- `phpunit` + linting
- HTML coverage reports

### ⚡ C. Performance Optimizations [Planned]
- Transient caching on directory REST
- Minified frontend bundles
- `WP_Query` ID-only optimizations
- Optional Service Worker

### 🎨 D. UX Polish [Planned]
- Loading indicators
- ARIA attributes + labels
- Friendly error messaging
- Guided onboarding & help sidebar

### 🛠️ E. WP-CLI & Import Tools [Planned]
- Command: `wp artpulse import`
- CSV/JSON support
- `--dry-run`, `--preview` flags
- Unit & integration tests

### 🌍 F. Internationalization [Planned]
- String scan & `.pot` generation
- Load text domain
- Block i18n support

### 🏢 G. Org Admin Tools [Planned]
- Linked stats for artists/artworks
- Billing view + history
- Org-level engagement insights

### 👥 H. Engagement Features [Planned]
- Favoriting, following, filters
- Profile link requests & moderation
- Public badges

### 💳 I. Monetization Expansion [Planned]
- Feature unlocks via payment
- Renewal toggle + email reminders
- Analytics for paid members

---

## Timeline

- **Sprint 1**: Phases A + B
- **Sprint 2**: Phases C + D
- **Sprint 3**: Phases E + F
- **Sprint 4**: Phases G + H
- **Sprint 5**: Phase I

> 💡 Reviews at end of each sprint: Demo & merge.

---

## Dependencies

- Lock versions in `composer.lock`, `package-lock.json`
- Ensure compatibility with WordPress 6.8+ and PHP 8.3+

---

*End of Development Plan*