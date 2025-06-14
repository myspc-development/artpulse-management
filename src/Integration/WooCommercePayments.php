<?php
namespace EAD\Integration;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCommercePayments {
    public static function register() {
        add_filter( 'woocommerce_add_cart_item_data', [ self::class, 'add_cart_item_data' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ self::class, 'store_order_item_meta' ], 10, 4 );
        add_action( 'woocommerce_order_status_completed', [ self::class, 'handle_order_completed' ] );
    }

    public static function generate_checkout_url( int $listing_id ): string {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '';
        }

        $settings   = get_option( 'artpulse_plugin_settings', [] );
        $product_id = isset( $settings['wc_featured_product_id'] ) ? absint( $settings['wc_featured_product_id'] ) : 0;
        if ( ! $product_id ) {
            return '';
        }

        $url = wc_get_checkout_url();
        $url = add_query_arg( [
            'add-to-cart'  => $product_id,
            'ead_listing_id' => $listing_id,
        ], $url );

        return esc_url( $url );
    }

    public static function add_cart_item_data( $cart_item_data, $product_id ) {
        if ( isset( $_GET['ead_listing_id'] ) ) {
            $cart_item_data['ead_listing_id'] = absint( $_GET['ead_listing_id'] );
        }

        return $cart_item_data;
    }

    public static function store_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['ead_listing_id'] ) ) {
            $item->add_meta_data( '_ead_listing_id', $values['ead_listing_id'], true );
        }
    }

    public static function handle_order_completed( $order_id ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $listing_id = $item->get_meta( '_ead_listing_id', true );
            if ( $listing_id ) {
                update_post_meta( $listing_id, '_ead_featured', '1' );
                update_post_meta( $listing_id, '_ead_featured_payment_status', 'paid' );
                $settings = get_option( 'artpulse_plugin_settings', [] );
                $days     = isset( $settings['featured_duration_days'] ) ? absint( $settings['featured_duration_days'] ) : 30;
                $expires  = strtotime( '+' . $days . ' days' );
                update_post_meta( $listing_id, '_ead_featured_expires', $expires );
            }
        }
    }
}
