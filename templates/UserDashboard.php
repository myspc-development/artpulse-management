<?php
use EAD\Dashboard\UserDashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo UserDashboard::render();
