<?php

namespace ArtPulse\Core;

// Create custom roles if not already defined
if ( ! get_role( 'member' ) ) {
    add_role( 'member', 'Member', [ 'read' => true ] );
}
if ( ! get_role( 'artist' ) ) {
    add_role( 'artist', 'Artist', [ 'read' => true ] );
}
if ( ! get_role( 'organization' ) ) {
    add_role( 'organization', 'Organization', [ 'read' => true ] );
}

// Capabilities per CPT
$cpt_caps = [
    'artpulse_event',
    'artpulse_artist',
    'artpulse_artwork',
    'artpulse_org',
];

// Role definitions
$roles_caps = [
    'member' => [
        'read',
        'create_artpulse_event',
    ],
    'artist' => [
        'read',
        'create_artpulse_artist',
        'edit_artpulse_artist', 'read_artpulse_artist', 'delete_artpulse_artist',
        'edit_artpulse_artists', 'edit_others_artpulse_artists',
        'publish_artpulse_artists', 'read_private_artpulse_artists',
        'delete_artpulse_artists', 'delete_private_artpulse_artists',
        'delete_published_artpulse_artists', 'delete_others_artpulse_artists',
        'edit_private_artpulse_artists', 'edit_published_artpulse_artists',
    ],
    'organization' => [
        'read',
        'create_artpulse_org',
        'edit_artpulse_org', 'read_artpulse_org', 'delete_artpulse_org',
        'edit_artpulse_orgs', 'edit_others_artpulse_orgs',
        'publish_artpulse_orgs', 'read_private_artpulse_orgs',
        'delete_artpulse_orgs', 'delete_private_artpulse_orgs',
        'delete_published_artpulse_orgs', 'delete_others_artpulse_orgs',
        'edit_private_artpulse_orgs', 'edit_published_artpulse_orgs',
    ],
    'administrator' => [],
];

// Generate full capability sets for administrators
foreach ( $cpt_caps as $cpt ) {
    $plural = $cpt . 's';

    $roles_caps['administrator'] = array_merge(
        $roles_caps['administrator'],
        [
            "create_{$plural}",
            "edit_{$cpt}", "read_{$cpt}", "delete_{$cpt}",
            "edit_{$plural}", "edit_others_{$plural}", "publish_{$plural}",
            "read_private_{$plural}", "delete_{$plural}", "delete_private_{$plural}",
            "delete_published_{$plural}", "delete_others_{$plural}",
            "edit_private_{$plural}", "edit_published_{$plural}",
        ]
    );
}

// Assign capabilities to each role
foreach ( $roles_caps as $role_slug => $caps ) {
    $role = get_role( $role_slug );
    if ( $role ) {
        foreach ( $caps as $cap ) {
            $role->add_cap( $cap );
        }
    }
}
