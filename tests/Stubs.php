<?php
namespace Tests;

class Stubs {
    public static array $posts = [];
    public static array $last_query_args = [];
    public static array $caps = [];
    public static int $current_user_id = 1;
    public static array $user_meta = [];
    public static array $meta = [];
    public static array $updated_posts = [];
    public static array $transients = [];
    public static $db_result = null;
    public static array $db_last_query = [];
    public static string $redirect = '';
    public static bool $logged_in = true;
}

function is_user_logged_in(): bool {
    return \Tests\Stubs::$logged_in;
}

function current_user_can(string $cap): bool {
    return in_array($cap, \Tests\Stubs::$caps, true);
}

function get_current_user_id(): int {
    return \Tests\Stubs::$current_user_id;
}

function get_user_meta($user_id, string $key, $single = false) {
    return \Tests\Stubs::$user_meta[$key] ?? '';
}

function get_post_meta($post_id, string $key, $single = false) {
    return \Tests\Stubs::$meta[$post_id][$key] ?? '';
}

function update_post_meta($post_id, string $key, $value) {
    \Tests\Stubs::$meta[$post_id][$key] = $value;
    return true;
}

function wp_update_post(array $args) {
    if (isset($args['ID'])) {
        \Tests\Stubs::$updated_posts[$args['ID']] = $args;
        return $args['ID'];
    }
    return 0;
}

function get_posts(array $args = []) {
    \Tests\Stubs::$last_query_args = $args;
    return \Tests\Stubs::$posts;
}

function wp_nonce_field($action, $name = '_wpnonce') {
    echo "<input type='hidden' name='" . htmlspecialchars($name, ENT_QUOTES) . "' value='nonce'>";
}

function wp_create_nonce($action) { return 'nonce'; }
function wp_verify_nonce($nonce, $action = '') { return true; }

function sanitize_text_field($text) { return is_string($text) ? trim($text) : $text; }
function sanitize_key($key) { return preg_replace('/[^a-z0-9_]/', '', strtolower($key)); }

function set_transient($key, $value, $expiration = 0) { \Tests\Stubs::$transients[$key] = $value; }
function get_transient($key) { return \Tests\Stubs::$transients[$key] ?? false; }
function delete_transient($key) { unset(\Tests\Stubs::$transients[$key]); }

function wp_redirect($location) { \Tests\Stubs::$redirect = $location; }
function wp_safe_redirect($location) { \Tests\Stubs::$redirect = $location; }
function wp_get_referer() { return 'http://example.com/'; }

function add_action($hook, $callback) {}
function add_filter($tag, $callback) {}
function add_shortcode($tag, $cb) {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script() {}
function register_activation_hook($file, $callback) {}

function admin_url($path = '') { return $path; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return dirname($file) . '/'; }
function rest_url($path = '') { return $path; }

function add_query_arg($params, $url = '') {
    $parts = parse_url($url);
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    foreach ((array)$params as $k => $v) {
        $query[$k] = $v;
    }
    $base = $parts['path'] ?? '';
    return $base . '?' . http_build_query($query);
}

function remove_query_arg($keys, $url = '') {
    $parts = parse_url($url);
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    foreach ((array)$keys as $k) {
        unset($query[$k]);
    }
    $base = $parts['path'] ?? '';
    return $base . (empty($query) ? '' : '?' . http_build_query($query));
}

function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_textarea($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_url($url) { return $url; }
function esc_html__($t, $d = null) { return $t; }
function esc_attr__($t, $d = null) { return $t; }
function esc_html_e($t, $d = null) { echo $t; }
function esc_attr_e($t, $d = null) { echo $t; }
function __($t, $d = null) { return $t; }

function get_edit_post_link($id) { return "edit.php?post=$id"; }
function get_permalink($id) { return "post.php?post=$id"; }
function get_the_title($id) {
    if (isset(\Tests\Stubs::$updated_posts[$id]['post_title'])) {
        return \Tests\Stubs::$updated_posts[$id]['post_title'];
    }
    foreach (\Tests\Stubs::$posts as $p) {
        if (is_object($p) && $p->ID == $id && isset($p->post_title)) {
            return $p->post_title;
        }
    }
    return "Post $id";
}

class WP_REST_Controller {}
class WP_REST_Server { const READABLE = 'GET'; }
class WP_REST_Request {}
class WP_REST_Response {
    public function __construct(public $data = null, public $status = 200) {}
}
class WP_Error {
    public function __construct(public $code = '', public $message = '', public $data = null) {}
}

$GLOBALS['wpdb'] = new class {
    public string $ead_rsvps = 'ead_rsvps';
    public function prepare($query, ...$args) {
        \Tests\Stubs::$db_last_query = [$query, $args];
        return vsprintf($query, $args);
    }
    public function get_var($sql) {
        return \Tests\Stubs::$db_result;
    }
};
