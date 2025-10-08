<?php
/** @var WP_User $user */
/** @var array $context */

$reason = isset( $context['reason'] ) ? wp_strip_all_tags( (string) $context['reason'] ) : '';

$lines = [
    sprintf(
        esc_html__( 'Hi %s,', 'artpulse-management' ),
        esc_html( $user->display_name ?: $user->user_login )
    ),
    esc_html__( 'Thank you for your interest in the ArtPulse Organization tools. After review we\'re unable to approve the request at this time.', 'artpulse-management' ),
];

if ( $reason ) {
    $lines[] = sprintf(
        esc_html__( 'Reason: %s', 'artpulse-management' ),
        $reason
    );
}

$lines[] = esc_html__( 'You can update your details and resubmit the request from your dashboard whenever you\'re ready.', 'artpulse-management' );

echo implode( "\n\n", $lines );
