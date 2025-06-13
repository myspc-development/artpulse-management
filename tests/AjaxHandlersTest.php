<?php
use PHPUnit\Framework\TestCase;
use EAD\Plugin;

class AjaxHandlersTest extends TestCase
{
    public function test_handlers_loaded_on_init()
    {
        // Calling init should load ajax-handlers.php
        Plugin::init();

        $this->assertTrue(function_exists('ead_event_rsvp_ajax'));
        $this->assertTrue(function_exists('ead_load_states_handler'));
    }
}

