<?php

class AnalyticsManagerTest extends WP_UnitTestCase {
    public function test_log_event_function_defined() {
        $this->assertTrue(method_exists(ArtPulse\Core\AnalyticsManager::class, 'register'));
    }
}
