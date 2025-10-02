# Organization Onboarding Flow

The organization onboarding experience now encourages new community members to register, notify moderators, and immediately engage with nearby creators and events.

## 1. Registration Form
- Shortcode: `[ap_register_organization]`
- Location: Any page or post where you want to collect organization sign-ups.
- Handles: organization name, description, website URL, and contact email.
- Submission target: `POST /wp-json/artpulse/v1/submissions` with `post_type=artpulse_org`.
- Security: Includes a nonce (`ap_register_org_nonce`) validated during form handling.

## 2. Automatic Author Assignments
- After a successful submission, the current user receives the new organization ID in their `ap_organization_id` user meta.
- Administrators are alerted via `NotificationManager::add()` using the `org_submission` notification type.

## 3. Creator Engagement Prompts
- The shortcode reuses the social favorite/follow controls from `templates/partials/content-artpulse-item.php` to surface nearby artists and events.
- Users see calls-to-action to follow featured creators immediately after their organization is submitted.
- A personal notification (`org_follow_prompt`) invites the creator back to the dashboard to complete onboarding tasks.

## 4. Moderation & Next Steps
- Admin notifications include the organization title and submitting member name, providing a quick path to review.
- Newly onboarded organizations are encouraged to:
  - Follow local artists
  - Favorite upcoming events
  - Complete profile details from the organization dashboard

Embed this shortcode inside the onboarding page or welcome email sequence so new organizations understand the immediate community touchpoints available to them.
