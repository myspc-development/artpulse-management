<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ArtPulse\Core\MembershipUrls;

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID;

        public function __construct(int $id)
        {
            $this->ID = $id;
        }
    }
}

class MembershipUrlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->resetCache();
    }

    protected function tearDown(): void
    {
        $this->resetCache();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testGetPurchaseUrlFallsBackToMembershipPage(): void
    {
        Functions\when('apply_filters')->alias(static fn($hook, $value) => $value);
        Functions\when('get_page_by_path')->alias(static function ($path) {
            if ($path === 'membership') {
                return new WP_Post(123);
            }

            return null;
        });
        Functions\when('get_permalink')->alias(static fn($post) => 'https://example.test/membership/');
        Functions\when('get_option')->alias(static fn($key) => 0);
        Functions\when('home_url')->alias(static fn($path = '/') => 'https://example.test' . $path);
        Functions\when('add_query_arg')->alias(static fn($key, $value, $url) => $url . '?' . $key . '=' . $value);

        $url = MembershipUrls::getPurchaseUrl('Pro');

        $this->assertSame('https://example.test/membership/?level=pro', $url);
    }

    public function testGetPurchaseUrlRespectsFilters(): void
    {
        Functions\when('apply_filters')->alias(static function ($hook, $value, ...$args) {
            if ('artpulse/membership/purchase_base_url' === $hook) {
                return 'https://filters.test/purchase';
            }

            if ('artpulse/membership/purchase_url' === $hook) {
                return strtoupper((string) $value);
            }

            return $value;
        });
        Functions\when('add_query_arg')->alias(static fn($key, $value, $url) => $url . '?' . $key . '=' . $value);

        $url = MembershipUrls::getPurchaseUrl('Org');

        $this->assertSame('HTTPS://FILTERS.TEST/PURCHASE?LEVEL=ORG', $url);
    }

    private function resetCache(): void
    {
        $reflection = new ReflectionClass(MembershipUrls::class);
        $property   = $reflection->getProperty('purchaseBaseUrl');
        $property->setAccessible(true);
        $property->setValue(null);
    }
}

