<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use EAD\Helpers\EmailHelper;

/**
 * Class SubmitEventEndpoint
 *
 * Handles event submissions via REST API.
 */
class SubmitEventEndpoint extends WP_REST_Controller {

    protected $namespace = 'artpulse/v1';
    protected $rest_base = 'events/submit';

    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'submitEvent' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );
    }

    /**
     * Handle event submission.
     */
    public function submitEvent(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', __('You must be logged in to submit an event.', 'artpulse-management'), [ 'status' => 401 ]);
        }

        // === Sanitize input ===
        $title       = sanitize_text_field($request->get_param('title'));
        $description = wp_kses_post($request->get_param('description'));
        $event_type  = sanitize_text_field($request->get_param('event_type'));
        $start_date  = sanitize_text_field($request->get_param('event_start_date'));
        $end_date    = sanitize_text_field($request->get_param('event_end_date'));
        $venue_name  = sanitize_text_field($request->get_param('venue_name'));
        $organizer   = sanitize_text_field($request->get_param('organizer'));
        $organizerEmail = sanitize_email($request->get_param('organizer_email'));

        // Address / geo fields
        $country  = sanitize_text_field($request->get_param('event_country'));
        $state    = sanitize_text_field($request->get_param('event_state'));
        $city     = sanitize_text_field($request->get_param('event_city'));
        $suburb   = sanitize_text_field($request->get_param('event_suburb'));
        $street   = sanitize_text_field($request->get_param('event_street_address'));
        $postcode = sanitize_text_field($request->get_param('event_postcode'));
        $featured = $request->get_param('event_featured') ? '1' : '';
        $latitude  = $request->get_param('latitude') !== null ? floatval($request->get_param('latitude')) : '';
        $longitude = $request->get_param('longitude') !== null ? floatval($request->get_param('longitude')) : '';

        // === Validate required fields ===
        if (empty($title))       return new WP_Error('missing_title', __('Title is required.', 'artpulse-management'), [ 'status' => 400 ]);
        if (empty($start_date))  return new WP_Error('missing_start', __('Start date is required.', 'artpulse-management'), [ 'status' => 400 ]);
        if (empty($end_date))    return new WP_Error('missing_end', __('End date is required.', 'artpulse-management'), [ 'status' => 400 ]);
        if (strtotime($start_date) > strtotime($end_date)) {
            return new WP_Error('invalid_dates', __('End date must be after start date.', 'artpulse-management'), [ 'status' => 400 ]);
        }
        if (empty($organizer))   return new WP_Error('missing_organizer', __('Organizer is required.', 'artpulse-management'), [ 'status' => 400 ]);
        if (empty($organizerEmail) || !is_email($organizerEmail)) {
            return new WP_Error('invalid_organizer_email', __('A valid organizer email is required.', 'artpulse-management'), [ 'status' => 400 ]);
        }

        // === Handle gallery upload ===
        $gallery_ids = [];
        if (!empty($_FILES['gallery']['name']) && is_array($_FILES['gallery']['name'])) {
            $files = $_FILES['gallery'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB

            // Limit number of uploaded images to five
            $uploaded_count = 0;
            if (is_array($files['name'])) {
                foreach ($files['name'] as $name) {
                    if (!empty($name)) {
                        $uploaded_count++;
                    }
                }
            }
            if ($uploaded_count > 5) {
                return new WP_Error(
                    'too_many_gallery_files',
                    __('You can upload a maximum of five gallery images.', 'artpulse-management'),
                    [ 'status' => 400 ]
                );
            }

            for ($i = 0; $i < count($files['name']); $i++) {
                if (empty($files['name'][$i])) continue;

                $file_type = $files['type'][$i];
                $file_size = $files['size'][$i];

                if (!in_array($file_type, $allowed_types)) {
                    return new WP_Error('invalid_gallery_type', __('One or more gallery files are invalid types.', 'artpulse-management'), [ 'status' => 400 ]);
                }
                if ($file_size > $max_size) {
                    return new WP_Error('gallery_file_too_large', __('One or more gallery files are too large.', 'artpulse-management'), [ 'status' => 400 ]);
                }

                $file_array = [
                    'name'     => $files['name'][$i],
                    'type'     => $file_type,
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $file_size,
                ];
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attachment_id = media_handle_sideload($file_array, 0);
                if (is_wp_error($attachment_id)) {
                    return new WP_Error('gallery_upload_failed', __('Gallery upload failed.', 'artpulse-management'), [ 'status' => 400 ]);
                }
                $gallery_ids[] = $attachment_id;
            }
        }

        // === Insert Event as Pending ===
        $eventPost = [
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'pending',
            'post_type'    => 'ead_event',
            'post_author'  => get_current_user_id(),
        ];

        $postId = wp_insert_post($eventPost, true);

        if (is_wp_error($postId)) {
            error_log('ArtPulse Management: Error submitting event: ' . $postId->get_error_message());
            return new WP_Error('failed_to_submit_event', __('Failed to submit event.', 'artpulse-management'), [ 'status' => 500 ]);
        }

        // === Save all meta ===
        update_post_meta($postId, 'event_date', $start_date);
        update_post_meta($postId, 'event_start_date', $start_date);
        update_post_meta($postId, 'event_end_date', $end_date);
        update_post_meta($postId, 'venue_name', $venue_name);
        update_post_meta($postId, 'event_organizer', $organizer);
        update_post_meta($postId, 'event_organizer_email', $organizerEmail);
        update_post_meta($postId, 'event_country', $country);
        update_post_meta($postId, 'event_state', $state);
        update_post_meta($postId, 'event_city', $city);
        update_post_meta($postId, 'event_suburb', $suburb);
        update_post_meta($postId, 'event_street_address', $street);
        update_post_meta($postId, 'event_postcode', $postcode);
        update_post_meta($postId, 'event_latitude', $latitude);
        update_post_meta($postId, 'event_longitude', $longitude);
        update_post_meta($postId, 'event_gallery', $gallery_ids);
        update_post_meta($postId, 'event_featured', $featured);

        if (!empty($event_type)) {
            wp_set_object_terms($postId, intval($event_type), 'ead_event_type');
        }

        // Set first gallery image as featured, if available
        if (!empty($gallery_ids)) {
            set_post_thumbnail($postId, $gallery_ids[0]);
        }

        // === Send admin notification (optional) ===
        $settings          = get_option('artpulse_notification_settings', []);
        $send_admin_notify = true;

        if (isset($settings['new_event_submission_notification'])) {
            $send_admin_notify = (bool) $settings['new_event_submission_notification'];
        }

        if ($send_admin_notify) {
            $adminEmail = get_option('admin_email');

            if (class_exists('EAD\\Helpers\\EmailHelper')) {
                EmailHelper::send_email(
                    $adminEmail,
                    __('New Event Submission', 'artpulse-management'),
                    sprintf(
                        __('A new event "%s" has been submitted and is pending review.', 'artpulse-management'),
                        esc_html($title)
                    )
                );
            } else {
                wp_mail(
                    $adminEmail,
                    __('New Event Submission', 'artpulse-management'),
                    sprintf(
                        __('A new event "%s" has been submitted and is pending review.', 'artpulse-management'),
                        esc_html($title)
                    )
                );
            }
        }

        $response = new WP_REST_Response([
            'success' => true,
            'message' => __('Event submitted successfully and is pending review.', 'artpulse-management'),
            'event_id'  => $postId,
        ], 201);

        $response->header('Location', rest_url($this->namespace . '/events/' . $postId));
        return $response;
    }

    /**
     * Permission check callback.
     */
    public function permissionsCheck(WP_REST_Request $request) {
        return is_user_logged_in();
    }

    /**
     * Define endpoint arguments.
     */
    public function getEndpointArgs() {
        return [
            'title'           => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('Event title.', 'artpulse-management'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'description'     => [
                'required'          => false,
                'type'              => 'string',
                'description'       => __('Event description.', 'artpulse-management'),
                'sanitize_callback' => 'wp_kses_post',
            ],
            'event_type'      => [
                'required'          => true,
                'type'              => 'integer',
                'description'       => __('Event type term ID.', 'artpulse-management'),
                'sanitize_callback' => 'absint',
            ],
            'event_start_date' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('Event start date.', 'artpulse-management'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'event_end_date' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('Event end date.', 'artpulse-management'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'venue_name'      => [
                'required'          => false,
                'type'              => 'string',
                'description'       => __('Venue name.', 'artpulse-management'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'organizer'       => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('Event organizer.', 'artpulse-management'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'organizer_email' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('Event organizer email.', 'artpulse-management'),
                'sanitize_callback' => 'sanitize_email',
            ],
            // Address/geo fields
            'event_country'   => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'event_state'     => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'event_city'      => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'event_suburb'    => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'event_street_address' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'event_postcode'  => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'event_featured' => [
                'required' => false,
                'type' => 'boolean',
                'sanitize_callback' => function($value){ return $value ? '1' : ''; },
            ],
            'latitude'        => [
                'required' => false,
                'type' => 'number',
                'sanitize_callback' => 'floatval',
            ],
            'longitude'       => [
                'required' => false,
                'type' => 'number',
                'sanitize_callback' => 'floatval',
            ],
            // Gallery handled via $_FILES not REST param
        ];
    }
}
