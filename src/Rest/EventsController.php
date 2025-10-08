<?php

namespace ArtPulse\Rest;

use ArtPulse\Community\FavoritesManager;
use ArtPulse\Core\ImageTools;
use ArtPulse\Core\PostTypeRegistrar;
use ArtPulse\Integration\Recurrence\RecurrenceExpander;
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
        add_action('save_post_' . PostTypeRegistrar::EVENT_POST_TYPE, [self::class, 'purge_cache']);
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
            'schema'              => [self::class, 'get_events_schema'],
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
            'schema'              => [self::class, 'get_event_schema'],
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
        $result = self::fetch_events($params, true);

        $payload = [
            'events'     => $result['events'],
            'pagination' => $result['pagination'],
            'truncated'  => $result['truncated'],
        ];

        $etag = self::build_etag($payload);
        if (self::is_not_modified($request, $etag)) {
            return new WP_REST_Response(null, 304, ['ETag' => $etag]);
        }

        $response = rest_ensure_response($payload);
        $response->header('ETag', $etag);
        $response->header('X-WP-Total', (string) $result['pagination']['total']);
        $response->header('X-WP-TotalPages', (string) $result['pagination']['total_pages']);

        if (!empty($result['truncated'])) {
            $response->header('X-ArtPulse-Truncated', '1');
        }

        return $response;
    }

    public static function get_event(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $post = get_post($id);

        if (!$post instanceof WP_Post || PostTypeRegistrar::EVENT_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status) {
            return new WP_Error('not_found', __('Event not found.', 'artpulse-management'), ['status' => 404]);
        }

        $event = self::prepare_event($post->ID);
        $expansion = self::expand_occurrences($event, null, null);
        $event['occurrences'] = $expansion['occurrences'];
        $event['occurrencesTruncated'] = $expansion['truncated'];

        return rest_ensure_response($event);
    }

    public static function get_ics(WP_REST_Request $request): WP_REST_Response
    {
        $params      = self::normalize_request_params($request);
        $fingerprint = $params['cache_fingerprint'] ?? [];

        $fingerprint['user'] = !empty($params['favorites']) ? (int) get_current_user_id() : 0;
        $cache_key           = self::get_cache_key($fingerprint, false, 'events.ics');

        $ics = self::cache_get($cache_key);
        if (false === $ics) {
            $result = self::fetch_events($params, false);
            $ics    = self::generate_ics($result['events']);
            self::cache_set($cache_key, $ics);
        }

        $etag = self::build_etag($ics);
        if (self::is_not_modified($request, $etag)) {
            return new WP_REST_Response(null, 304, [
                'ETag'               => $etag,
                'Content-Type'       => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="events.ics"',
            ]);
        }

        $response = new WP_REST_Response($ics);
        $response->header('ETag', $etag);
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
        $normalized = self::ensure_normalized_args($args);
        $range_start = $normalized['range_start'];
        $range_end   = $normalized['range_end'];

        $fingerprint = $normalized['cache_fingerprint'];
        $current_user_id = get_current_user_id();
        $fingerprint['user'] = $normalized['favorites'] ? (int) $current_user_id : 0;

        $cache_key = self::get_cache_key($fingerprint, $apply_pagination);
        $cached    = self::cache_get($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $query_args = [
            'post_type'      => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
        ];

        if (!empty($normalized['taxonomy_query'])) {
            $query_args['tax_query'] = $normalized['taxonomy_query'];
        }

        $query = new WP_Query($query_args);

        $events           = [];
        $truncated_global = false;

        foreach ($query->posts as $post_id) {
            $event = self::prepare_event((int) $post_id, $current_user_id);
            if (empty($event['start'])) {
                continue;
            }

            $expansion = self::expand_occurrences($event, $range_start, $range_end);
            if (empty($expansion['occurrences'])) {
                continue;
            }

            if (!empty($expansion['truncated'])) {
                $truncated_global = true;
            }

            foreach ($expansion['occurrences'] as $index => $occurrence) {
                if ($normalized['favorites'] && empty($occurrence['favorite'])) {
                    continue;
                }

                $events[] = $occurrence + [
                    'occurrence'        => $index,
                    'categories'        => $event['categories'],
                    'categoryNames'     => $event['categoryNames'],
                    'organization'      => $event['organization'],
                    'organizationName'  => $event['organizationName'],
                    'image'             => $event['image'],
                    'thumbnail'         => $event['image']['url'] ?? '',
                    'excerpt'           => $event['excerpt'],
                    'schema'            => $event['schema'],
                    'description'       => $event['excerpt'],
                ];
            }
        }

        $events = self::sort_events($events, $normalized['orderby'], $normalized['order']);

        $total    = count($events);
        $per_page = $normalized['per_page'];
        $page     = $normalized['page'];
        $total_pages = (int) max(1, ceil($total / $per_page));
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
            'truncated'  => $truncated_global,
        ];

        self::cache_set($cache_key, $result);

        return $result;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private static function ensure_normalized_args(array $args): array
    {
        if (!empty($args['_normalized'])) {
            return $args;
        }

        return self::normalize_args($args);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private static function normalize_args(array $args): array
    {
        $timezone    = \wp_timezone();
        $start_input = $args['start'] ?? null;
        $end_input   = $args['end'] ?? null;

        [$start_utc, $end_utc] = self::normalize_date_range($start_input, $end_input, $timezone);

        $per_page = isset($args['per_page']) ? (int) $args['per_page'] : 20;
        $per_page = max(1, $per_page ?: 20);
        $per_page = min($per_page, 100);

        $page = isset($args['page']) ? (int) $args['page'] : 1;
        $page = max(1, $page);

        $orderby = is_string($args['orderby'] ?? '') ? strtolower((string) $args['orderby']) : '';
        $allowed_orderby = ['event_start', 'title'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'event_start';
        }

        $order = strtoupper(is_string($args['order'] ?? '') ? (string) $args['order'] : '');
        $order = 'DESC' === $order ? 'DESC' : 'ASC';

        $favorites = self::to_bool($args['favorites'] ?? false);

        $taxonomy_filters = self::normalize_taxonomy_filters($args['taxonomy'] ?? []);

        $cache_fingerprint = [
            'start'     => $start_utc->format(DateTimeInterface::ATOM),
            'end'       => $end_utc->format(DateTimeInterface::ATOM),
            'page'      => $page,
            'per_page'  => $per_page,
            'orderby'   => $orderby,
            'order'     => $order,
            'favorites' => $favorites ? 1 : 0,
            'taxonomy'  => $taxonomy_filters['fingerprint'],
        ];

        return [
            '_normalized'       => true,
            'range_start'       => $start_utc,
            'range_end'         => $end_utc,
            'per_page'          => $per_page,
            'page'              => $page,
            'orderby'           => $orderby,
            'order'             => $order,
            'favorites'         => $favorites,
            'taxonomy_query'    => $taxonomy_filters['query'],
            'cache_fingerprint' => $cache_fingerprint,
        ];
    }

    /**
     * @param mixed $value
     *
     * @return array{query: array<int|string, array<string, mixed>>, fingerprint: array<int, array{taxonomy: string, field: string, terms: array<int, int|string>}>}
     */
    private static function normalize_taxonomy_filters($value): array
    {
        $allowed = get_object_taxonomies(PostTypeRegistrar::EVENT_POST_TYPE, 'names');
        $allowed = is_array($allowed) ? array_map('strval', $allowed) : [];

        $query       = [];
        $fingerprint = [];

        foreach ((array) $value as $raw_taxonomy => $raw_terms) {
            $taxonomy = sanitize_key((string) $raw_taxonomy);
            if ('' === $taxonomy || !in_array($taxonomy, $allowed, true)) {
                continue;
            }

            $terms     = is_array($raw_terms) ? $raw_terms : [$raw_terms];
            $term_ids  = [];
            $term_slugs = [];

            foreach ($terms as $term) {
                if (is_int($term) || (is_string($term) && is_numeric($term))) {
                    $term_id = (int) $term;
                    if ($term_id > 0) {
                        $term_ids[] = $term_id;
                    }
                    continue;
                }

                if (is_string($term)) {
                    $slug = sanitize_title($term);
                    if ('' !== $slug) {
                        $term_slugs[] = $slug;
                    }
                }
            }

            if ($term_slugs) {
                $unique_slugs = array_values(array_unique($term_slugs));
                $clause = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $unique_slugs,
                ];
                $query[]       = $clause;
                $fingerprint[] = $clause;
            }

            if ($term_ids) {
                $unique_ids = array_values(array_unique($term_ids));
                $clause = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $unique_ids,
                ];
                $query[]       = $clause;
                $fingerprint[] = $clause;
            }
        }

        if ($query) {
            $relation = count($query) > 1 ? ['relation' => 'AND'] : [];
            if ($relation) {
                $query = array_merge($relation, $query);
            }
        }

        if ($fingerprint) {
            foreach ($fingerprint as &$entry) {
                $terms = $entry['terms'];
                sort($terms);
                $entry['terms'] = $terms;
            }
            unset($entry);

            usort($fingerprint, static function (array $a, array $b): int {
                $taxonomy_compare = strcmp($a['taxonomy'], $b['taxonomy']);
                if (0 !== $taxonomy_compare) {
                    return $taxonomy_compare;
                }

                $field_compare = strcmp($a['field'], $b['field']);
                if (0 !== $field_compare) {
                    return $field_compare;
                }

                return strcmp(wp_json_encode($a['terms']), wp_json_encode($b['terms']));
            });
        }

        return [
            'query'       => $query,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * @param mixed $start
     * @param mixed $end
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private static function normalize_date_range($start, $end, DateTimeZone $timezone): array
    {
        [$default_start, $default_end] = self::default_month_range($timezone);

        $start_local = self::parse_range_boundary($start, $timezone, false) ?? $default_start;
        $end_local   = self::parse_range_boundary($end, $timezone, true);

        if (!$end_local) {
            $end_local = self::default_end_for_start($start_local);
        }

        if ($end_local < $start_local) {
            $end_local = $start_local;
        }

        return [
            $start_local->setTimezone(new DateTimeZone('UTC')),
            $end_local->setTimezone(new DateTimeZone('UTC')),
        ];
    }

    private static function default_month_range(DateTimeZone $timezone): array
    {
        $now = new DateTimeImmutable('now', $timezone);
        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        $end   = $start->modify('last day of this month')->setTime(23, 59, 59);

        return [$start, $end];
    }

    private static function default_end_for_start(DateTimeImmutable $start): DateTimeImmutable
    {
        return $start->modify('last day of this month')->setTime(23, 59, 59);
    }

    /**
     * @param mixed $value
     */
    private static function parse_range_boundary($value, DateTimeZone $timezone, bool $is_end): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone($timezone);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $date = new DateTimeImmutable($value, $timezone);
                return $is_end ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0);
            }

            $date = new DateTimeImmutable($value, $timezone);
            if ($is_end && !str_contains($value, 'T') && !str_contains($value, ' ')) {
                $date = $date->setTime(23, 59, 59);
            }

            return $date;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $events
     *
     * @return array<int, array<string, mixed>>
     */
    private static function sort_events(array $events, string $orderby, string $order): array
    {
        $direction = 'DESC' === strtoupper($order) ? -1 : 1;

        usort($events, static function (array $a, array $b) use ($orderby, $direction): int {
            if ('title' === $orderby) {
                return $direction * strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            }

            $a_start = isset($a['start']) ? strtotime((string) $a['start']) : 0;
            $b_start = isset($b['start']) ? strtotime((string) $b['start']) : 0;

            $comparison = $a_start <=> $b_start;

            if (0 === $comparison) {
                return $direction * strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            }

            return $direction * $comparison;
        });

        return $events;
    }

    /**
     * @return mixed
     */
    private static function cache_get(string $key)
    {
        if (wp_using_ext_object_cache()) {
            return wp_cache_get($key, 'artpulse_events');
        }

        $value = get_transient('artpulse_events_' . $key);

        return false === $value ? false : $value;
    }

    /**
     * @param mixed $value
     */
    private static function cache_set(string $key, $value): void
    {
        if (wp_using_ext_object_cache()) {
            wp_cache_set($key, $value, 'artpulse_events', HOUR_IN_SECONDS);

            return;
        }

        set_transient('artpulse_events_' . $key, $value, HOUR_IN_SECONDS);
    }

    private static function build_etag($payload): string
    {
        return '"' . md5(serialize($payload)) . '"';
    }

    private static function is_not_modified(WP_REST_Request $request, string $etag): bool
    {
        $if_none_match = trim((string) $request->get_header('If-None-Match'));

        return '' !== $if_none_match && $if_none_match === $etag;
    }

    /**
     * @param mixed $value
     */
    private static function to_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value > 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private static function get_collection_args(): array
    {
        return [
            'start'     => [
                'sanitize_callback' => [self::class, 'sanitize_date_param'],
            ],
            'end'       => [
                'sanitize_callback' => [self::class, 'sanitize_date_param'],
            ],
            'favorites' => [
                'sanitize_callback' => [self::class, 'sanitize_boolean_param'],
            ],
            'per_page'  => [
                'sanitize_callback' => [self::class, 'sanitize_positive_int'],
            ],
            'page'      => [
                'sanitize_callback' => [self::class, 'sanitize_positive_int'],
            ],
            'orderby'   => [
                'sanitize_callback' => [self::class, 'sanitize_orderby_param'],
            ],
            'order'     => [
                'sanitize_callback' => [self::class, 'sanitize_order_param'],
            ],
            'taxonomy'  => [
                'sanitize_callback' => [self::class, 'sanitize_taxonomy_param'],
            ],
        ];
    }

    private static function normalize_request_params(WP_REST_Request $request): array
    {
        $params = [
            'start'     => $request->get_param('start'),
            'end'       => $request->get_param('end'),
            'page'      => $request->get_param('page'),
            'per_page'  => $request->get_param('per_page'),
            'orderby'   => $request->get_param('orderby'),
            'order'     => $request->get_param('order'),
            'favorites' => $request->get_param('favorites'),
            'taxonomy'  => $request->get_param('taxonomy'),
        ];

        return self::normalize_args($params);
    }

    public static function get_events_schema(): array
    {
        return [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'artpulse_events_collection',
            'type'       => 'object',
            'properties' => [
                'events' => [
                    'type'  => 'array',
                    'items' => self::get_event_schema_definition(),
                ],
                'pagination' => [
                    'type'       => 'object',
                    'properties' => [
                        'total'       => ['type' => 'integer'],
                        'total_pages' => ['type' => 'integer'],
                        'page'        => ['type' => 'integer'],
                        'per_page'    => ['type' => 'integer'],
                    ],
                ],
                'truncated' => [
                    'type' => 'boolean',
                ],
            ],
        ];
    }

    public static function get_event_schema(): array
    {
        return self::get_event_schema_definition();
    }

    private static function get_event_schema_definition(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => true,
            'properties'           => [
                'id'               => ['type' => 'integer'],
                'title'            => ['type' => 'string'],
                'start'            => ['type' => 'string'],
                'end'              => ['type' => 'string'],
                'startUtc'         => ['type' => 'string'],
                'endUtc'           => ['type' => 'string'],
                'allDay'           => ['type' => 'boolean'],
                'timezone'         => ['type' => 'string'],
                'location'         => ['type' => 'string'],
                'cost'             => ['type' => ['string', 'null']],
                'favorite'         => ['type' => 'boolean'],
                'url'              => ['type' => 'string', 'format' => 'uri'],
                'categories'       => [
                    'type'  => 'array',
                    'items' => ['type' => 'string'],
                ],
                'categoryNames' => [
                    'type'  => 'array',
                    'items' => ['type' => 'string'],
                ],
                'organization'     => ['type' => 'integer'],
                'organizationName' => ['type' => 'string'],
                'thumbnail'        => ['type' => 'string'],
                'image'            => [
                    'type'       => ['object', 'null'],
                    'properties' => [
                        'id'     => ['type' => 'integer'],
                        'url'    => ['type' => 'string', 'format' => 'uri'],
                        'alt'    => ['type' => 'string'],
                        'size'   => ['type' => 'string'],
                        'width'  => ['type' => 'integer'],
                        'height' => ['type' => 'integer'],
                    ],
                ],
                'excerpt'    => ['type' => 'string'],
                'schema'     => ['type' => 'object'],
                'recurrence' => ['type' => ['array', 'object', 'null']],
            ],
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
        $timezone = $timezone ?: \wp_timezone_string();
        $location = get_post_meta($post_id, '_ap_event_location', true);
        $cost     = get_post_meta($post_id, '_ap_event_cost', true);
        $all_day  = (bool) get_post_meta($post_id, '_ap_event_all_day', true);
        $org_id   = (int) get_post_meta($post_id, '_ap_event_organization', true);
        $org_name = $org_id ? get_the_title($org_id) : '';

        $thumb_id = get_post_thumbnail_id($post_id);
        if (!$thumb_id) {
            $fallback_images = (array) get_post_meta($post_id, '_ap_submission_images', true);
            $thumb_id = (int) ($fallback_images[0] ?? 0);
        }

        $image = $thumb_id ? array_merge(
            [
                'url'    => '',
                'width'  => 0,
                'height' => 0,
                'size'   => '',
            ],
            ImageTools::best_image_src($thumb_id) ?: [],
            [
                'id'  => $thumb_id,
                'alt' => sanitize_text_field(
                    get_post_meta($thumb_id, '_wp_attachment_image_alt', true) ?: get_the_title($post_id)
                ),
            ]
        ) : null;
        if ($image && '' === $image['url']) {
            $image = null;
        }

        $thumbnail = $image['url'] ?? '';
        $excerpt   = wp_strip_all_tags(get_the_excerpt($post_id));

        $categories = wp_get_post_terms($post_id, PostTypeRegistrar::EVENT_TAXONOMY, ['fields' => 'slugs']);
        $category_names = wp_get_post_terms($post_id, PostTypeRegistrar::EVENT_TAXONOMY, ['fields' => 'names']);

        $is_favorited = false;
        if ($current_user_id > 0) {
            $is_favorited = FavoritesManager::is_favorited($current_user_id, $post_id, PostTypeRegistrar::EVENT_POST_TYPE);
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
            'startUtc'         => self::format_utc($start),
            'endUtc'           => self::format_utc($end),
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
            'image'            => $image,
            'excerpt'          => $excerpt,
            'schema'           => $schema,
            'recurrence'       => get_post_meta($post_id, '_ap_event_recurrence', true),
        ];
    }

    private static function expand_occurrences(array $event, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end): array
    {
        return RecurrenceExpander::expand($event, $range_start, $range_end);
    }

    private static function sanitize_date_param($value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private static function sanitize_positive_int($value): ?int
    {
        if (is_numeric($value)) {
            $int = (int) $value;

            return $int > 0 ? $int : null;
        }

        return null;
    }

    private static function sanitize_orderby_param($value): string
    {
        $value = strtolower((string) $value);

        return in_array($value, ['event_start', 'title'], true) ? $value : 'event_start';
    }

    private static function sanitize_order_param($value): string
    {
        return 'DESC' === strtoupper((string) $value) ? 'DESC' : 'ASC';
    }

    private static function sanitize_taxonomy_param($value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function sanitize_boolean_param($value): int
    {
        return self::to_bool($value) ? 1 : 0;
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

    private static function format_utc(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $date = self::parse_date($value);

        return $date ? $date->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM) : '';
    }

    private static function get_cache_key(array $fingerprint, bool $apply_pagination, string $suffix = 'events'): string
    {
        $version = (int) get_option(self::CACHE_VERSION_OPTION, 1);
        $encoded = wp_json_encode($fingerprint);
        $toggle  = $apply_pagination ? ':1' : ':0';

        return $version . ':' . $suffix . ':' . md5((string) $encoded . $toggle);
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
