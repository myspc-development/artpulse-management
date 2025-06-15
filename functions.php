<?php
// Generic helper functions for ArtPulse plugin.

/**
 * Send welcome email after member registration.
 *
 * @param int $user_id User ID of the newly registered member.
 */
function artpulse_send_welcome_email( $user_id ) {
    $user    = get_userdata( $user_id );
    $email   = $user->user_email;
    $name    = $user->display_name;

    $subject = 'Welcome to ArtPulse!';
    $message = "Hi $name,\n\nThank you for registering.\n\nYour membership is active. You can now log in and explore ArtPulse.\n\nVisit: " . home_url() . "\n\nRegards,\nArtPulse Team";

    wp_mail( $email, $subject, $message );
}

// ---- Membership Manager admin menu and related code has been removed ----
// All member admin is now managed under the consolidated Member Management menu.
