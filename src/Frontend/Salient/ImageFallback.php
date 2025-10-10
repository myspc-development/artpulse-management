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
        add_filter('post_thumbnail_html', [self::class, 'maybe_render_placeholder'], 10, 5);
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
     * @param int|string                        $attachment_id
     * @param string|int[]                      $size
     * @param bool                              $icon
     *
     * @return array<int, int|string|bool>|false
     */
    public static function maybe_adjust_image_src($image, int|string $attachment_id, $size, bool $icon)
    {
        if (!is_numeric($attachment_id)) {
            return $image;
        }

        $attachment_id = (int) $attachment_id;

        if (!isset(self::$trackedAttachments[$attachment_id]) && $image && !empty($image[0])) {
            return $image;
        }

        $best = ImageTools::best_image_src($attachment_id);
        if (!$best) {
            return $image;
        }

        return [$best['url'], $best['width'], $best['height'], false];
    }

    /**
     * @param string $html
     * @param int    $post_id
     * @param int    $post_thumbnail_id
     * @param string|int[] $size
     * @param string|array<string, mixed> $attr
     */
    public static function maybe_render_placeholder($html, int $post_id, int $post_thumbnail_id, $size, $attr): string
    {
        if ('' !== trim((string) $html)) {
            return $html;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post || PostTypeRegistrar::EVENT_POST_TYPE !== $post->post_type) {
            return $html;
        }

        $classes = ['ap-event-placeholder'];

        if (is_string($size) && '' !== $size) {
            $classes[] = 'attachment-' . sanitize_html_class($size);
            $classes[] = 'size-' . sanitize_html_class($size);
        }

        $classes[] = 'wp-post-image';

        if (is_array($attr) && !empty($attr['class'])) {
            foreach (preg_split('/\s+/', (string) $attr['class']) as $class) {
                $class = trim($class);
                if ('' !== $class) {
                    $classes[] = $class;
                }
            }
        }

        $classes = array_unique(array_filter($classes));
        $class_attr = implode(' ', $classes);

        return sprintf('<div class="%s" aria-hidden="true"></div>', esc_attr($class_attr));
    }
}
