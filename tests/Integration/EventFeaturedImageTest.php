<?php

namespace ArtPulse\Tests\Integration;

use ArtPulse\Core\PostTypeRegistrar;
use ArtPulse\Frontend\OrganizationEventForm;
use ArtPulse\Rest\SubmissionRestController;
use WP_Query;
use WP_REST_Request;
use WP_UnitTestCase;

class EventFeaturedImageTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PostTypeRegistrar::register();
        add_theme_support('post-thumbnails');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_POST = [];
        $_FILES = [];
    }

    public function test_form_submission_sets_featured_image(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $tempFile = wp_tempnam('fixture-image.jpg');
        $this->assertNotFalse($tempFile, 'Failed to create temporary upload file.');
        $bytesWritten = file_put_contents($tempFile, $this->getFixtureImageContents());
        $this->assertNotFalse($bytesWritten, 'Failed to write fixture image to temporary file.');

        $_POST = [
            'title'          => 'Form Submission Event',
            'description'    => 'Event description from the form.',
            'event_date'     => '2024-12-01',
            'event_location' => 'Test Location',
            'event_type'     => '',
        ];

        $_FILES = [
            'event_flyer' => [
                'name'     => 'image.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => $tempFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tempFile),
            ],
        ];

        $post_id = OrganizationEventForm::handle_submission(false);
        $this->assertIsInt($post_id);

        $this->assertTrue(has_post_thumbnail($post_id));
        $attachment_id = get_post_thumbnail_id($post_id);
        $this->assertNotEmpty($attachment_id);
        $this->assertSame('attachment', get_post_type($attachment_id));

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('sizes', $metadata);
        $this->assertNotEmpty($metadata['sizes']);
    }

    public function test_rest_submission_sets_featured_image(): void
    {
        $attachment_id = $this->createAttachmentFromFixture();

        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type'  => 'artpulse_event',
            'title'      => 'REST Submission Event',
            'content'    => 'Submitted through REST.',
            'event_date' => '2024-12-02',
            'image_ids'  => [$attachment_id],
        ]);

        $response = SubmissionRestController::handle_submission($request);
        $data = $response->get_data();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);

        $post_id = (int) $data['id'];
        $this->assertTrue(has_post_thumbnail($post_id));

        $thumbnail_id = get_post_thumbnail_id($post_id);
        $this->assertSame($attachment_id, $thumbnail_id);

        $metadata = wp_get_attachment_metadata($thumbnail_id);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('sizes', $metadata);
        $this->assertNotEmpty($metadata['sizes']);
    }

    public function test_single_template_renders_featured_image_html(): void
    {
        $attachment_id = $this->createAttachmentFromFixture();
        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Single Template Event',
            'post_content'=> 'Event body copy.',
        ]);
        set_post_thumbnail($event_id, $attachment_id);

        global $wp_query;
        $wp_query = new WP_Query([
            'p'         => $event_id,
            'post_type' => 'artpulse_event',
        ]);

        ob_start();
        include dirname(__DIR__, 2) . '/templates/salient/content-artpulse_event.php';
        $output = ob_get_clean();

        $expected_url = wp_get_attachment_image_url($attachment_id, 'full');
        $this->assertNotFalse($expected_url);
        $this->assertStringContainsString('nectar-portfolio-single-media', $output);
        $this->assertStringContainsString((string) $expected_url, $output);

        wp_reset_postdata();
        wp_reset_query();
    }

    public function test_archive_renders_thumbnail(): void
    {
        $attachment_id = $this->createAttachmentFromFixture();
        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Archive Template Event',
        ]);
        set_post_thumbnail($event_id, $attachment_id);

        global $wp_query;
        $wp_query = new WP_Query([
            'post_type'      => 'artpulse_event',
            'posts_per_page' => 1,
            'post__in'       => [$event_id],
        ]);

        $callback = static function () {
            echo '<div class="ap-test-archive-callback">';
            while (have_posts()) {
                the_post();
                the_post_thumbnail('medium');
            }
            echo '</div>';
            rewind_posts();
        };

        add_action('get_template_part_templates/salient/content', $callback, 10, 2);

        ob_start();
        include dirname(__DIR__, 2) . '/templates/salient/archive-artpulse_event.php';
        $output = ob_get_clean();

        remove_action('get_template_part_templates/salient/content', $callback, 10);

        $expected_url = wp_get_attachment_image_url($attachment_id, 'medium');
        $this->assertNotFalse($expected_url);
        $this->assertStringContainsString('<div class="ap-test-archive-callback">', $output);
        $this->assertStringContainsString((string) $expected_url, $output);

        wp_reset_postdata();
        wp_reset_query();
    }

    private function createAttachmentFromFixture(): int
    {
        $upload = wp_upload_bits('fixture-image.jpg', null, $this->getFixtureImageContents());
        $this->assertIsArray($upload);
        $this->assertEmpty($upload['error']);

        $filetype = wp_check_filetype(basename($upload['file']), null);

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $filetype['type'] ?? 'image/jpeg',
            'post_title'     => 'Fixture Image',
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $upload['file']);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return (int) $attachment_id;
    }

    private function getFixtureImageContents(): string
    {
        $base64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhUQEhIWFRUVFRUVFRUVFRUVFRUVFRUXFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy0lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIALcBEwMBIgACEQEDEQH/xAAbAAACAgMBAAAAAAAAAAAAAAAEBQMGAAECB//EADkQAAEDAgMFBgQEBQMFAQAAAAEAAhEDIQQSMUEFUWEGEyJxgZGh8BRCUrHB0fAjM2KCktLh8RZTc4KS/8QAGgEAAwEBAQEAAAAAAAAAAAAAAAECAwQFBv/EACcRAQEAAgICAwACAwEAAAAAAAABAhESITFBEyJRYXGB8AUiMv/aAAwDAQACEQMRAD8A9ziIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiIP/Z';

        $contents = base64_decode($base64, true);
        $this->assertNotFalse($contents, 'Failed to decode base64 fixture image.');

        return $contents;
    }
}
