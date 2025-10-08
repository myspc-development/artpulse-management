<?php

namespace ArtPulse\Tools\CLI;

use ArtPulse\Mobile\EventGeo;
use WP_CLI;
use WP_Query;

class BackfillEventGeo
{
    /**
     * Execute the command.
     */
    public static function handle(): void
    {
        $query = new WP_Query([
            'post_type'      => \ArtPulse\Core\PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        $count = 0;
        foreach ($query->posts as $event_id) {
            $event_id = (int) $event_id;
            EventGeo::sync($event_id);
            $count++;
        }

        WP_CLI::success(sprintf('Backfilled coordinates for %d events.', $count));
    }
}
