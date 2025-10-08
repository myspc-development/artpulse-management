<?php

namespace ArtPulse\Core;

use WP_User;

/**
 * Lightweight audit logger for role and membership transitions.
 */
class AuditLogger
{
    public static function info(string $action, array $context = []): void
    {
        self::log($action, $context);
    }

    /**
     * Log an action with structured context for easier debugging.
     *
     * @param string               $action
     * @param array<string, mixed> $context
     */
    public static function log(string $action, array $context = []): void
    {
        if (!function_exists('error_log')) {
            return;
        }

        $payload = [
            'timestamp' => gmdate('c'),
            'action'    => $action,
            'context'   => self::normalise_context($context),
        ];

        $message = '[ArtPulse] ' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        error_log($message);
    }

    /**
     * Ensure the context array can be serialised safely.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    protected static function normalise_context(array $context): array
    {
        array_walk($context, static function (&$value): void {
            if ($value instanceof WP_User) {
                $value = [
                    'ID'           => $value->ID,
                    'user_login'   => $value->user_login,
                    'display_name' => $value->display_name,
                    'roles'        => $value->roles,
                ];
                return;
            }

            if (is_object($value) || is_array($value)) {
                $value = json_decode(wp_json_encode($value), true);
            }
        });

        return $context;
    }
}
