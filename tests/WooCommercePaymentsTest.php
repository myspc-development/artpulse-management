<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Integration\WooCommercePayments;

class WooCommercePaymentsTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$actions = [];
        Stubs::$filters = [];
    }

    public function test_register_adds_hooks()
    {
        WooCommercePayments::register();

        $this->assertContains([
            'woocommerce_add_cart_item_data',
            [WooCommercePayments::class, 'add_cart_item_data'],
            10,
            2,
        ], Stubs::$filters);

        $this->assertContains([
            'woocommerce_checkout_create_order_line_item',
            [WooCommercePayments::class, 'store_order_item_meta'],
            10,
            4,
        ], Stubs::$actions);

        $this->assertContains([
            'woocommerce_order_status_completed',
            [WooCommercePayments::class, 'handle_order_completed'],
            10,
            1,
        ], Stubs::$actions);
    }
}
