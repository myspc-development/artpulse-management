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
    private const JPEG_FIXTURE_BASE64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhUQEhIWFRUVFRUVFRUVFRUVFRUVFRUXFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy0lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIALcBEwMBIgACEQEDEQH/xAAbAAACAgMBAAAAAAAAAAAAAAAEBQMGAAECB//EADkQAAEDAgMFBgQEBQMFAQAAAAEAAhEDIQQSMUEFUWEGEyJxgZGh8BRCUrHB0fAjM2KCktLh8RZTc4KS/8QAGgEAAwEBAQEAAAAAAAAAAAAAAAECAwQFBv/EACcRAQEAAgICAwACAwEAAAAAAAABAhESITFBEyJRYXGB8AUiMv/aAAwDAQACEQMRAD8A9ziIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiIP/Z';

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            $this->markTestSkipped('GD/Imagick not available.');
        }

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

        [$tempFile, $fileSize] = self::create_temp_file_from_base64(self::JPEG_FIXTURE_BASE64, 'fixture-image.jpg');

        $_POST = [
            'title'          => 'Form Submission Event',
            'description'    => 'Event description from the form.',
            'event_date'     => '2024-12-01',
            'event_location' => 'Test Location',
            'event_type'     => '',
        ];

        $_FILES = [
            'event_flyer' => [
                'name'     => 'fixture-image.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => $tempFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => $fileSize,
            ],
        ];

        $post_id = OrganizationEventForm::handle_submission(false);
        $this->assertIsInt($post_id);

        $this->assertTrue(has_post_thumbnail($post_id));
        $attachment_id = get_post_thumbnail_id($post_id);
        $this->assertNotEmpty($attachment_id);
        $this->assertSame('attachment', get_post_type($attachment_id));

        $metadata = wp_get_attachment_metadata($attachment_id);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('sizes', $metadata);
        $this->assertNotEmpty($metadata['sizes']);

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    public function test_rest_submission_sets_featured_image(): void
    {
        $attachment_id = self::attach_from_base64(self::JPEG_FIXTURE_BASE64);

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

        $image_url = wp_get_attachment_url($attachment_id);
        $this->assertNotFalse($image_url);
        $this->assertContains($image_url, $data['images']);

        $metadata = wp_get_attachment_metadata($thumbnail_id);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('sizes', $metadata);
        $this->assertNotEmpty($metadata['sizes']);
    }

    public function test_single_template_renders_featured_image_html(): void
    {
        $attachment_id = self::attach_from_base64(self::JPEG_FIXTURE_BASE64);
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
        $this->assertMatchesRegularExpression('/<div class="nectar-portfolio-single-media">.*<img[^>]+src="[^"]+"/s', $output);
        $this->assertMatchesRegularExpression('/<img[^>]+class="[^"]*ap-event-img[^"]*"/i', $output);
        $this->assertStringContainsString('loading="lazy"', $output);
        $this->assertStringContainsString('decoding="async"', $output);
        $this->assertStringContainsString('alt="Single Template Event"', $output);

        $uploads = wp_get_upload_dir();
        $this->assertStringContainsString($uploads['baseurl'], $output);
        $this->assertStringContainsString((string) $expected_url, $output);

        wp_reset_postdata();
        wp_reset_query();
    }

    public function test_single_template_falls_back_to_submission_image(): void
    {
        $attachment_id = self::attach_from_base64(self::JPEG_FIXTURE_BASE64);
        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Fallback Single Image Event',
        ]);

        update_post_meta($event_id, '_ap_submission_images', [$attachment_id]);

        global $wp_query;
        $wp_query = new WP_Query([
            'p'         => $event_id,
            'post_type' => 'artpulse_event',
        ]);

        ob_start();
        include dirname(__DIR__, 2) . '/templates/salient/content-artpulse_event.php';
        $output = ob_get_clean();

        $best = \ArtPulse\Core\ImageTools::best_image_src($attachment_id);
        $this->assertIsArray($best);
        $this->assertStringContainsString('nectar-portfolio-single-media', $output);
        $this->assertStringContainsString((string) $best['url'], $output);
        $this->assertMatchesRegularExpression('/<img[^>]+class="[^"]*ap-event-img[^"]*"/i', $output);
        $this->assertStringContainsString('loading="lazy"', $output);
        $this->assertStringContainsString('decoding="async"', $output);
        $this->assertStringContainsString('alt="Fallback Single Image Event"', $output);

        wp_reset_postdata();
        wp_reset_query();
    }

    public function test_single_template_renders_placeholder_without_images(): void
    {
        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Placeholder Event',
        ]);

        global $wp_query;
        $wp_query = new WP_Query([
            'p'         => $event_id,
            'post_type' => 'artpulse_event',
        ]);

        ob_start();
        include dirname(__DIR__, 2) . '/templates/salient/content-artpulse_event.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('<div class="ap-event-placeholder" aria-hidden="true"></div>', $output);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]+ap-event-img/', $output);

        wp_reset_postdata();
        wp_reset_query();
    }

    public function test_archive_renders_thumbnail(): void
    {
        $attachment_id = self::attach_from_base64(self::JPEG_FIXTURE_BASE64);
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

        $callback = static function (string $slug, ?string $name = null): void {
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
        if (!$expected_url) {
            $expected_url = wp_get_attachment_image_url($attachment_id, 'full');
        }
        $this->assertNotFalse($expected_url);
        $this->assertStringContainsString('<div class="ap-test-archive-callback">', $output);
        $this->assertStringContainsString((string) $expected_url, $output);
        $this->assertMatchesRegularExpression('/<img[^>]+src="[^"]+"/i', $output);

        wp_reset_postdata();
        wp_reset_query();
    }

    public function test_archive_template_uses_submission_image_when_missing_thumbnail(): void
    {
        $attachment_id = self::attach_from_base64(self::JPEG_FIXTURE_BASE64);
        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Archive Submission Fallback Event',
        ]);

        update_post_meta($event_id, '_ap_submission_images', [$attachment_id]);

        global $wp_query;
        $wp_query = new WP_Query([
            'post_type'      => 'artpulse_event',
            'posts_per_page' => 1,
            'post__in'       => [$event_id],
        ]);

        $callback = static function (string $slug, ?string $name = null): void {
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

        $best = \ArtPulse\Core\ImageTools::best_image_src($attachment_id);
        $this->assertIsArray($best);
        $this->assertStringContainsString('<div class="ap-test-archive-callback">', $output);
        $this->assertStringContainsString((string) $best['url'], $output);
        $this->assertMatchesRegularExpression('/<img[^>]+src="[^"]+"/i', $output);

        wp_reset_postdata();
        wp_reset_query();
    }

    private static function attach_from_base64(string $b64, string $name = 'fixture.jpg'): int
    {
        $data = base64_decode($b64, true);
        if ($data === false) {
            throw new \RuntimeException('Unable to decode base64 fixture data.');
        }

        $upload = wp_upload_bits($name, null, $data);
        if (!empty($upload['error'])) {
            throw new \RuntimeException('Failed to write fixture image to uploads directory: ' . $upload['error']);
        }

        $file = $upload['file'];
        $type = wp_check_filetype(basename($file), null)['type'] ?? 'image/jpeg';
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $type,
            'post_title'     => 'fixture-image',
            'post_status'    => 'inherit',
        ], $file);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $meta = wp_generate_attachment_metadata($attachment_id, $file);
        wp_update_attachment_metadata($attachment_id, $meta);

        return (int) $attachment_id;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function create_temp_file_from_base64(string $b64, string $name = 'fixture.jpg'): array
    {
        $data = base64_decode($b64, true);
        if ($data === false) {
            throw new \RuntimeException('Unable to decode base64 fixture data.');
        }

        $temp_file = wp_tempnam($name);
        if ($temp_file === false) {
            throw new \RuntimeException('Unable to create temporary upload file.');
        }

        $bytes_written = file_put_contents($temp_file, $data);
        if ($bytes_written === false) {
            throw new \RuntimeException('Unable to write fixture data to temporary upload file.');
        }

        $size = filesize($temp_file);

        return [$temp_file, $size !== false ? $size : strlen($data)];
    }
}
