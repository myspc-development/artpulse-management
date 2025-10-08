<?php
/** @var WP_User $user */

$lines = [
    sprintf(
        /* translators: %s member display name. */
        esc_html__( 'Hi %s,', 'artpulse-management' ),
        esc_html( $user->display_name ?: $user->user_login )
    ),
    esc_html__( 'Thanks for requesting access to the ArtPulse Organization tools. Our team will review your submission shortly.', 'artpulse-management' ),
    esc_html__( 'We will notify you as soon as the review is complete.', 'artpulse-management' ),
];

echo implode( "\n\n", $lines );
