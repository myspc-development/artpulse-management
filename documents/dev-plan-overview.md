# Dev Plan Overview

This document provides a high-level summary of all modular development plans for the ArtPulse Management system. Each module is documented in its own dedicated file to support easier collaboration, scaling, and maintenance.

---

## 📁 Core Modules

- [dev-plan-core.md](./dev-plan-core.md): Plugin bootstrapping, settings, and common utilities.
- [dev-plan-access-control.md](./dev-plan-access-control.md): Role- and membership-based content access and redirects.
- [dev-plan-users-profile.md](./dev-plan-users-profile.md): Custom fields and enhanced profile functionality.

## 📦 Membership & Organizations

- [dev-plan-membership.md](./dev-plan-membership.md): Subscription levels, roles, and renewal logic.
- [dev-plan-organizations.md](./dev-plan-organizations.md): Grouping users into teams with shared access and management.

## 🎨 Content Management

- [dev-plan-artists.md](./dev-plan-artists.md): Artist profiles, bios, and relationship to content.
- [dev-plan-artworks.md](./dev-plan-artworks.md): Artwork entries, images, and linking to artists/events.
- [dev-plan-events.md](./dev-plan-events.md): Exhibitions, workshops, scheduling, and associations.

## 💳 Commerce & QA

- [dev-plan-payments.md](./dev-plan-payments.md): Stripe integration, status tracking, and receipt logic.
- [dev-plan-testing.md](./dev-plan-testing.md): Unit/UI/CI testing strategy and QA workflows.

---

## ✅ Next Steps

- Use this document as the starting point for planning, reviewing, and expanding each module.
- Ensure each feature addition or refactor updates the relevant module’s dev plan.
- Periodically audit modules to ensure architecture and documentation remain aligned.
