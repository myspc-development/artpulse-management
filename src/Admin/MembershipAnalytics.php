<?php
namespace EAD\Admin;

class MembershipAnalytics {
    public static function render_admin_page() {
        $stats = self::get_member_stats();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Membership Analytics', 'artpulse-management' ); ?></h1>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Metric', 'artpulse-management' ); ?></th>
                        <th><?php esc_html_e( 'Count', 'artpulse-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e( 'Total Members', 'artpulse-management' ); ?></td>
                        <td><?php echo esc_html( $stats['total'] ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Active Members', 'artpulse-management' ); ?></td>
                        <td><?php echo esc_html( $stats['active'] ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Expired Members', 'artpulse-management' ); ?></td>
                        <td><?php echo esc_html( $stats['expired'] ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php esc_html_e( 'Members by Level', 'artpulse-management' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Level', 'artpulse-management' ); ?></th>
                        <th><?php esc_html_e( 'Count', 'artpulse-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $stats['levels'] as $level => $count ) : ?>
                    <tr>
                        <td><?php echo esc_html( ucfirst( $level ) ); ?></td>
                        <td><?php echo esc_html( $count ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function get_member_stats(): array {
        $args      = [
            'meta_query' => [ [ 'key' => 'membership_level', 'compare' => 'EXISTS' ] ],
            'number'     => -1,
            'fields'     => 'ID',
        ];
        $user_ids  = get_users( $args );
        $total     = count( $user_ids );
        $now       = current_time( 'mysql' );
        $active    = 0;
        $expired   = 0;
        $levels    = [];

        foreach ( $user_ids as $uid ) {
            $level = get_user_meta( $uid, 'membership_level', true );
            $end   = get_user_meta( $uid, 'membership_end_date', true );

            if ( ! isset( $levels[ $level ] ) ) {
                $levels[ $level ] = 0;
            }
            $levels[ $level ]++;

            if ( $end && $end < $now ) {
                $expired++;
            } else {
                $active++;
            }
        }
        ksort( $levels );

        return [
            'total'   => $total,
            'active'  => $active,
            'expired' => $expired,
            'levels'  => $levels,
        ];
    }
}
