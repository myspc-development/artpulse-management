<?php
namespace ArtPulse\Community;

class FavoritesRestController {
    public static function register() {
        register_rest_route('artpulse/v1', '/favorite', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle_toggle'],
            'permission_callback' => function() { return is_user_logged_in(); }
        ]);
        register_rest_route('artpulse/v1', '/favorites', [
            'methods' => 'GET',
            'callback' => [self::class, 'handle_get'],
            'permission_callback' => function() { return is_user_logged_in(); }
        ]);
    }

    public static function handle_toggle($request) {
        // TODO: Implement add/remove favorite logic here
        return [ 'success' => true ];
    }

    public static function handle_get($request) {
        // TODO: Implement fetching user favorites here
        return [];
    }
}

// Hook to REST API, using FQCN for Composer autoload
add_action('rest_api_init', [\ArtPulse\Community\FavoritesRestController::class, 'register']);
