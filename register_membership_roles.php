<?php
function ead_register_membership_roles() {
    add_role('member_basic', 'Basic Member', [
        'read' => true,
        'rsvp_events' => true,
        'view_dashboard' => true
    ]);
    add_role('member_registered', 'Registered Member', [
        'read' => true,
        'view_exclusive_content' => true
    ]);
    add_role('member_pro', 'Pro Artist', [
        'read' => true,
        'submit_artwork' => true,
        'access_artist_dashboard' => true
    ]);
    add_role('member_org', 'Organization Member', [
        'read' => true,
        'submit_organization' => true,
        'access_org_dashboard' => true
    ]);
}
register_activation_hook(__FILE__, 'ead_register_membership_roles');

