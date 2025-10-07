<?php

namespace ArtPulse\Core;

class ImageTools
{
    /**
     * Return the best available image URL+width+height for an attachment.
     * Tries sizes in order, then falls back to 'full'.
     *
     * @param int   $attachment_id Attachment post ID.
     * @param array $preferred     Preferred sizes.
     *
     * @return array{url:string,width:int,height:int,size:string}|null
     */
    public static function best_image_src(int $attachment_id, array $preferred = ['large', 'medium_large', 'medium', 'thumbnail', 'full']): ?array
    {
        foreach ($preferred as $size) {
            $src = wp_get_attachment_image_src($attachment_id, $size);
            if ($src && is_array($src) && !empty($src[0])) {
                return [
                    'url'    => $src[0],
                    'width'  => isset($src[1]) ? (int) $src[1] : 0,
                    'height' => isset($src[2]) ? (int) $src[2] : 0,
                    'size'   => $size,
                ];
            }
        }

        $url = wp_get_attachment_url($attachment_id);

        return $url ? [
            'url'    => $url,
            'width'  => 0,
            'height' => 0,
            'size'   => 'full',
        ] : null;
    }
}
