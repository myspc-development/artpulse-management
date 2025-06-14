<?php
namespace EAD\Admin;

class BookingsAdmin {
    public static function render_admin_page() {
        $list_table = new BookingsListTable();

        if ( 'post' === strtolower( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            check_admin_referer( 'ead_bookings_bulk' );
            $list_table->process_bulk_action();
        }

        $list_table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'ArtPulse Bookings', 'artpulse-management' ); ?></h1>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ead-organization-dashboard' ) ); ?>" class="button"><?php esc_html_e( 'View Organization Dashboard', 'artpulse-management' ); ?></a></p>
            <form method="post">
                <?php wp_nonce_field( 'ead_bookings_bulk' ); ?>
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ?? 'artpulse-bookings' ); ?>" />
                <ul class="subsubsub">
                    <?php
                    $views = $list_table->views();
                    echo implode( ' | ', $views );
                    ?>
                </ul>
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }
}
