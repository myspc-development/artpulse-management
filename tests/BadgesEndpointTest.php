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
            'ead_rsvps' => [1]
        ];
        Stubs::$meta = [
            1 => ['event_date' => '2023-08-01']
        ];

        $endpoint = new BadgesEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('get_user_badges');
        $method->setAccessible(true);

        $badges = $method->invoke($endpoint, 1);

        $this->assertCount(1, $badges);
        $this->assertSame('🎉 Rookie', $badges[0]['label']);
    }

    public function test_get_user_badges_all_badges()
    {
        Stubs::$user_meta = [
            'ead_rsvps' => range(1,10)
        ];
        Stubs::$meta = [];
        $date = strtotime('2023-01-01');
        for ($i = 1; $i <= 10; $i++) {
            Stubs::$meta[$i] = ['event_date' => date('Y-m-d', $date)];
            $date = strtotime('+1 day', $date);
        }

        $endpoint = new BadgesEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('get_user_badges');
        $method->setAccessible(true);

        $badges = $method->invoke($endpoint, 1);

        $labels = array_column($badges, 'label');
        $this->assertContains('🎉 Rookie', $labels);
        $this->assertContains('🧭 Explorer', $labels);
        $this->assertContains('💜 Superfan', $labels);
        $this->assertContains('🔥 Streak Master', $labels);
    }
}

