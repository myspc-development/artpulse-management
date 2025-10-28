<?php
/** @var WP_User $user */
/** @var array $context */

$dashboard_url     = isset( $context['dashboard_url'] ) ? esc_url( $context['dashboard_url'] ) : esc_url( home_url( '/dashboard/?role=organization' ) );
$dual_role_message = isset( $context['dual_role_message'] ) ? (string) $context['dual_role_message'] : __( 'Remember: you can keep both artist and organization access active—switch roles from your dashboard.', 'artpulse-management' );

$lines = [
    sprintf(
        esc_html__( 'Hi %s,', 'artpulse-management' ),
        esc_html( $user->display_name ?: $user->user_login )
    ),
    esc_html__( 'Great news! Your organization upgrade has been approved.', 'artpulse-management' ),
    esc_html__( 'You can now build your organization profile, upload images, and submit events using the Organization Builder.', 'artpulse-management' ),
    sprintf(
        esc_html__( 'Open your organization dashboard: %s', 'artpulse-management' ),
        $dashboard_url
    ),
    esc_html( $dual_role_message ),
];

echo implode( "\n\n", $lines );
