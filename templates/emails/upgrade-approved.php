<?php
/** @var WP_User $user */
/** @var array $context */

$dual_role_message = isset( $context['dual_role_message'] ) ? (string) $context['dual_role_message'] : __( 'Remember: you can keep both artist and organization access active—switch roles from your dashboard.', 'artpulse-management' );

$lines = [
    sprintf(
        esc_html__( 'Hi %s,', 'artpulse-management' ),
        esc_html( $user->display_name ?: $user->user_login )
    ),
    esc_html__( 'Great news! Your {role_label} upgrade has been approved.', 'artpulse-management' ),
    esc_html__( 'You can now build your {role_label} profile, upload images, and submit events using the builder tools.', 'artpulse-management' ),
    esc_html__( 'Open your {role_label} dashboard: {dashboard_url}', 'artpulse-management' ),
];

if ( '' !== trim( $dual_role_message ) ) {
    $lines[] = esc_html( $dual_role_message );
}

echo implode( "\n\n", $lines );
