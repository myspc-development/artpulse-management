<?php

namespace ArtPulse\Core;

use function sanitize_key;

/**
 * Utilities for normalising upgrade review types.
 */
final class UpgradeType
{
    /**
     * Convert an upgrade type or alias into the canonical value used by the repository.
     */
    public static function normalise(string $type): ?string
    {
        $key = sanitize_key($type);

        return match ($key) {
            UpgradeReviewRepository::TYPE_ORG,
            UpgradeReviewRepository::TYPE_ORG_UPGRADE,
            'organization',
            'organisation',
            'org',
            'orgs',
            'artpulse_org',
            'artpulse_organization',
            'ap_org',
            'ap_org_manager' => UpgradeReviewRepository::TYPE_ORG,
            UpgradeReviewRepository::TYPE_ARTIST,
            UpgradeReviewRepository::TYPE_ARTIST_UPGRADE,
            'artist',
            'artists',
            'ap_artist',
            'artpulse_artist' => UpgradeReviewRepository::TYPE_ARTIST,
            default => null,
        };
    }

    /**
     * Return the canonical type along with any legacy aliases.
     *
     * @return string[]
     */
    public static function expand(string $type): array
    {
        $normalised = self::normalise($type);

        if (null === $normalised) {
            return [];
        }

        $values = [$normalised];

        if (UpgradeReviewRepository::TYPE_ORG === $normalised) {
            $values[] = UpgradeReviewRepository::TYPE_ORG_UPGRADE;
        } elseif (UpgradeReviewRepository::TYPE_ARTIST === $normalised) {
            $values[] = UpgradeReviewRepository::TYPE_ARTIST_UPGRADE;
        }

        return array_values(array_unique($values));
    }
}
