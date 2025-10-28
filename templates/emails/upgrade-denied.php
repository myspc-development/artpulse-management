<?php
/** @var WP_User $user */
/** @var array $context */

$reason             = isset( $context['reason'] ) ? wp_strip_all_tags( (string) $context['reason'] ) : '';
$dashboard_url      = isset( $context['dashboard_url'] ) ? esc_url( $context['dashboard_url'] ) : esc_url( home_url( '/dashboard/?role=organization' ) );
$dual_role_message  = isset( $context['dual_role_message'] ) ? (string) $context['dual_role_message'] : __( 'Remember: you can keep both artist and organization access active—switch roles from your dashboard.', 'artpulse-management' );

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

$lines[] = sprintf(
    esc_html__( 'Update your details and resubmit any time from your dashboard: %s', 'artpulse-management' ),
    $dashboard_url
);
$lines[] = esc_html( $dual_role_message );

echo implode( "\n\n", $lines );
