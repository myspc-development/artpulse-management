<?php
use PHPUnit\Framework\TestCase;
use ArtPulse\Core\AnalyticsDashboard;

class AnalyticsDashboardTest extends TestCase
{
    public function testAnalyticsDashboardIsLoadable()
    {
        $this->assertTrue(method_exists(AnalyticsDashboard::class, 'register'));
    }
}
