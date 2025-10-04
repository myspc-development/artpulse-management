<?php
class WooCommerceIntegrationTest extends \WP_UnitTestCase {
    public function test_register_hooks_exists() {
        $this->assertTrue(method_exists(ArtPulse\Core\WooCommerceIntegration::class, 'register'));
    }
}
