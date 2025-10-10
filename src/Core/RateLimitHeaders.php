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
     */
    public static function emit(array $headers): void
    {
        foreach ($headers as $name => $value) {
            if ('' === $name) {
                continue;
            }

            header(trim($name) . ': ' . trim((string) $value));
        }
    }
}

