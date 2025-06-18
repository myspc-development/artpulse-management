<?php
use PHPUnit\Framework\TestCase;
use ArtPulse\Core\FrontendMembershipPage;

class FrontendMembershipPageTest extends TestCase
{
    public function testFrontendMembershipPageRenders()
    {
        $this->assertTrue(method_exists(FrontendMembershipPage::class, 'register'));
    }
}
