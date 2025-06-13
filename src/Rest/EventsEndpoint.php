<?php

namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Query;

class EventsEndpoint extends WP_REST_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';  // Match your JS!
        $this->rest_base = 'events';
    }

    public function register_routes() {

        // GET all events
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getEvents'],
            'permission_callback' => [$this, 'permissionsCheck'],
            'args'                => $this->getCollectionParams(),
        ]);

        // GET single event
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getSingleEvent'],
            'permission_callback' => [$this, 'permissionsCheck'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => [$this, 'validateEventId'],
                ],
            ],
        ]);

        // GET event iCalendar
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/ics', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getEventIcs'],
            'permission_callback' => [$this, 'permissionsCheck'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => [$this, 'validateEventId'],
                ],
            ],
        ]);

        // POST create event
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'createEvent'],
            'permission_callback' => [$this, 'permissionsCheckWrite'],
            'args'                => $this->getEventSchema(),
        ]);

        // PUT update event
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'updateEvent'],
            'permission_callback' => [$this, 'permissionsCheckWrite'],
            'args'                => $this->getEventSchema(true),
        ]);

        // DELETE event
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'deleteEvent'],
            'permission_callback' => [$this, 'permissionsCheckWrite'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => [$this, 'validateEventId'],
                ],
            ],
        ]);
    }

    public function getEvents(WP_REST_Request $request) {
        $perPage = $request->get_param('per_page') ?: 10;
        $page    = $request->get_param('page') ?: 1;
        $event_type = sanitize_text_field($request->get_param('event_type'));
        $city       = sanitize_text_field($request->get_param('city'));
        $state      = sanitize_text_field($request->get_param('state'));
        $country    = sanitize_text_field($request->get_param('country'));

        $args = [
            'post_type'      => 'ead_event',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $perPage ),
            'paged'          => absint( $page ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $cache_key = 'ead_events_' . md5( serialize( $args ) );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            $response = new WP_REST_Response( $cached['data'], 200 );
            $response->header( 'X-WP-Total', $cached['total'] );
            $response->header( 'X-WP-TotalPages', $cached['pages'] );

            return $response;
        }

        // Filter by event_type taxonomy if provided
        if (!empty($event_type)) {
            $args['tax_query'] = [[
                'taxonomy' => 'ead_event_type',
                'field'    => 'slug',
                'terms'    => $event_type,
            ]];
        }

        // Meta query for location filters
        $meta_query = [];
        if (!empty($city)) {
            $meta_query[] = [
                'key'     => 'event_city',
                'value'   => $city,
                'compare' => 'LIKE',
            ];
        }
        if (!empty($state)) {
            $meta_query[] = [
                'key'     => 'event_state',
                'value'   => $state,
                'compare' => 'LIKE',
            ];
        }
        if (!empty($country)) {
            $meta_query[] = [
                'key'     => 'event_country',
                'value'   => $country,
                'compare' => 'LIKE',
            ];
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return new WP_Error('no_events_found', __('No events found.', 'artpulse-management'), ['status' => 404]);
        }

        $events = [];
        foreach ($query->posts as $post) {
            $events[] = $this->format_event_data($post);
        }

        $response = new WP_REST_Response( $events, 200 );
        $response->header( 'X-WP-Total', $query->found_posts );
        $response->header( 'X-WP-TotalPages', $query->max_num_pages );

        set_transient(
            $cache_key,
            [
                'data'  => $events,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages,
            ],
            5 * MINUTE_IN_SECONDS
        );

        return $response;
    }

    public function getSingleEvent(WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'ead_event' || $post->post_status !== 'publish') {
            return new WP_Error('event_not_found', __('Event not found.', 'artpulse-management'), ['status' => 404]);
        }

        return new WP_REST_Response($this->format_event_data($post), 200);
    }

    public function createEvent(WP_REST_Request $request) {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new WP_Error('invalid_nonce', __('Invalid nonce.', 'artpulse-management'), ['status' => 403]);
        }

        $post_id = wp_insert_post([
            'post_type'    => 'ead_event',
            'post_title'   => sanitize_text_field($request->get_param('title')),
            'post_content' => wp_kses_post($request->get_param('description')),
            'post_status'  => 'pending',
            'post_author'  => get_current_user_id(),
        ]);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $this->save_event_meta($post_id, $request);

        return new WP_REST_Response(['message' => __('Event created.', 'artpulse-management'), 'id' => $post_id], 201);
    }

    public function updateEvent(WP_REST_Request $request) {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new WP_Error('invalid_nonce', __('Invalid nonce.', 'artpulse-management'), ['status' => 403]);
        }

        $id = (int) $request->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'ead_event') {
            return new WP_Error('event_not_found', __('Event not found.', 'artpulse-management'), ['status' => 404]);
        }

        $updated = wp_update_post([
            'ID'           => $id,
            'post_title'   => sanitize_text_field($request->get_param('title')),
            'post_content' => wp_kses_post($request->get_param('description')),
        ], true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        $this->save_event_meta($id, $request);

        return new WP_REST_Response(['message' => __('Event updated.', 'artpulse-management'), 'id' => $id], 200);
    }

    public function deleteEvent(WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $result = wp_delete_post($id, true);

        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete event.', 'artpulse-management'), ['status' => 500]);
        }

        return new WP_REST_Response(['message' => __('Event deleted.', 'artpulse-management')], 200);
    }

    private function save_event_meta($post_id, WP_REST_Request $request) {
        $meta_fields = [
            'event_start_date', 'event_end_date', 'venue_name', 'event_street_address',
            'event_city', 'event_state', 'event_country', 'event_postcode',
            'event_organizer_name', 'event_organizer_email'
        ];

        foreach ($meta_fields as $field) {
            $value = $request->get_param($field);
            if (!empty($value)) {
                update_post_meta($post_id, $field, sanitize_text_field($value));
            } else {
                delete_post_meta($post_id, $field);
            }
        }

        // Taxonomy term
        $event_type = $request->get_param('event_type');
        if (!empty($event_type)) {
            wp_set_object_terms($post_id, $event_type, 'ead_event_type');
        }
    }

    private function format_event_data($post) {
        return [
            'id'          => $post->ID,
            'title'       => get_the_title($post->ID),
            'description' => apply_filters('the_content', $post->post_content),
            'start_date'  => get_post_meta($post->ID, 'event_start_date', true),
            'end_date'    => get_post_meta($post->ID, 'event_end_date', true),
            'venue'       => [
                'name' => get_post_meta($post->ID, 'venue_name', true),
                'street_address' => get_post_meta($post->ID, 'event_street_address', true),
                'city' => get_post_meta($post->ID, 'event_city', true),
                'state' => get_post_meta($post->ID, 'event_state', true),
                'country' => get_post_meta($post->ID, 'event_country', true),
                'postcode' => get_post_meta($post->ID, 'event_postcode', true),
            ],
            'organizer' => [
                'name' => get_post_meta($post->ID, 'event_organizer_name', true),
                'email' => get_post_meta($post->ID, 'event_organizer_email', true),
            ],
            'event_type' => $this->get_event_type($post->ID),
        ];
    }

    private function get_event_type($post_id) {
        $terms = wp_get_post_terms($post_id, 'ead_event_type');
        $result = [];

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $result[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ];
            }
        }

        return $result;
    }

    public function validateEventId($eventId) {
        $post = get_post($eventId);
        return (bool) ($post && $post->post_type === 'ead_event');
    }

    public function permissionsCheck(WP_REST_Request $request) {
        return current_user_can('read');
    }

    public function permissionsCheckWrite(WP_REST_Request $request) {
        return current_user_can('edit_posts');
    }

    public function getCollectionParams() {
        return [
            'page' => [
                'description'       => __('Current page of the collection.', 'artpulse-management'),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'description'       => __('Number of items per page.', 'artpulse-management'),
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ],
            'event_type' => [
                'description'       => __('Filter by event type slug.', 'artpulse-management'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'city' => [
                'description'       => __('Filter by city.', 'artpulse-management'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'state' => [
                'description'       => __('Filter by state/region.', 'artpulse-management'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'country' => [
                'description'       => __('Filter by country.', 'artpulse-management'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function getEventSchema($editing = false) {
        return [
            'title' => [
                'description' => __('Event title.', 'artpulse-management'),
                'type' => 'string',
                'required' => !$editing,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'description' => [
                'description' => __('Event description.', 'artpulse-management'),
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ],
            'event_type' => [
                'description' => __('Event type slug.', 'artpulse-management'),
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'event_start_date' => [
                'description' => __('Start date.', 'artpulse-management'),
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'event_end_date' => [
                'description' => __('End date.', 'artpulse-management'),
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            // Add other fields as needed...
        ];
    }

    public function getEventIcs(WP_REST_Request $request) {
        $id  = (int) $request->get_param('id');
        $ics = $this->generateIcsData($id);

        if (!$ics) {
            return new WP_Error('event_not_found', __('Event not found.', 'artpulse-management'), ['status' => 404]);
        }

        $response = new WP_REST_Response($ics, 200);
        $response->header('Content-Type', 'text/calendar; charset=utf-8');
        $response->header('Content-Disposition', 'attachment; filename="event-' . $id . '.ics"');

        return $response;
    }

    private function generateIcsData($eventId) {
        $post = get_post($eventId);

        if (!$post || $post->post_type !== 'ead_event') {
            return '';
        }

        $start = $this->formatIcsDate(get_post_meta($eventId, 'event_start_date', true));
        $end   = $this->formatIcsDate(get_post_meta($eventId, 'event_end_date', true));

        $venue   = get_post_meta($eventId, 'venue_name', true);
        $street  = get_post_meta($eventId, 'event_street_address', true);
        $city    = get_post_meta($eventId, 'event_city', true);
        $state   = get_post_meta($eventId, 'event_state', true);
        $country = get_post_meta($eventId, 'event_country', true);

        $location_parts = array_filter([$venue, $street, $city, $state, $country]);
        $location       = implode(', ', $location_parts);

        $description = wp_strip_all_tags($post->post_content);

        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//ArtPulse//EN\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= 'UID:' . $eventId . '@artpulse' . "\r\n";
        $ics .= 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\n";
        if ($start) {
            $ics .= 'DTSTART;VALUE=DATE:' . $start . "\r\n";
        }
        if ($end) {
            $ics .= 'DTEND;VALUE=DATE:' . $end . "\r\n";
        }
        $ics .= 'SUMMARY:' . $this->escapeIcsText($post->post_title) . "\r\n";
        if ($description) {
            $ics .= 'DESCRIPTION:' . $this->escapeIcsText($description) . "\r\n";
        }
        if ($location) {
            $ics .= 'LOCATION:' . $this->escapeIcsText($location) . "\r\n";
        }
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    private function formatIcsDate($date) {
        if (empty($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        if (!$timestamp) {
            return '';
        }

        return gmdate('Ymd', $timestamp);
    }

    private function escapeIcsText($text) {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\n", "\\n", $text);
        $text = str_replace([',', ';'], ['\\,', '\\;'], $text);
        return $text;
    }
}
