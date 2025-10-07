<?php

namespace ArtPulse\Frontend\Salient;

use ArtPulse\Core\ImageTools;
use ArtPulse\Core\PostTypeRegistrar;
use WP_Post;

class ImageFallback
{
    /**
     * @var array<int, true>
     */
    private static array $trackedAttachments = [];

    public static function register(): void
    {
        add_filter('post_thumbnail_id', [self::class, 'maybe_use_submission_image'], 10, 2);
        add_filter('wp_get_attachment_image_src', [self::class, 'maybe_adjust_image_src'], 10, 4);
    }

    /**
     * @param int|string $thumbnail_id
     * @param WP_Post|int|null $post
     * @return int|string
     */
    public static function maybe_use_submission_image($thumbnail_id, $post)
    {
        if ($thumbnail_id) {
            return $thumbnail_id;
        }

        if (!$post instanceof WP_Post) {
            return $thumbnail_id;
        }

        if (PostTypeRegistrar::EVENT_POST_TYPE !== $post->post_type) {
            return $thumbnail_id;
        }

        $images = (array) get_post_meta($post->ID, '_ap_submission_images', true);
        $fallback_id = (int) ($images[0] ?? 0);

        if ($fallback_id > 0) {
            self::$trackedAttachments[$fallback_id] = true;

            return $fallback_id;
        }

        return $thumbnail_id;
    }

    /**
     * @param array<int, int|string|bool>|false $image
     * @param int                               $attachment_id
     * @param string|int[]                      $size
     * @param bool                              $icon
     *
     * @return array<int, int|string|bool>|false
     */
    public static function maybe_adjust_image_src($image, int $attachment_id, $size, bool $icon)
    {
        if (!isset(self::$trackedAttachments[$attachment_id]) && $image && !empty($image[0])) {
            return $image;
        }

        $best = ImageTools::best_image_src($attachment_id);
        if (!$best) {
            return $image;
        }

        return [$best['url'], $best['width'], $best['height'], false];
    }
}
