<?php
namespace EAD\Shortcodes;

/**
 * Simple honeypot utilities for forms.
 */
trait HoneypotTrait {
    protected static function render_honeypot( $atts = [] ) {
        if ( empty( $atts['honeypot'] ) ) {
            return '';
        }
        ob_start();
        ?>
        <div class="ead-honeypot" style="position:absolute;left:-10000px;">
            <label for="website_url_hp" aria-hidden="true"><?php esc_html_e( 'Leave this field empty', 'artpulse-management' ); ?></label>
            <input type="text" name="website_url_hp" id="website_url_hp" autocomplete="off">
        </div>
        <?php
        return ob_get_clean();
    }

    protected static function honeypot_triggered() {
        return ! empty( $_POST['website_url_hp'] );
    }
}
