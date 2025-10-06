<?php

namespace ArtPulse\Rest;

use ArtPulse\Community\FavoritesManager;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

class EventsController
{
    private const CACHE_VERSION_OPTION = 'ap_events_cache_version';

    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'register']);
        add_action('save_post_artpulse_event', [self::class, 'purge_cache']);
        add_action('deleted_post', [self::class, 'purge_cache']);
        add_action('set_object_terms', [self::class, 'purge_cache']);
    }

    public static function register(): void
    {
        register_rest_route('artpulse/v1', '/events', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_events'],
            'permission_callback' => '__return_true',
            'args'                => self::get_collection_args(),
        ]);

        register_rest_route('artpulse/v1', '/event/(?P<id>\\d+)', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_event'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'is_numeric',
                ],
            ],
        ]);

        register_rest_route('artpulse/v1', '/events.ics', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_ics'],
            'permission_callback' => '__return_true',
            'args'                => self::get_collection_args(),
        ]);
    }

    public static function get_events(WP_REST_Request $request): WP_REST_Response
    {
        $params = self::normalize_request_params($request);
        $result = self::fetch_events($params);

        $response = rest_ensure_response([
            'events'     => $result['events'],
            'pagination' => $result['pagination'],
        ]);

        $response->header('X-WP-Total', (string) $result['pagination']['total']);
        $response->header('X-WP-TotalPages', (string) $result['pagination']['total_pages']);

        return $response;
    }

    public static function get_event(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $post = get_post($id);

        if (!$post instanceof WP_Post || 'artpulse_event' !== $post->post_type || 'publish' !== $post->post_status) {
            return new WP_Error('not_found', __('Event not found.', 'artpulse-management'), ['status' => 404]);
        }

        $event = self::prepare_event($post->ID);
        $event['occurrences'] = self::expand_occurrences($event, null, null);

        return rest_ensure_response($event);
    }

    public static function get_ics(WP_REST_Request $request): WP_REST_Response
    {
        $params = self::normalize_request_params($request);
        $result = self::fetch_events($params, false);

        $ics = self::generate_ics($result['events']);
        $response = new WP_REST_Response($ics);
        $response->header('Content-Type', 'text/calendar; charset=utf-8');
        $response->header('Content-Disposition', 'attachment; filename="events.ics"');

        return $response;
    }

    /**
     * Query events for external consumers (shortcodes, templates, tests).
     *
     * @param array<string, mixed> $args
     * @param bool                 $apply_pagination  Apply pagination after expansion.
     *
     * @return array<string, mixed>
     */
    public static function fetch_events(array $args = [], bool $apply_pagination = true): array
    {
        $defaults = [
            'start'      => null,
            'end'        => null,
            'category'   => [],
            'org'        => null,
            'favorites'  => false,
            'per_page'   => 20,
            'page'       => 1,
            'orderby'    => 'start',
            'order'      => 'ASC',
            'event_id'   => null,
        ];

        $args = array_merge($defaults, $args);

        $cache_key = self::get_cache_key($args, $apply_pagination);
        $cached    = wp_cache_get($cache_key, 'artpulse_events');
        if (false !== $cached) {
            return $cached;
        }

        $range_start = $args['start'] instanceof DateTimeImmutable ? $args['start'] : self::parse_date($args['start']);
        $range_end   = $args['end'] instanceof DateTimeImmutable ? $args['end'] : self::parse_date($args['end']);

        $query_args = [
            'post_type'      => 'artpulse_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
        ];

        if (!empty($args['category'])) {
            $slugs = array_map('sanitize_title', (array) $args['category']);
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'artpulse_event_type',
                    'field'    => 'slug',
                    'terms'    => $slugs,
                ],
            ];
        }

        if (!empty($args['org'])) {
            $query_args['meta_query'][] = [
                'key'   => '_ap_event_organization',
                'value' => (int) $args['org'],
            ];
        }

        if (!empty($args['event_id'])) {
            $query_args['post__in'] = [(int) $args['event_id']];
        }

        $query = new WP_Query($query_args);

        $events = [];
        $current_user_id = get_current_user_id();

        foreach ($query->posts as $post_id) {
            $event = self::prepare_event((int) $post_id, $current_user_id);
            if (empty($event['start'])) {
                continue;
            }

            $occurrences = self::expand_occurrences($event, $range_start, $range_end);
            if (empty($occurrences)) {
                continue;
            }

            foreach ($occurrences as $index => $occurrence) {
                if ($args['favorites'] && empty($occurrence['favorite'])) {
                    continue;
                }

                $events[] = $occurrence + [
                    'occurrence'  => $index,
                    'categories'  => $event['categories'],
                    'categoryNames' => $event['categoryNames'],
                    'organization' => $event['organization'],
                    'organizationName' => $event['organizationName'],
                    'thumbnail'   => $event['thumbnail'],
                    'excerpt'     => $event['excerpt'],
                    'schema'      => $event['schema'],
                    'description' => $event['excerpt'],
                ];
            }
        }

        usort($events, static function (array $a, array $b) use ($args): int {
            $direction = 'DESC' === strtoupper((string) $args['order']) ? -1 : 1;
            return $direction * strcmp((string) ($a[$args['orderby']] ?? ''), (string) ($b[$args['orderby']] ?? ''));
        });

        $total        = count($events);
        $per_page     = max(1, (int) $args['per_page']);
        $page         = max(1, (int) $args['page']);
        $total_pages  = (int) max(1, ceil($total / $per_page));
        $paged_events = $events;

        if ($apply_pagination) {
            $offset       = ($page - 1) * $per_page;
            $paged_events = array_slice($events, $offset, $per_page);
        }

        $result = [
            'events'     => array_values($paged_events),
            'pagination' => [
                'total'       => $total,
                'total_pages' => $total_pages,
                'per_page'    => $per_page,
                'current'     => $page,
            ],
        ];

        wp_cache_set($cache_key, $result, 'artpulse_events', HOUR_IN_SECONDS);

        return $result;
    }

    private static function get_collection_args(): array
    {
        return [
            'start'     => [
                'sanitize_callback' => [self::class, 'sanitize_date'],
            ],
            'end'       => [
                'sanitize_callback' => [self::class, 'sanitize_date'],
            ],
            'category'  => [
                'sanitize_callback' => [self::class, 'sanitize_array_param'],
            ],
            'org'       => [
                'sanitize_callback' => 'absint',
            ],
            'favorites' => [
                'sanitize_callback' => static fn($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            ],
            'per_page'  => [
                'sanitize_callback' => 'absint',
                'default'           => 20,
            ],
            'page'      => [
                'sanitize_callback' => 'absint',
                'default'           => 1,
            ],
            'orderby'   => [
                'sanitize_callback' => [self::class, 'sanitize_orderby'],
                'default'           => 'start',
            ],
            'order'     => [
                'sanitize_callback' => static fn($value) => strtoupper($value) === 'DESC' ? 'DESC' : 'ASC',
                'default'           => 'ASC',
            ],
            'event_id'  => [
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    private static function normalize_request_params(WP_REST_Request $request): array
    {
        $category = $request->get_param('category');
        if (is_string($category)) {
            $category = [$category];
        }

        return [
            'start'     => $request->get_param('start'),
            'end'       => $request->get_param('end'),
            'category'  => $category ?? [],
            'org'       => $request->get_param('org'),
            'favorites' => (bool) $request->get_param('favorites'),
            'per_page'  => $request->get_param('per_page'),
            'page'      => $request->get_param('page'),
            'orderby'   => $request->get_param('orderby'),
            'order'     => $request->get_param('order'),
            'event_id'  => $request->get_param('event_id'),
        ];
    }

    private static function prepare_event(int $post_id, int $current_user_id = 0): array
    {
        $start = get_post_meta($post_id, '_ap_event_start', true);
        $end   = get_post_meta($post_id, '_ap_event_end', true);

        if (!$start) {
            $fallback = get_post_meta($post_id, '_ap_event_date', true);
            if ($fallback) {
                $start = $fallback;
            }
        }

        $timezone = get_post_meta($post_id, '_ap_event_timezone', true);
        $timezone = $timezone ?: wp_timezone_string();
        $location = get_post_meta($post_id, '_ap_event_location', true);
        $cost     = get_post_meta($post_id, '_ap_event_cost', true);
        $all_day  = (bool) get_post_meta($post_id, '_ap_event_all_day', true);
        $org_id   = (int) get_post_meta($post_id, '_ap_event_organization', true);
        $org_name = $org_id ? get_the_title($org_id) : '';

        $thumb_id = get_post_thumbnail_id($post_id);
        $thumbnail = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '';
        $excerpt   = wp_strip_all_tags(get_the_excerpt($post_id));

        $categories = wp_get_post_terms($post_id, 'artpulse_event_type', ['fields' => 'slugs']);
        $category_names = wp_get_post_terms($post_id, 'artpulse_event_type', ['fields' => 'names']);

        $is_favorited = false;
        if ($current_user_id > 0) {
            $is_favorited = FavoritesManager::is_favorited($current_user_id, $post_id, 'artpulse_event');
        }

        $schema = [
            '@context'  => 'https://schema.org',
            '@type'     => 'Event',
            'name'      => get_the_title($post_id),
            'startDate' => $start ?: '',
            'endDate'   => $end ?: $start,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'location'  => [
                '@type'   => 'Place',
                'name'    => $location,
            ],
            'organizer' => $org_name ? [
                '@type' => 'Organization',
                'name'  => $org_name,
                'url'   => $org_id ? get_permalink($org_id) : '',
            ] : null,
            'offers'    => $cost ? [
                '@type' => 'Offer',
                'price' => $cost,
            ] : null,
            'url'       => get_permalink($post_id),
        ];

        $schema = array_filter($schema);

        return [
            'id'               => $post_id,
            'title'            => get_the_title($post_id),
            'start'            => $start ? self::format_iso($start, $timezone) : '',
            'end'              => $end ? self::format_iso($end, $timezone) : '',
            'allDay'           => $all_day,
            'timezone'         => $timezone,
            'location'         => $location,
            'cost'             => $cost,
            'favorite'         => $is_favorited,
            'url'              => get_permalink($post_id),
            'categories'       => array_map('sanitize_title', $categories),
            'categoryNames'    => array_map('sanitize_text_field', $category_names),
            'organization'     => $org_id,
            'organizationName' => $org_name,
            'thumbnail'        => $thumbnail,
            'excerpt'          => $excerpt,
            'schema'           => $schema,
            'recurrence'       => get_post_meta($post_id, '_ap_event_recurrence', true),
        ];
    }

    private static function expand_occurrences(array $event, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end): array
    {
        $start = self::parse_date($event['start']);
        if (!$start instanceof DateTimeImmutable) {
            return [];
        }

        $end = self::parse_date($event['end']);
        if (!$end instanceof DateTimeImmutable) {
            $end = $event['allDay'] ? $start : $start->add(new DateInterval('PT1H'));
        }

        $occurrences = [];
        $base = [
            'id'        => $event['id'],
            'title'     => $event['title'],
            'allDay'    => $event['allDay'],
            'timezone'  => $event['timezone'],
            'location'  => $event['location'],
            'cost'      => $event['cost'],
            'url'       => $event['url'],
            'favorite'  => $event['favorite'],
            'start'     => $start->format(DateTimeInterface::ATOM),
            'end'       => $end->format(DateTimeInterface::ATOM),
        ];

        $rule = $event['recurrence'];
        if (!$rule) {
            if (self::occurrence_in_range($start, $end, $range_start, $range_end)) {
                $occurrences[] = $base;
            }

            return $occurrences;
        }

        $parsed = self::parse_recurrence_rule($rule);
        if (!$parsed) {
            if (self::occurrence_in_range($start, $end, $range_start, $range_end)) {
                $occurrences[] = $base;
            }

            return $occurrences;
        }

        $frequency = $parsed['freq'];
        $interval  = max(1, (int) $parsed['interval']);
        $until     = $parsed['until'];
        $count     = $parsed['count'];

        $cursor_start = $start;
        $cursor_end   = $end;
        $index        = 0;

        while (true) {
            if ($count && $index >= $count) {
                break;
            }

            if ($until && $cursor_start > $until) {
                break;
            }

            if (self::occurrence_in_range($cursor_start, $cursor_end, $range_start, $range_end)) {
                $occurrences[] = $base + [
                    'start' => $cursor_start->format(DateTimeInterface::ATOM),
                    'end'   => $cursor_end->format(DateTimeInterface::ATOM),
                ];
            }

            $index++;
            switch ($frequency) {
                case 'DAILY':
                    $cursor_start = $cursor_start->add(new DateInterval('P' . $interval . 'D'));
                    $cursor_end   = $cursor_end->add(new DateInterval('P' . $interval . 'D'));
                    break;
                case 'WEEKLY':
                    $cursor_start = $cursor_start->add(new DateInterval('P' . $interval . 'W'));
                    $cursor_end   = $cursor_end->add(new DateInterval('P' . $interval . 'W'));
                    break;
                case 'MONTHLY':
                    $cursor_start = $cursor_start->add(new DateInterval('P' . $interval . 'M'));
                    $cursor_end   = $cursor_end->add(new DateInterval('P' . $interval . 'M'));
                    break;
                default:
                    $cursor_start = $cursor_start->add(new DateInterval('P' . $interval . 'D'));
                    $cursor_end   = $cursor_end->add(new DateInterval('P' . $interval . 'D'));
                    break;
            }

            if ($range_end && $cursor_start > $range_end->add(new DateInterval('P1D'))) {
                break;
            }
        }

        return $occurrences;
    }

    private static function occurrence_in_range(DateTimeImmutable $start, DateTimeImmutable $end, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end): bool
    {
        if ($range_start && $end < $range_start) {
            return false;
        }

        if ($range_end && $start > $range_end) {
            return false;
        }

        return true;
    }

    private static function parse_recurrence_rule(string $rule): ?array
    {
        $rule = trim($rule);
        if ('' === $rule) {
            return null;
        }

        if (str_starts_with($rule, 'RRULE:')) {
            $rule = substr($rule, 6);
        }

        $parts = wp_parse_list($rule);
        $data  = [
            'freq'     => 'DAILY',
            'interval' => 1,
            'count'    => null,
            'until'    => null,
        ];

        foreach ($parts as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $part, 2));
            $key   = strtoupper($key);
            $value = strtoupper($value);

            switch ($key) {
                case 'FREQ':
                    if (in_array($value, ['DAILY', 'WEEKLY', 'MONTHLY'], true)) {
                        $data['freq'] = $value;
                    }
                    break;
                case 'INTERVAL':
                    $data['interval'] = (int) $value ?: 1;
                    break;
                case 'COUNT':
                    $data['count'] = (int) $value ?: null;
                    break;
                case 'UNTIL':
                    $until = self::parse_date($value);
                    if ($until instanceof DateTimeImmutable) {
                        $data['until'] = $until;
                    }
                    break;
            }
        }

        return $data;
    }

    private static function sanitize_date($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $parsed = self::parse_date($value);
        return $parsed ? $parsed->format(DateTimeInterface::ATOM) : null;
    }

    private static function sanitize_array_param($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_values(array_filter(array_map('sanitize_text_field', (array) $value)));
    }

    private static function sanitize_orderby($value): string
    {
        $allowed = ['start', 'end', 'title'];
        $value   = strtolower((string) $value);

        return in_array($value, $allowed, true) ? $value : 'start';
    }

    private static function parse_date($value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return new DateTimeImmutable('@' . (int) $value);
            }

            $tz = new DateTimeZone('UTC');
            return new DateTimeImmutable((string) $value, $tz);
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function format_iso(string $value, string $timezone): string
    {
        $date = self::parse_date($value);
        if (!$date) {
            return '';
        }

        try {
            $tz = new DateTimeZone($timezone);
        } catch (\Exception $e) {
            $tz = new DateTimeZone('UTC');
        }

        return $date->setTimezone($tz)->format(DateTimeInterface::ATOM);
    }

    private static function get_cache_key(array $args, bool $apply_pagination): string
    {
        $version = (int) get_option(self::CACHE_VERSION_OPTION, 1);
        return $version . ':' . md5(wp_json_encode($args) . ($apply_pagination ? ':1' : ':0'));
    }

    public static function purge_cache(): void
    {
        $version = (int) get_option(self::CACHE_VERSION_OPTION, 1);
        update_option(self::CACHE_VERSION_OPTION, $version + 1, false);
    }

    private static function generate_uid(array $event): string
    {
        return sprintf('%s-%s@artpulse', $event['id'], md5($event['start'] . $event['end']));
    }

    public static function generate_ics(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ArtPulse//Events//EN',
            'CALSCALE:GREGORIAN',
        ];

        foreach ($events as $event) {
            $start = self::parse_date($event['start']);
            $end   = self::parse_date($event['end']);
            if (!$start) {
                continue;
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . self::generate_uid($event);
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');

            if (!empty($event['allDay'])) {
                $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
                if ($end) {
                    $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
                }
            } else {
                $lines[] = 'DTSTART:' . $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
                if ($end) {
                    $lines[] = 'DTEND:' . $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
                }
            }

            $lines[] = 'SUMMARY:' . self::escape_ics_text($event['title'] ?? '');
            if (!empty($event['location'])) {
                $lines[] = 'LOCATION:' . self::escape_ics_text($event['location']);
            }
            if (!empty($event['description'])) {
                $lines[] = 'DESCRIPTION:' . self::escape_ics_text($event['description']);
            }
            if (!empty($event['url'])) {
                $lines[] = 'URL;VALUE=URI:' . esc_url_raw($event['url']);
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private static function escape_ics_text(string $value): string
    {
        $escaped = str_replace(['\\', ',', ';', "\n", "\r"], ['\\\\', '\\,', '\\;', '\\n', ''], $value);
        return wp_strip_all_tags($escaped);
    }
}
