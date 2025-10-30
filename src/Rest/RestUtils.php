<?php

namespace ArtPulse\Rest;

use WP_Error;

/**
 * Shared helper utilities for REST controllers.
 */
final class RestUtils
{
    /**
     * Create a standardized WP_Error response for REST endpoints.
     */
    public static function error(string $code, string $message, int $status = 400, ?string $field = null): WP_Error
    {
        $data = ['status' => $status];

        if (null !== $field && '' !== $field) {
            $data['field'] = $field;
        }

        return new WP_Error($code, $message, $data);
    }
}
