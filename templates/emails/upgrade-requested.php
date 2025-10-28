<?php
/** @var WP_User $user */
/** @var array   $context */

$dashboard_url     = isset( $context['dashboard_url'] ) ? esc_url( $context['dashboard_url'] ) : esc_url( home_url( '/dashboard/?role=organization' ) );
$dual_role_message = isset( $context['dual_role_message'] ) ? (string) $context['dual_role_message'] : __( 'Remember: you can keep both artist and organization access active—switch roles from your dashboard.', 'artpulse-management' );

$lines = [
    sprintf(
        /* translators: %s member display name. */
        esc_html__( 'Hi %s,', 'artpulse-management' ),
        esc_html( $user->display_name ?: $user->user_login )
    ),
    esc_html__( 'Thanks for requesting access to the ArtPulse Organization tools. Our team will review your submission shortly.', 'artpulse-management' ),
    sprintf(
        esc_html__( 'You can check the status anytime from your dashboard: %s', 'artpulse-management' ),
        $dashboard_url
    ),
    esc_html__( 'We will notify you as soon as the review is complete.', 'artpulse-management' ),
    esc_html( $dual_role_message ),
];

echo implode( "\n\n", $lines );
