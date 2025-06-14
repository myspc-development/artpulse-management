<?php
use PHPUnit\Framework\TestCase;

class RolesManagerTest extends TestCase
{
    public function test_roles_manager_alias_is_defined()
    {
        $this->assertTrue(class_exists('EAD\\Roles\\RolesManager'));
        $this->assertTrue(class_exists('EAD\\RolesManager'));
    }
}
