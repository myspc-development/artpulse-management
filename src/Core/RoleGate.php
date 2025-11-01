<?php
namespace ArtPulse\Core;

class RoleGate {
    public static function user_can_access( string $role, int $user_id = 0 ): bool {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }

        switch ( $role ) {
            case 'member':
                return is_user_logged_in();
            case 'artist':
                return user_can( $user_id, 'edit_posts' ) || user_can( $user_id, 'ap_is_artist' );
            case 'org':
                return user_can( $user_id, 'edit_pages' ) || user_can( $user_id, 'ap_is_org' );
            default:
                return false;
        }
    }
}
