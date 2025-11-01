<?php

namespace ArtPulse\Rest;

use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;
use WP_User_Query;

use function absint;
use function array_filter;
use function array_map;
use function array_unique;
use function count;
use function current_user_can;
use function delete_post_meta;
use function delete_transient;
use function esc_url_raw;
use function get_avatar_url;
use function get_current_user_id;
use function get_edit_post_link;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function get_user_by;
use function get_user_meta;
use function get_the_title;
use function get_transient;
use function is_wp_error;
use function is_user_logged_in;
use function rest_ensure_response;
use function rest_authorization_required_code;
use function rest_parse_date;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function set_transient;
use function update_post_meta;
use function update_user_meta;
use function wp_insert_post;
use function wp_mail;
use function wp_reset_postdata;
use function wp_trash_post;
use function wp_update_post;

class OrganizationDashboardController extends WP_REST_Controller
{
    private const ATTR_ORG_ID = 'ap_resolved_org_id';
    private const OVERVIEW_CACHE_PREFIX = 'ap_org_overview_';
    private const OVERVIEW_TTL = 5 * MINUTE_IN_SECONDS;
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 50;

    protected string $namespace = 'artpulse/v1';

    public static function register(): void
    {
        $controller = new self();
        $controller->register_routes();
    }

    public function register_routes(): void
    {
        register_rest_route(
            $this->namespace,
            '/org/overview',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_overview'],
                    'permission_callback' => fn (WP_REST_Request $request) => $this->ensure_org_access($request, false),
                    'args'                => $this->get_org_param_args(),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/org/events',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_events'],
                    'permission_callback' => fn (WP_REST_Request $request) => $this->ensure_org_access($request, false),
                    'args'                => array_merge(
                        $this->get_org_param_args(),
                        [
                            'status'    => [
                                'sanitize_callback' => [$this, 'sanitize_event_status_param'],
                                'validate_callback' => [$this, 'validate_event_status_param'],
                            ],
                            'per_page'  => [
                                'sanitize_callback' => 'absint',
                            ],
                            'page'      => [
                                'sanitize_callback' => 'absint',
                            ],
                        ]
                    ),
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_event'],
                    'permission_callback' => fn (WP_REST_Request $request) => $this->ensure_org_access($request, true),
                    'args'                => array_merge(
                        $this->get_org_param_args(true),
                        $this->get_event_mutation_args(false)
                    ),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/org/events/(?P<id>\\d+)',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_event'],
                    'permission_callback' => fn (WP_REST_Request $request) => $this->ensure_org_access($request, true),
                    'args'                => array_merge(
                        [
                            'id' => [
                                'sanitize_callback' => 'absint',
                                'validate_callback' => [$this, 'validate_event_exists'],
                            ],
                        ],
                        $this->get_event_mutation_args(true)
                    ),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'delete_event'],
                    'permission_callback' => fn (WP_REST_Request $request) => $this->ensure_org_access($request, true),
                    'args'                => [
                        'id' => [
                            'sanitize_callback' => 'absint',
                            'validate_callback' => [$this, 'validate_event_exists'],
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/org/roster',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_roster'],
                    'permission_callback' => fn (WP_REST_Request $request) => $this->ensure_org_access($request, true),
                    'args'                => array_merge(
                        $this->get_org_param_args(true),
                        [
                            'per_page' => [
                                'sanitize_callback' => 'absint',
                            ],
                            'page'     => [
                                'sanitize_callback' => 'absint',
                            ],
                        ]
                    ),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/org/invite',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'invite_member'],
                    'permission_callback' => fn (WP_REST_Request $request) => $this->ensure_org_access($request, true),
                    'args'                => array_merge(
                        $this->get_org_param_args(true),
                        [
                            'email'   => [
                                'sanitize_callback' => 'sanitize_email',
                            ],
                            'user_id' => [
                                'sanitize_callback' => 'absint',
                            ],
                            'role'    => [
                                'sanitize_callback' => [$this, 'sanitize_org_role'],
                            ],
                        ]
                    ),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/org/analytics',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_analytics'],
                    'permission_callback' => fn (WP_REST_Request $request) => $this->ensure_org_access($request, true),
                    'args'                => array_merge(
                        $this->get_org_param_args(true),
                        [
                            'range' => [
                                'sanitize_callback' => [$this, 'sanitize_range_param'],
                                'validate_callback' => [$this, 'validate_range_param'],
                            ],
                        ]
                    ),
                ],
            ]
        );
    }

    public function get_overview(WP_REST_Request $request): WP_REST_Response
    {
        $org_id  = $this->get_resolved_org_id($request);
        $user_id = get_current_user_id();

        $cache_key = $this->get_overview_cache_key($user_id, $org_id);
        $cached    = get_transient($cache_key);
        if (false !== $cached) {
            return rest_ensure_response($cached);
        }

        $counts = [
            'events'      => $this->count_events($org_id),
            'artists'     => $this->count_artists($org_id),
            'submissions' => $this->count_pending_events($org_id),
        ];

        $recent = [
            'events'      => $this->get_recent_events($org_id),
            'submissions' => $this->get_recent_submissions($org_id),
        ];

        $payload = [
            'counts' => $counts,
            'recent' => $recent,
            'org'    => [
                'id'        => $org_id,
                'title'     => get_the_title($org_id) ?: '',
                'permalink' => $this->maybe_esc_url(get_permalink($org_id)),
            ],
        ];

        set_transient($cache_key, $payload, self::OVERVIEW_TTL);

        return rest_ensure_response($payload);
    }

    public function get_events(WP_REST_Request $request): WP_REST_Response
    {
        $org_id   = $this->get_resolved_org_id($request);
        $status   = $request->get_param('status') ?: 'publish';
        $per_page = $this->sanitize_per_page((int) $request->get_param('per_page'));
        $page     = $this->sanitize_page((int) $request->get_param('page'));

        $query = new WP_Query([
            'post_type'      => 'artpulse_event',
            'post_status'    => $status,
            'meta_query'     => [
                [
                    'key'   => '_ap_event_organization',
                    'value' => $org_id,
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'posts_per_page' => $per_page,
            'paged'          => $page,
        ]);

        $events = [];
        foreach ($query->posts as $post) {
            if ($post instanceof WP_Post) {
                $events[] = $this->format_event($post->ID);
            }
        }

        wp_reset_postdata();

        $response = [
            'items'      => $events,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => (int) $query->found_posts,
                'total_pages' => (int) max(1, ceil($query->found_posts / $per_page)),
            ],
        ];

        return rest_ensure_response($response);
    }

    public function create_event(WP_REST_Request $request): WP_REST_Response
    {
        $org_id = $this->get_resolved_org_id($request);
        $title  = $this->sanitize_required_text($request->get_param('title'));

        if ('' === $title) {
            return new WP_Error(
                'ap_org_event_title_required',
                __('A title is required to create an event.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $status = $this->sanitize_event_status_param($request->get_param('status') ?: 'draft');
        if (!$this->validate_event_status_param($status)) {
            return new WP_Error(
                'ap_org_event_status_invalid',
                __('Invalid event status supplied.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $post_id = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_title'  => $title,
            'post_status' => $status,
            'post_author' => get_current_user_id(),
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $this->persist_event_meta($post_id, $org_id, $request);

        $this->invalidate_overview_cache($org_id);

        $response = $this->format_event($post_id);

        return new WP_REST_Response($response, 201);
    }

    public function update_event(WP_REST_Request $request): WP_REST_Response
    {
        $event_id = (int) $request->get_param('id');
        $event    = get_post($event_id);

        if (!$event instanceof WP_Post || 'artpulse_event' !== $event->post_type) {
            return new WP_Error(
                'ap_org_event_missing',
                __('The requested event could not be found.', 'artpulse-management'),
                ['status' => 404]
            );
        }

        $org_id = (int) get_post_meta($event_id, '_ap_event_organization', true);
        if ($org_id <= 0) {
            return new WP_Error(
                'ap_org_event_unassigned',
                __('This event is not linked to an organization.', 'artpulse-management'),
                ['status' => 409]
            );
        }

        $title = $request->get_param('title');
        $data  = ['ID' => $event_id];
        $dirty = false;

        if (null !== $title) {
            $data['post_title'] = $this->sanitize_required_text($title);
            if ('' === $data['post_title']) {
                return new WP_Error(
                    'ap_org_event_title_required',
                    __('A title is required to update an event.', 'artpulse-management'),
                    ['status' => 400]
                );
            }
            $dirty = true;
        }

        $status = $request->get_param('status');
        if (null !== $status) {
            $status = $this->sanitize_event_status_param($status);
            if (!$this->validate_event_status_param($status)) {
                return new WP_Error(
                    'ap_org_event_status_invalid',
                    __('Invalid event status supplied.', 'artpulse-management'),
                    ['status' => 400]
                );
            }
            $data['post_status'] = $status;
            $dirty               = true;
        }

        if ($dirty) {
            $result = wp_update_post($data, true);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        $this->persist_event_meta($event_id, $org_id, $request, true);

        $this->invalidate_overview_cache($org_id);

        return rest_ensure_response($this->format_event($event_id));
    }

    public function delete_event(WP_REST_Request $request): WP_REST_Response
    {
        $event_id = (int) $request->get_param('id');
        $org_id   = (int) get_post_meta($event_id, '_ap_event_organization', true);

        if ($org_id <= 0) {
            return new WP_Error(
                'ap_org_event_unassigned',
                __('This event is not linked to an organization.', 'artpulse-management'),
                ['status' => 409]
            );
        }

        $deleted = wp_trash_post($event_id);
        if (!$deleted) {
            return new WP_Error(
                'ap_org_event_delete_failed',
                __('The event could not be deleted.', 'artpulse-management'),
                ['status' => 500]
            );
        }

        $this->invalidate_overview_cache($org_id);

        return new WP_REST_Response(null, 204);
    }

    public function get_roster(WP_REST_Request $request): WP_REST_Response
    {
        $org_id   = $this->get_resolved_org_id($request);
        $per_page = $this->sanitize_per_page((int) $request->get_param('per_page'));
        $page     = $this->sanitize_page((int) $request->get_param('page'));

        return rest_ensure_response($this->build_roster_response($org_id, $per_page, $page));
    }

    public function invite_member(WP_REST_Request $request): WP_REST_Response
    {
        $org_id   = $this->get_resolved_org_id($request);
        $per_page = $this->sanitize_per_page((int) $request->get_param('per_page'));
        $page     = $this->sanitize_page((int) $request->get_param('page'));
        $role     = $this->sanitize_org_role($request->get_param('role'));

        $user_id = absint($request->get_param('user_id'));
        $email   = sanitize_email($request->get_param('email'));

        if ($user_id <= 0 && '' === $email) {
            return new WP_Error(
                'ap_org_invite_invalid',
                __('Provide a valid user ID or email to send an invite.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $user = null;
        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
            if (!$user instanceof WP_User) {
                return new WP_Error(
                    'ap_org_invite_user_missing',
                    __('The selected user could not be found.', 'artpulse-management'),
                    ['status' => 404]
                );
            }
        } elseif ('' !== $email) {
            $user = get_user_by('email', $email);
        }

        if ($user instanceof WP_User) {
            $this->attach_user_to_org((int) $user->ID, $org_id, $role);
            $this->invalidate_overview_cache($org_id);

            $payload              = $this->build_roster_response($org_id, $per_page, $page);
            $payload['assignment'] = 'attached';

            return rest_ensure_response($payload);
        }

        if ('' === $email) {
            return new WP_Error(
                'ap_org_invite_email_missing',
                __('A valid email address is required to invite a new member.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $this->send_invite_email($email, $org_id);

        $payload              = $this->build_roster_response($org_id, $per_page, $page);
        $payload['assignment'] = 'invited';

        return rest_ensure_response($payload);
    }

    public function get_analytics(WP_REST_Request $request): WP_REST_Response
    {
        $range = (int) ($request->get_param('range') ?: 30);

        $response = [
            'range'   => $range,
            'metrics' => [],
        ];

        return rest_ensure_response($response);
    }

    public function validate_event_exists($value): bool|WP_Error
    {
        $event_id = absint($value);
        $post     = get_post($event_id);

        if ($post instanceof WP_Post && 'artpulse_event' === $post->post_type) {
            return true;
        }

        return new WP_Error(
            'ap_org_event_missing',
            __('The requested event could not be found.', 'artpulse-management'),
            ['status' => 404]
        );
    }

    public function validate_event_status_param($value): bool
    {
        $allowed = ['publish', 'draft', 'pending'];
        return in_array($value, $allowed, true);
    }

    public function sanitize_event_status_param($value): string
    {
        $value = sanitize_key($value ?? '');
        return $value ?: 'publish';
    }

    public function sanitize_org_role($value): string
    {
        $value = sanitize_key($value ?? 'manager');
        if ('' === $value) {
            $value = 'manager';
        }

        return $value;
    }

    public function sanitize_range_param($value): int
    {
        $allowed = [7, 30, 90];
        $value   = (int) $value;
        if (!in_array($value, $allowed, true)) {
            return 30;
        }

        return $value;
    }

    public function validate_range_param($value): bool
    {
        $allowed = [7, 30, 90];
        return in_array((int) $value, $allowed, true);
    }

    private function ensure_org_access(WP_REST_Request $request, bool $require_edit): bool|WP_Error
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('You must be logged in to access organization data.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $user_id = get_current_user_id();
        $org_id  = $this->resolve_org_id($request, $user_id);
        if (is_wp_error($org_id)) {
            return $org_id;
        }

        if (!$this->user_can_manage_org($user_id, $org_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to manage this organization.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        if ($require_edit && !current_user_can('edit_post', $org_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You cannot modify this organization.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        $request->set_attribute(self::ATTR_ORG_ID, $org_id);

        return true;
    }

    private function resolve_org_id(WP_REST_Request $request, int $user_id): int|WP_Error
    {
        $org_id   = absint($request->get_param('org_id'));
        $event_id = absint($request->get_param('id'));

        if ($event_id > 0 && $org_id <= 0) {
            $event = get_post($event_id);
            if (!$event instanceof WP_Post || 'artpulse_event' !== $event->post_type) {
                return new WP_Error(
                    'ap_org_event_missing',
                    __('The requested event could not be found.', 'artpulse-management'),
                    ['status' => 404]
                );
            }

            $event_org = (int) get_post_meta($event_id, '_ap_event_organization', true);
            if ($event_org > 0) {
                $org_id = $event_org;
            }
        }

        if ($org_id <= 0) {
            $managed = $this->get_user_org_ids($user_id);
            if (count($managed) === 1) {
                $org_id = (int) $managed[0];
            }
        }

        if ($org_id <= 0) {
            return new WP_Error(
                'ap_org_missing',
                __('A valid organization must be specified.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $org = get_post($org_id);
        if (!$org instanceof WP_Post || 'artpulse_org' !== $org->post_type) {
            return new WP_Error(
                'ap_org_not_found',
                __('The organization could not be found.', 'artpulse-management'),
                ['status' => 404]
            );
        }

        return $org_id;
    }

    private function user_can_manage_org(int $user_id, int $org_id): bool
    {
        if ($user_id <= 0 || $org_id <= 0) {
            return false;
        }

        if (current_user_can('ap_is_org')) {
            return true;
        }

        $managed = $this->get_user_org_ids($user_id);

        return in_array($org_id, $managed, true);
    }

    private function get_resolved_org_id(WP_REST_Request $request): int
    {
        return (int) $request->get_attribute(self::ATTR_ORG_ID);
    }

    private function get_org_param_args(bool $required = false): array
    {
        return [
            'org_id' => [
                'description'       => __('The organization ID.', 'artpulse-management'),
                'type'              => 'integer',
                'required'          => $required,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    private function get_event_mutation_args(bool $is_update): array
    {
        return [
            'title'        => [
                'type'              => 'string',
                'required'          => !$is_update,
                'sanitize_callback' => [$this, 'sanitize_required_text'],
            ],
            'status'       => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => [$this, 'sanitize_event_status_param'],
                'validate_callback' => [$this, 'validate_event_status_param'],
            ],
            'start'        => [
                'type'              => ['string', 'null'],
                'required'          => false,
                'sanitize_callback' => [$this, 'sanitize_datetime_param'],
                'validate_callback' => [$this, 'validate_datetime_param'],
            ],
            'end'          => [
                'type'              => ['string', 'null'],
                'required'          => false,
                'sanitize_callback' => [$this, 'sanitize_datetime_param'],
                'validate_callback' => [$this, 'validate_datetime_param'],
            ],
            'venue'        => [
                'type'              => ['string', 'null'],
                'required'          => false,
                'sanitize_callback' => [$this, 'sanitize_optional_text'],
            ],
            'location'     => [
                'type'              => ['string', 'null'],
                'required'          => false,
                'sanitize_callback' => [$this, 'sanitize_optional_text'],
            ],
            'external_url' => [
                'type'              => ['string', 'null'],
                'required'          => false,
                'sanitize_callback' => [$this, 'sanitize_url_param'],
            ],
        ];
    }

    public function sanitize_required_text($value): string
    {
        return sanitize_text_field((string) ($value ?? ''));
    }

    public function sanitize_optional_text($value): string
    {
        return sanitize_text_field((string) ($value ?? ''));
    }

    public function sanitize_datetime_param($value): string
    {
        $value = (string) ($value ?? '');
        return trim($value);
    }

    public function validate_datetime_param($value): bool
    {
        $value = (string) ($value ?? '');
        if ('' === $value) {
            return true;
        }

        return false !== rest_parse_date($value);
    }

    public function sanitize_url_param($value): string
    {
        $value = (string) ($value ?? '');
        if ('' === $value) {
            return '';
        }

        return esc_url_raw($value);
    }

    private function sanitize_per_page(int $value): int
    {
        if ($value <= 0) {
            $value = self::DEFAULT_PER_PAGE;
        }

        return min($value, self::MAX_PER_PAGE);
    }

    private function sanitize_page(int $value): int
    {
        return max(1, $value);
    }

    private function persist_event_meta(int $event_id, int $org_id, WP_REST_Request $request, bool $partial = false): void
    {
        update_post_meta($event_id, '_ap_event_organization', $org_id);

        $map = [
            'start'        => '_ap_event_start',
            'end'          => '_ap_event_end',
            'venue'        => '_ap_event_venue',
            'location'     => '_ap_event_location',
            'external_url' => '_ap_event_external_url',
        ];

        foreach ($map as $param => $meta_key) {
            if ($partial && null === $request->get_param($param)) {
                continue;
            }

            $value = (string) $request->get_param($param);
            if ('' === $value) {
                delete_post_meta($event_id, $meta_key);
                continue;
            }

            update_post_meta($event_id, $meta_key, $value);
        }
    }

    private function format_event(int $event_id): array
    {
        $post = get_post($event_id);
        if (!$post instanceof WP_Post) {
            return [];
        }

        $data = [
            'id'         => $event_id,
            'title'      => get_the_title($event_id) ?: '',
            'status'     => $post->post_status,
            'start'      => (string) get_post_meta($event_id, '_ap_event_start', true),
            'end'        => (string) get_post_meta($event_id, '_ap_event_end', true),
            'venue'      => (string) get_post_meta($event_id, '_ap_event_venue', true),
            'location'   => (string) get_post_meta($event_id, '_ap_event_location', true),
            'externalUrl'=> $this->maybe_esc_url((string) get_post_meta($event_id, '_ap_event_external_url', true)),
            'edit_link'  => $this->maybe_esc_url((string) get_edit_post_link($event_id, '')), 
            'permalink'  => $this->maybe_esc_url((string) get_permalink($event_id)),
            'date'       => $post->post_date_gmt ?: $post->post_date,
        ];

        return $data;
    }

    private function maybe_esc_url(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        return esc_url_raw($value);
    }

    private function count_events(int $org_id): int
    {
        $query = new WP_Query([
            'post_type'      => 'artpulse_event',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_ap_event_organization',
                    'value' => $org_id,
                ],
            ],
        ]);

        return (int) $query->found_posts;
    }

    private function count_artists(int $org_id): int
    {
        $query = new WP_Query([
            'post_type'      => 'artpulse_artist',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_ap_artist_org',
                    'value' => $org_id,
                ],
            ],
        ]);

        return (int) $query->found_posts;
    }

    private function count_pending_events(int $org_id): int
    {
        $query = new WP_Query([
            'post_type'      => 'artpulse_event',
            'post_status'    => 'pending',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_ap_event_organization',
                    'value' => $org_id,
                ],
            ],
        ]);

        return (int) $query->found_posts;
    }

    private function get_recent_events(int $org_id): array
    {
        $query = new WP_Query([
            'post_type'      => 'artpulse_event',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => '_ap_event_organization',
                    'value' => $org_id,
                ],
            ],
        ]);

        $events = [];
        foreach ($query->posts as $post) {
            if ($post instanceof WP_Post) {
                $events[] = $this->format_event($post->ID);
            }
        }

        wp_reset_postdata();

        return $events;
    }

    private function get_recent_submissions(int $org_id): array
    {
        $query = new WP_Query([
            'post_type'      => 'artpulse_event',
            'post_status'    => 'pending',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => '_ap_event_organization',
                    'value' => $org_id,
                ],
            ],
        ]);

        $events = [];
        foreach ($query->posts as $post) {
            if ($post instanceof WP_Post) {
                $events[] = $this->format_event($post->ID);
            }
        }

        wp_reset_postdata();

        return $events;
    }

    private function build_roster_response(int $org_id, int $per_page, int $page): array
    {
        $per_page = $this->sanitize_per_page($per_page);
        $page     = $this->sanitize_page($page);
        $offset   = ($page - 1) * $per_page;

        $query = new WP_User_Query([
            'number'      => $per_page,
            'offset'      => $offset,
            'count_total' => true,
            'fields'      => 'all',
            'meta_query'  => $this->get_org_user_meta_query($org_id),
        ]);

        $items = [];
        foreach ($query->get_results() as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $items[] = [
                'id'     => (int) $user->ID,
                'name'   => $user->display_name ?: $user->user_login,
                'role'   => $this->format_org_role_label($user, $org_id),
                'avatar' => $this->maybe_esc_url(get_avatar_url((int) $user->ID, ['size' => 64]) ?: ''),
            ];
        }

        $total = (int) $query->get_total();

        return [
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) max(1, ceil($total / $per_page)),
            ],
        ];
    }

    private function get_org_user_meta_query(int $org_id): array
    {
        $org_id = absint($org_id);
        return [
            'relation' => 'OR',
            [
                'key'   => '_ap_org_ids',
                'value' => '"' . $org_id . '"',
                'compare' => 'LIKE',
            ],
            [
                'key'   => '_ap_org_post_id',
                'value' => $org_id,
            ],
            [
                'key'   => 'ap_organization_id',
                'value' => $org_id,
            ],
        ];
    }

    private function attach_user_to_org(int $user_id, int $org_id, string $role): void
    {
        if ($user_id <= 0 || $org_id <= 0) {
            return;
        }

        $existing = get_user_meta($user_id, '_ap_org_ids', true);
        if (!is_array($existing)) {
            $existing = $existing ? [(int) $existing] : [];
        }

        $existing[] = $org_id;
        $existing   = array_values(array_unique(array_filter(array_map('absint', $existing))));

        update_user_meta($user_id, '_ap_org_ids', $existing);
        update_user_meta($user_id, '_ap_org_post_id', $org_id);
        update_user_meta($user_id, 'ap_organization_id', $org_id);
        update_user_meta($user_id, '_ap_org_role_' . $org_id, $role);

        $user = get_user_by('id', $user_id);
        if ($user instanceof WP_User && !in_array('organization', (array) $user->roles, true)) {
            $user->add_role('organization');
        }
    }

    private function send_invite_email(string $email, int $org_id): void
    {
        if ('' === $email) {
            return;
        }

        $subject = __('Invitation to collaborate on ArtPulse', 'artpulse-management');

        $org_title = get_the_title($org_id) ?: __('an organization', 'artpulse-management');
        $message   = sprintf(
            /* translators: %s is the organization name. */
            __('You have been invited to collaborate with %s on ArtPulse. Create or log in to your account to accept this invitation.', 'artpulse-management'),
            $org_title
        );

        wp_mail($email, $subject, $message);
    }

    private function format_org_role_label(WP_User $user, int $org_id): string
    {
        $role = get_user_meta($user->ID, '_ap_org_role_' . $org_id, true);
        if ($role) {
            $role = sanitize_text_field((string) $role);
        }

        return $role ? ucfirst($role) : __('Manager', 'artpulse-management');
    }

    private function get_user_org_ids(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $ids = [];

        $meta = get_user_meta($user_id, '_ap_org_ids', true);
        if (is_array($meta)) {
            $ids = array_map('absint', $meta);
        } elseif ($meta) {
            $ids = [(int) $meta];
        }

        $legacy = (int) get_user_meta($user_id, '_ap_org_post_id', true);
        if ($legacy > 0) {
            $ids[] = $legacy;
        }

        $single = (int) get_user_meta($user_id, 'ap_organization_id', true);
        if ($single > 0) {
            $ids[] = $single;
        }

        $ids = array_filter(array_map('absint', $ids), fn ($id) => $id > 0);
        $ids = array_values(array_unique($ids));

        return $ids;
    }

    private function invalidate_overview_cache(int $org_id): void
    {
        $user_ids   = $this->get_org_manager_user_ids($org_id);
        $user_ids[] = get_current_user_id();

        $user_ids = array_values(array_unique(array_filter(array_map('absint', $user_ids))));

        foreach ($user_ids as $user_id) {
            delete_transient($this->get_overview_cache_key($user_id, $org_id));
        }
    }

    private function get_org_manager_user_ids(int $org_id): array
    {
        $query = new WP_User_Query([
            'fields'     => 'ID',
            'meta_query' => $this->get_org_user_meta_query($org_id),
        ]);

        return array_map('absint', $query->get_results());
    }

    private function get_overview_cache_key(int $user_id, int $org_id): string
    {
        return self::OVERVIEW_CACHE_PREFIX . $user_id . '_' . $org_id;
    }
}
