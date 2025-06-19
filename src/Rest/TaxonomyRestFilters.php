<?php
namespace ArtPulse\Rest;

class TaxonomyRestFilters
{
    public static function register()
    {
        add_action('rest_api_init', function () {
            $taxonomies = [
                'artpulse_artist'  => 'artist_specialty',
                'artpulse_artwork' => 'artwork_style',
                'artpulse_event'   => 'event_type',
                'artpulse_org'     => 'organization_category',
            ];

            foreach ($taxonomies as $post_type => $taxonomy) {
                add_filter("rest_{$post_type}_query", function ($args, $request) use ($taxonomy) {
                    $terms = $request->get_param('tax_' . $taxonomy);
                    if ($terms) {
                        $args['tax_query'] = [
                            [
                                'taxonomy' => $taxonomy,
                                'field'    => 'slug',
                                'terms'    => explode(',', $terms),
                            ],
                        ];
                    }
                    return $args;
                }, 10, 2);
            }
        });
    }
}
