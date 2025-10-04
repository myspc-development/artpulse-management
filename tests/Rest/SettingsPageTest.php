<?php
class SettingsPageTest extends \WP_UnitTestCase {
    public function test_settings_option_can_be_saved_and_loaded() {
        $key = 'artpulse_settings';
        $settings = ['version' => 'test-version'];
        update_option($key, $settings);
        $loaded = get_option($key);
        $this->assertEquals('test-version', $loaded['version']);
    }
}
