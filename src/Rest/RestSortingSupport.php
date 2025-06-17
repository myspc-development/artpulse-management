<?php
namespace ArtPulse\Rest;

class RestSortingSupport {
    public static function register() {
        foreach (['ead_artist', 'ead_artwork', 'ead_event', 'ead_organization'] as $type) {
            add_filter("rest_{$type}_collection_params", function ($params) {
                $params['orderby']['enum'][] = 'meta_value';
                $params['meta_key'] = [
                    'description' => 'Meta key to sort by',
                    'type'        => 'string',
                    'required'    => false,
                ];
                return $params;
            });
        }
    }
}
