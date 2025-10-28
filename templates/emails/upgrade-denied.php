<?php
/** @var WP_User $user */
/** @var array $context */

$reason            = isset( $context['reason'] ) ? (string) $context['reason'] : '';
$dual_role_message = isset( $context['dual_role_message'] ) ? (string) $context['dual_role_message'] : __( 'Remember: you can keep both artist and organization access active—switch roles from your dashboard.', 'artpulse-management' );

$lines = [
    sprintf(
        esc_html__( 'Hi %s,', 'artpulse-management' ),
        esc_html( $user->display_name ?: $user->user_login )
    ),
    esc_html__( 'Thank you for your interest in the ArtPulse tools. After review we\'re unable to approve the {role_label} upgrade at this time.', 'artpulse-management' ),
];

if ( '' !== trim( $reason ) ) {
    $lines[] = esc_html__( 'Reason: {reason}', 'artpulse-management' );
}

$lines[] = esc_html__( 'Update your details and resubmit any time from your {role_label} dashboard: {dashboard_url}', 'artpulse-management' );

if ( '' !== trim( $dual_role_message ) ) {
    $lines[] = esc_html( $dual_role_message );
}

echo implode( "\n\n", $lines );
