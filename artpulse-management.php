<?php
/**
 * Plugin Name: ArtPulse Membership Management
 * Description: Handles user memberships, payments, and member admin functionality.
 * Version: 1.0.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define constants
define( 'EAD_PLUGIN_VERSION', '1.0.0' );
define( 'EAD_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'EAD_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Core functionality
require_once __DIR__ . '/membership-core.php';
require_once __DIR__ . '/functions.php';

// Admin-only logic
if ( is_admin() ) {
    \EAD\Admin\ManageMembers::register();

    add_action( 'admin_menu', function () {
        add_menu_page(
            __( 'Member Manager', 'artpulse-management' ),
            __( 'Members', 'artpulse-management' ),
            'manage_options',
            'ead-member-menu',
            [ '\EAD\Admin\ManageMembers', 'render_admin_page' ],
            'dashicons-groups',
            30
        );
    } );
}
