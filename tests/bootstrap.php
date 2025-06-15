<?php
namespace EAD {
    function plugin_dir_path( $file ) {
        return dirname( $file ) . '/';
    }
}

namespace {
    // Define minimal WordPress constant so the plugin loads.
    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', dirname( __DIR__ ) . '/' );
    }

    require_once __DIR__ . '/Stubs.php';

    // Alias all stub functions and classes from the Tests namespace into the
    // global namespace so plugin code can call them as expected.
    foreach ( get_defined_functions()['user'] as $fn ) {
        if ( stripos( $fn, 'tests\\' ) === 0 ) {
            $global = substr( $fn, 6 );
            if ( ! function_exists( $global ) ) {
                eval( 'function ' . $global . '(...$args) { return \\' . $fn . '(...$args); }' );
            }
        }
    }
    foreach ( get_declared_classes() as $class ) {
        if ( stripos( $class, 'Tests\\' ) === 0 ) {
            $global = substr( $class, 6 );
            if ( ! class_exists( $global ) ) {
                class_alias( $class, $global );
            }
        }
    }

    require_once dirname( __DIR__ ) . '/src/Autoloader.php';
    \EAD\Autoloader::register();

    // Ensure the wpdb prefix property exists for tests.
    global $wpdb;
    if ( ! isset( $wpdb->prefix ) ) {
        $wpdb->prefix = '';
    }
}
