<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\BadgesEndpoint;

class BadgesEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$user_meta = [];
        Stubs::$logged_in = true;
    }

    public function test_get_user_badges_first_rsvp()
    {
        Stubs::$user_meta = [
            'ead_rsvps' => [1],
            'ead_favorites' => []
        ];

        $endpoint = new BadgesEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('get_user_badges');
        $method->setAccessible(true);

        $badges = $method->invoke($endpoint, 1);

        $this->assertCount(1, $badges);
        $this->assertSame('🎉 First RSVP', $badges[0]['label']);
    }

    public function test_get_user_badges_all_badges()
    {
        Stubs::$user_meta = [
            'ead_rsvps' => [1,2,3,4,5],
            'ead_favorites' => range(1,10)
        ];

        $endpoint = new BadgesEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('get_user_badges');
        $method->setAccessible(true);

        $badges = $method->invoke($endpoint, 1);

        $labels = array_column($badges, 'label');
        $this->assertContains('🎉 First RSVP', $labels);
        $this->assertContains('🏃 Frequent Attendee', $labels);
        $this->assertContains('💖 Super Fan', $labels);
        $this->assertContains('🌟 Power User', $labels);
    }
}

