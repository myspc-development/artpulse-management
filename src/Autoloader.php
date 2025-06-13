<?php
namespace EAD;

/**
 * Class Autoloader
 *
 * Automatically loads EAD plugin classes from the src directory.
 */
class Autoloader {
    /**
     * Register the autoloader.
     */
    public static function register() {
        spl_autoload_register([self::class, 'autoload']);
    }

    /**
     * The autoloader function.
     *
     * @param string $class The fully-qualified class name.
     */
    public static function autoload($class) {
        // Only load classes from this namespace
        if (strpos($class, __NAMESPACE__ . '\\') !== 0) {
            return;
        }

        // Remove the namespace prefix
        $relative_class = substr($class, strlen(__NAMESPACE__) + 1);

        // Convert namespace separators to directory separators
        $relative_path = str_replace('\\', '/', $relative_class);

        // Build the full path to the class file
        $file = plugin_dir_path( __FILE__ ) . $relative_path . '.php';  // Corrected path: removed 'src/'


        // Load the file if it exists
        if (file_exists($file)) {
            require_once $file;
        }
    }
}
