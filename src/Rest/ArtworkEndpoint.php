<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class ArtworkEndpoint
 *
 * Handles artwork data via the REST API.
 */
class ArtworkEndpoint extends WP_REST_Controller {

    /**
     * The namespace.
     *
     * @var string
     */
    protected $namespace = 'artpulse/v1';

    /**
     * The route name for the endpoint.
     *
     * @var string
     */
    protected $rest_base = 'artwork'; // Singular base, collections will be 'artworks'

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        // Collection route for GET (listing artworks) and POST (creating new artwork)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . 's', // e.g., /artpulse/v1/artworks
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_items'], // Get all artworks
                    'permission_callback' => [$this, 'get_items_permissions_check'],
                    'args'                => $this->get_collection_params(),
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_item'],
                    'permission_callback' => [$this, 'create_item_permissions_check'],
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
                ],
                // No schema here for collection routes, schema is for single item.
            ]
        );

        // Single item route for GET, PUT/POST, DELETE
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)', // e.g., /artpulse/v1/artwork/{id}
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_item'],
                    'permission_callback' => [$this, 'get_item_permissions_check'],
                    'args'                => [
                        'context' => [
                            'default' => 'view',
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE, // Covers POST, PUT, PATCH
                    'callback'            => [$this, 'update_item'],
                    'permission_callback' => [$this, 'update_item_permissions_check'],
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'delete_item'],
                    'permission_callback' => [$this, 'delete_item_permissions_check'],
                    'args'                => [
                        'force' => [
                            'default'     => false,
                            'description' => __('Whether to bypass Trash and force deletion.', 'artpulse-management'),
                            'type'        => 'boolean',
                        ],
                    ],
                ],
                'schema' => [$this, 'get_public_item_schema'], // Schema for single item route
            ]
        );
    }

    /**
     * Get the item schema, conforming to JSON Schema.
     * This defines what fields are expected in the request body and response.
     *
     * @return array
     */
    public function get_item_schema() {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'artwork',
            'type'       => 'object',
            'properties' => [
                'id'                => [
                    'description' => __('Unique identifier for the object.', 'artpulse-management'),
                    'type'        => 'integer',
                    'context'     => ['view', 'edit', 'embed'],
                    'readonly'    => true,
                ],
                'date'              => [
                    'description' => __('The date the object was published.', 'artpulse-management'),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => ['view', 'edit', 'embed'],
                    'readonly'    => true,
                ],
                // Define core post fields if you want them directly editable/viewable
                'artwork_title'        => [ // Changed from 'post_title' to match frontend form
                    'description' => __('The title of the artwork.', 'artpulse-management'),
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                ],
                'artwork_description'      => [ // Changed from 'post_content' to match frontend form
                    'description' => __('The content of the artwork (e.g., full description).', 'artpulse-management'),
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                ],
            ],
        ];

        // Add all custom meta fields from the Artwork Meta Box definition
        $meta_fields = $this->get_registered_artwork_meta_fields();
        foreach ($meta_fields as $key => $args) {
            list($type, $required, $sanitize_callback, $show_in_rest) = $args;

            // Skip 'artwork_image' as it's replaced by the gallery
            if ($key === 'artwork_image') {
                continue;
            }

            // 'artwork_title' and 'artwork_description' are already handled as core post fields
            if ($key === 'artwork_title' || $key === 'artwork_description') {
                continue;
            }

            if (!$show_in_rest) { // Skip fields not explicitly marked for REST API
                continue;
            }

            $rest_type = 'string'; // Default
            if ($type === 'integer') {
                $rest_type = 'integer';
            } elseif ($type === 'boolean') {
                $rest_type = 'boolean';
            }

            $schema['properties'][$key] = [
                'description'       => sprintf(__('Artwork %s.', 'artpulse-management'), str_replace('_', ' ', $key)),
                'type'              => $rest_type,
                'context'           => ['view', 'edit'],
                'required'          => $required, // Use the required setting from the meta box
                'sanitize_callback' => $sanitize_callback, // Attach sanitization callback
            ];
            // Add format for video URL
            if ($key === 'artwork_video_url') {
                $schema['properties'][$key]['format'] = 'uri';
            }
        }

        // Add the gallery images field explicitly
        $schema['properties']['artwork_gallery_images'] = [
            'description' => __('Array of attachment IDs for the artwork gallery.', 'artpulse-management'),
            'type'        => 'array',
            'items'       => [
                'type' => 'integer',
            ],
            'context'     => ['view', 'edit'],
            'required'    => false, // Can be empty
            'sanitize_callback' => function($value) {
                if (!is_array($value)) {
                    return [];
                }
                return array_map('absint', $value);
            },
        ];

        return $this->add_additional_fields_schema($schema);
    }

    /**
     * Get the query params for collections.
     *
     * @return array
     */
    public function get_collection_params() {
        return [
            'context'  => $this->get_schema_context(), // This is the line that caused the error
            'orderby'  => [
                'description' => __('Order collection by object attribute.', 'artpulse-management'),
                'type'        => 'string',
                'default'     => 'date',
                'enum'        => [
                    'date',
                    'id',
                    'modified',
                    'relevance',
                    'slug',
                    'title',
                    'author', // Added for artist filtering
                ],
            ],
            'order'    => [
                'description' => __('Order sort direction.', 'artpulse-management'),
                'type'        => 'string',
                'default'     => 'desc',
                'enum'        => [
                    'asc',
                    'desc',
                ],
            ],
            'per_page' => [
                'description' => __('Maximum number of items to be returned in result set.', 'artpulse-management'),
                'type'        => 'integer',
                'default'     => 10,
                'minimum'     => 1,
                'maximum'     => 100,
            ],
            'search'   => [
                'description' => __('Limit results to those matching a string.', 'artpulse-management'),
                'type'        => 'string',
            ],
            'author' => [ // Allow filtering by author (artist ID)
                'description'       => __('Limit results to those by a specific author ID.', 'artpulse-management'),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Check if a given request has access to read collection items.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_Error|bool
     */
    public function get_items_permissions_check($request) {
        // Publicly readable, or logged in for restricted content.
        // For simplicity, let's say any logged-in user can view.
        return current_user_can('read');
    }

    /**
     * Retrieve artworks.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_items($request) {
        $args = [
            'post_type'      => 'ead_artwork',
            'post_status'    => 'publish', // Only published artworks for public listing
            'posts_per_page' => $request->get_param('per_page'),
            'paged'          => $request->get_param('page'),
            'orderby'        => $request->get_param('orderby'),
            'order'          => $request->get_param('order'),
            's'              => $request->get_param('search'),
            'author'         => $request->get_param('author'),
        ];

        $cache_key = 'ead_artworks_' . md5( serialize( $args ) );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            $response = new WP_REST_Response( $cached['data'], 200 );
            $response->header( 'X-WP-Total', $cached['total'] );
            $response->header( 'X-WP-TotalPages', $cached['pages'] );

            return $response;
        }

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            return new WP_Error('no_artworks_found', __('No artworks found.', 'artpulse-management'), ['status' => 404]);
        }

        $data = [];
        foreach ($query->posts as $post) {
            $response = $this->prepare_item_for_response($post, $request);
            if (is_wp_error($response)) {
                return $response;
            }
            $data[] = $response->get_data();
        }

        $response = new WP_REST_Response( $data, 200 );
        $response->header( 'X-WP-Total', $query->found_posts );
        $response->header( 'X-WP-TotalPages', $query->max_num_pages );

        set_transient(
            $cache_key,
            [
                'data'  => $data,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages,
            ],
            5 * MINUTE_IN_SECONDS
        );

        return $response;
    }

    /**
     * Check if a given request has access to create items.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_Error|bool
     */
    public function create_item_permissions_check($request) {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', __('You must be logged in to submit artwork.', 'artpulse-management'), ['status' => rest_authorization_required_code()]);
        }
        // Check if the current user has permission to submit artworks.
        // We use the standard 'edit_posts' capability so contributors or higher
        // can create submissions without requiring a custom capability.
        return current_user_can('edit_posts');
    }

    /**
     * Create one item from the collection.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function create_item($request) {
        $prepared_args = $this->prepare_item_for_database($request);

        // Ensure post_content is always a string
        $prepared_args['post_content'] = (string) ( $prepared_args['post_content'] ?? '' );

        if (is_wp_error($prepared_args)) {
            return $prepared_args;
        }

        // Set default post status for new submissions
        $prepared_args['post_status'] = 'pending'; // Awaiting moderation
        $prepared_args['post_author'] = get_current_user_id();
        $prepared_args['post_type']   = 'ead_artwork';

        $post_id = wp_insert_post($prepared_args, true);

        if (is_wp_error($post_id)) {
            return new WP_Error('rest_cannot_create', __('Cannot create artwork.', 'artpulse-management'), ['status' => 500]);
        }

        // Handle meta fields and gallery images
        $this->update_additional_fields_for_object($post_id, $request);

        $post    = get_post($post_id);
        $response = $this->prepare_item_for_response($post, $request);

        if (is_wp_error($response)) {
            return $response;
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => __('Artwork submitted successfully and is awaiting moderation.', 'artpulse-management'),
                'data'    => $response->get_data(), // Return the prepared artwork data
            ],
            201
        );
    }

    /**
     * Check if a given request has access to read a specific item.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_Error|bool
     */
    public function get_item_permissions_check($request) {
        $post = get_post($request['id']);
        if (empty($post) || 'ead_artwork' !== $post->post_type) {
            return new WP_Error('rest_not_found', __('Artwork not found.', 'artpulse-management'), ['status' => 404]);
        }
        // Check if user can read this specific post (e.g., if it's private or pending)
        if (!current_user_can('read_post', $post->ID)) {
            return new WP_Error('rest_forbidden', __('You do not have permission to view this artwork.', 'artpulse-management'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /**
     * Get one item from the collection.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function get_item($request) {
        $post = get_post($request['id']);
        $response = $this->prepare_item_for_response($post, $request);
        if (is_wp_error($response)) {
            return $response;
        }
        return rest_ensure_response($response);
    }

    /**
     * Check if a given request has access to update a specific item.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_Error|bool
     */
    public function update_item_permissions_check($request) {
        $post = get_post($request['id']);
        if (empty($post) || 'ead_artwork' !== $post->post_type) {
            return new WP_Error('rest_not_found', __('Artwork not found.', 'artpulse-management'), ['status' => 404]);
        }
        // User must be the author or have 'edit_others_posts' capability
        if (get_current_user_id() !== (int) $post->post_author && !current_user_can('edit_others_posts')) {
            return new WP_Error('rest_forbidden', __('You do not have permission to edit this artwork.', 'artpulse-management'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /**
     * Update one item from the collection.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function update_item($request) {
        $post_id = (int) $request['id'];
        $post    = get_post($post_id);

        if (is_wp_error($post)) {
            return $post;
        }

        $prepared_args = $this->prepare_item_for_database($request);
        // Ensure post_content is always a string
        $prepared_args['post_content'] = (string) ( $prepared_args['post_content'] ?? '' );
        if (is_wp_error($prepared_args)) {
            return $prepared_args;
        }

        $prepared_args['ID'] = $post_id;
        // Do not change author or post_type on update unless specifically requested.
        unset($prepared_args['post_author']);
        unset($prepared_args['post_type']);

        $updated = wp_update_post($prepared_args, true);

        if (is_wp_error($updated)) {
            return new WP_Error('rest_cannot_update', __('Cannot update artwork.', 'artpulse-management'), ['status' => 500]);
        }

        // Handle meta fields and gallery images
        $this->update_additional_fields_for_object($post_id, $request);

        $post    = get_post($post_id); // Re-fetch updated post
        $response = $this->prepare_item_for_response($post, $request);

        if (is_wp_error($response)) {
            return $response;
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => __('Artwork updated successfully!', 'artpulse-management'),
                'data'    => $response->get_data(),
            ],
            200
        );
    }

    /**
     * Check if a given request has access to delete a specific item.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_Error|bool
     */
    public function delete_item_permissions_check($request) {
        $post = get_post($request['id']);
        if (empty($post) || 'ead_artwork' !== $post->post_type) {
            return new WP_Error('rest_not_found', __('Artwork not found.', 'artpulse-management'), ['status' => 404]);
        }
        // User must be able to delete this specific post
        return current_user_can('delete_post', $post->ID);
    }

    /**
     * Delete one item from the collection.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function delete_item($request) {
        $post = get_post($request['id']);
        $id   = (int) $request['id'];
        $force = isset($request['force']) ? (bool) $request['force'] : false;

        $previous_data = $this->prepare_item_for_response($post, $request);
        if (is_wp_error($previous_data)) {
            return $previous_data;
        }

        $result = wp_delete_post($id, $force);

        if (!$result) {
            return new WP_Error('rest_cannot_delete', __('The artwork cannot be deleted.', 'artpulse-management'), ['status' => 500]);
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => __('Artwork deleted successfully!', 'artpulse-management'),
                'data'    => [
                    'previous' => $previous_data->get_data(),
                    'deleted'  => true,
                ],
            ],
            200
        );
    }

    /**
     * Prepares a single item for create or update.
     * This method maps request parameters to WP_Post fields and meta fields.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_Error|array
     */
    protected function prepare_item_for_database($request) {
        $prepared_args = [];

        // Core Post Fields
        // Map frontend form fields (artwork_title, artwork_description) to core post fields
        if ($request->has_param('artwork_title')) {
            $prepared_args['post_title'] = sanitize_text_field($request->get_param('artwork_title') ?? '');
        }
        if ($request->has_param('artwork_description')) {
            $prepared_args['post_content'] = wp_kses_post($request->get_param('artwork_description') ?? '');
        }

        // Return the prepared args for wp_insert_post/wp_update_post.
        // Other meta fields are handled separately in update_additional_fields_for_object.
        return $prepared_args;
    }

    /**
     * Prepares the item for the REST response.
     *
     * @param mixed           $item    WordPress representation of the item.
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
     */
    public function prepare_item_for_response($item, $request) {
        $data = [
            'id'                => (int) $item->ID,
            'date'              => mysql_to_rfc3339($item->post_date),
            'modified'          => mysql_to_rfc3339($item->post_modified),
            'status'            => $item->post_status,
            'artwork_title'     => get_the_title($item), // Use the artwork_title meta if preferred, or get_the_title() for post_title
            'artwork_description' => apply_filters('the_content', (string) $item->post_content), // Use apply_filters for content
            'author_id'         => (int) $item->post_author,
            'author_name'       => get_the_author_meta('display_name', $item->post_author),
            'link'              => get_permalink($item->ID),
        ];

        // Add all custom meta fields
        $meta_fields = $this->get_registered_artwork_meta_fields();
        foreach ($meta_fields as $key => $args) {
            list($type, , , $show_in_rest) = $args;

            // Skip 'artwork_image' as it's replaced by the gallery
            if ($key === 'artwork_image') {
                continue;
            }
            // Skip if already handled as core post fields
            if ($key === 'artwork_title' || $key === 'artwork_description') {
                continue;
            }
            if (!$show_in_rest) {
                continue;
            }

            $value = (string) ead_get_meta($item->ID, $key);
            // Convert boolean strings to actual booleans for JSON output
            if ($type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            $data[$key] = $value;
        }

        // Handle artwork gallery images specifically
        $gallery_image_ids = ead_get_meta($item->ID, '_ead_artwork_gallery_images') ?? [];
        if (!is_array($gallery_image_ids)) {
            $gallery_image_ids = [];
        }
        $gallery_images_data = [];
        foreach ($gallery_image_ids as $img_id) {
            $img_url = wp_get_attachment_image_url($img_id, 'full'); // Or 'medium', 'large'
            if ($img_url) {
                $gallery_images_data[] = [
                    'id'  => $img_id,
                    'url' => $img_url,
                ];
            }
        }
        $data['artwork_gallery_images'] = $gallery_images_data;

        // Ensure featured image is set if it's the first gallery image
        $featured_image_id = get_post_thumbnail_id($item->ID);
        if (empty($featured_image_id) && !empty($gallery_image_ids[0])) {
            $featured_image_id = $gallery_image_ids[0];
        }
        $data['featured_image_url'] = $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'large') : null;


        $context = ! empty( $request['context'] ) ? $request['context'] : 'view';
        $data    = $this->filter_response_by_context( $data, $context );
        $response = rest_ensure_response( $data );
        $response->add_link( 'self', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $item->ID ) ) );
        return $response;
    }

    /**
     * Update additional fields for object.
     * This method handles saving custom meta fields and gallery images.
     *
     * @param int             $object_id Object ID.
     * @param WP_REST_Request $request   Request object.
     */
    public function update_additional_fields_for_object($object_id, $request) {
        $meta_fields = $this->get_registered_artwork_meta_fields();

        foreach ($meta_fields as $field_key => $args) {
            list($type, , $sanitize_callback, $show_in_rest) = $args;

            // Skip 'artwork_image' as it's replaced by the gallery
            if ($field_key === 'artwork_image') {
                continue;
            }
            // Skip if handled as core post fields
            if ($field_key === 'artwork_title' || $field_key === 'artwork_description') {
                continue;
            }
            if (!$show_in_rest) {
                continue;
            }

            if ($request->has_param($field_key)) {
                $value = $request->get_param($field_key);

                if (is_callable($sanitize_callback)) {
                    $value = call_user_func($sanitize_callback, $value);
                }

                update_post_meta($object_id, $field_key, $value);
            } elseif ($type === 'boolean') {
                // If a boolean field is not present in the request, it means it was unchecked.
                update_post_meta($object_id, $field_key, false);
            }
        }

        // Handle artwork gallery images
        if ($request->has_param('artwork_gallery_images')) {
            $image_ids = $request->get_param('artwork_gallery_images');
            $sanitized_image_ids = [];

            if (is_array($image_ids)) {
                foreach ($image_ids as $img_id) {
                    $sanitized_id = absint($img_id);
                    if ($sanitized_id > 0) {
                        $sanitized_image_ids[] = $sanitized_id;
                        // Set post_parent for each attached image to associate it with the artwork
                        wp_update_post([
                            'ID'          => $sanitized_id,
                            'post_parent' => $object_id,
                        ]);
                    }
                }
            }

            // Save the array of image IDs to post meta
            update_post_meta($object_id, '_ead_artwork_gallery_images', $sanitized_image_ids);

            // Set the first image as the featured image (thumbnail)
            if (!empty($sanitized_image_ids[0])) {
                set_post_thumbnail($object_id, $sanitized_image_ids[0]);
            } else {
                delete_post_thumbnail($object_id); // Remove featured image if no gallery images
            }
        }
    }

    /**
     * Helper to get artwork meta fields mirroring src/Admin/MetaBoxesArtwork.php's definition.
     * This is crucial for dynamic processing of fields.
     *
     * @return array
     */
    private function get_registered_artwork_meta_fields() {
        // This array should directly reflect the structure in src/Admin/MetaBoxesArtwork.php
        // The last boolean value indicates 'show_in_rest'
        return [
            'artwork_title'       => ['string',  true, 'sanitize_text_field',       true],
            'artwork_artist'      => ['string',  true, 'sanitize_text_field',       true],
            'artwork_medium'      => ['string',  true, 'sanitize_text_field',       true],
            'artwork_dimensions'  => ['string',  true, 'sanitize_text_field',       true],
            'artwork_year'        => ['integer', true, 'absint',                    true],
            'artwork_materials'   => ['textarea',true, 'sanitize_textarea_field',   true],
            'artwork_price'       => ['string',  true, 'sanitize_text_field',       true],
            'artwork_provenance'  => ['textarea',true, 'sanitize_textarea_field',   true],
            'artwork_edition'     => ['string',  true, 'sanitize_text_field',       true],
            'artwork_tags'        => ['string',  true, 'sanitize_text_field',       true],
            'artwork_description' => ['textarea',true, 'sanitize_textarea_field',   true],
            'artwork_image'       => ['integer', true, 'absint',                    true], // This will be superseded by gallery
            'artwork_video_url'   => ['string',  true, 'esc_url_raw',               true],
            // Not required for frontend submissions; defaults to false when not
            // present in the request.
            'artwork_featured'    => ['boolean', false, 'rest_sanitize_boolean',     true],
        ];
    }

    /**
     * Fallback for get_schema_context() for older WP versions.
     *
     * @since 5.3.0 The method was added to WP_REST_Controller.
     * @return array
     */
    protected function get_schema_context() {
        return [ 'view', 'edit', 'embed' ];
    }
}
