<?php

namespace ArtPulse\Core;

/**
 * Shared helpers for emitting RFC-compliant rate limit headers.
 */
final class RateLimitHeaders
{
    /**
     * Build a consistent header map for rate limit responses.
     *
     * @return array<string, string>
     */
    public static function build(int $limit, int $remaining, int $resetEpoch, ?int $retryAfter = null): array
    {
        $headers = [
            'X-RateLimit-Limit'     => (string) max(0, $limit),
            'X-RateLimit-Remaining' => (string) max(0, $remaining),
            'X-RateLimit-Reset'     => (string) max(0, $resetEpoch),
        ];

        if (null !== $retryAfter) {
            $headers['Retry-After'] = (string) max(0, $retryAfter);
        }

        return $headers;
    }

    /**
     * Emit headers for traditional (non-REST) responses.
     *
     * Returns the normalized header map so callers can also attach the same
     * values to structured responses (e.g. WP_Error data or REST responses).
     *
     * @return array<string, string>
     */
    public static function emit(int $limit, int $remaining, ?int $retryAfter, int $resetEpoch): array
    {
        $headers = self::build($limit, $remaining, $resetEpoch, $retryAfter);

        self::output($headers);

        return $headers;
    }

    /**
     * Emit a prepared map of headers.
     *
     * @param array<string, string> $headers
     */
    private static function output(array $headers): void
    {
        foreach ($headers as $name => $value) {
            if ('' === $name) {
                continue;
            }

            header(trim($name) . ': ' . trim((string) $value));
        }
    }
}

