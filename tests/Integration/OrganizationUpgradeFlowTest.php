<?php

namespace ArtPulse\Tests\Integration;

use ArtPulse\Admin\UpgradeReviewsController;
use ArtPulse\Core\RoleUpgradeManager;
use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\OrgBuilderShortcode;
use ArtPulse\Frontend\OrganizationEventForm;
use ArtPulse\Rest\SubmissionRestController;
use WP_Post;
use WP_REST_Request;
use WP_UnitTestCase;

class OrganizationUpgradeFlowTest extends WP_UnitTestCase
{
    private const JPEG_FIXTURE_BASE64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhUQEhIWFRUVFRUVFRUVFRUVFRUVFRUXFhUVFRUYHSggGholGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy0lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIALcBEwMBIgACEQEDEQH/xAAbAAACAgMBAAAAAAAAAAAAAAABAgMEBQYAB//EADkQAAEDAgMFBgQEBQMFAQAAAAEAAhEDIQQSMUEFUWEGEyJxgZGh8BRCUrHB0fAjM2KCktLh8RZTc4KS/8QAGgEAAwEBAQEAAAAAAAAAAAAAAAECAwQFBv/EACcRAQEAAgICAwACAwEAAAAAAAABAhESITFBEyJRYXGB8AUiMv/aAAwDAQACEQMRAD8A9ziIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiIP/Z';

    protected function tearDown(): void
    {
        parent::tearDown();
        $_POST  = [];
        $_FILES = [];
    }

    public function test_builder_shows_pending_notice_for_pending_request(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        $org_id  = $this->create_org_for_user($user_id, 'draft');

        RoleUpgradeManager::attach_owner($org_id, $user_id);
        UpgradeReviewRepository::create_org_upgrade($user_id, $org_id);

        wp_set_current_user($user_id);

        $output = OrgBuilderShortcode::render();

        $this->assertStringContainsString('pending', $output);
    }

    public function test_builder_allows_owner_to_view_builder(): void
    {
        $user_id = self::factory()->user->create(['role' => 'organization']);
        $org_id  = $this->create_org_for_user($user_id, 'publish');
        RoleUpgradeManager::attach_owner($org_id, $user_id);

        wp_set_current_user($user_id);

        $output = OrgBuilderShortcode::render();

        $this->assertStringContainsString('ap-org-builder', $output);
    }

    public function test_admin_approval_grants_role_and_sends_single_email(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        $org_id  = $this->create_org_for_user($user_id, 'draft');
        delete_post_meta($org_id, '_ap_owner_user');

        $review_id = UpgradeReviewRepository::create_org_upgrade($user_id, $org_id);
        $review    = get_post($review_id);
        $this->assertInstanceOf(WP_Post::class, $review);

        $mailer = tests_retrieve_phpmailer_instance();
        $mailer->mock_sent = [];

        $reflector = new \ReflectionClass(UpgradeReviewsController::class);
        $approve   = $reflector->getMethod('approve');
        $approve->setAccessible(true);
        $approve->invoke(null, $review);

        $updated = get_post($org_id);
        $this->assertSame('publish', $updated->post_status);
        $this->assertSame($user_id, (int) get_post_meta($org_id, '_ap_owner_user', true));

        $user = get_user_by('id', $user_id);
        $this->assertContains('organization', $user->roles);

        $emails = array_filter($mailer->mock_sent, static fn($mail) => false !== strpos($mail['subject'], 'upgrade'));
        $this->assertCount(1, $emails);
        $this->assertNotEmpty(get_user_meta($user_id, '_ap_upgrade_notified_organization', true));
    }

    public function test_admin_denial_sanitizes_reason_and_notifies_member(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        $org_id  = $this->create_org_for_user($user_id, 'draft');

        $review_id = UpgradeReviewRepository::create_org_upgrade($user_id, $org_id);
        $review    = get_post($review_id);
        $this->assertInstanceOf(WP_Post::class, $review);

        $mailer = tests_retrieve_phpmailer_instance();
        $mailer->mock_sent = [];

        $reflector = new \ReflectionClass(UpgradeReviewsController::class);
        $deny       = $reflector->getMethod('deny');
        $deny->setAccessible(true);

        $result = $deny->invoke(null, $review, " <strong>Insufficient</strong> details ");
        $this->assertTrue($result);

        $updated_review = get_post($review_id);
        $this->assertSame(UpgradeReviewRepository::STATUS_DENIED, UpgradeReviewRepository::get_status($updated_review));
        $this->assertSame('Insufficient details', UpgradeReviewRepository::get_reason($updated_review));

        $emails = array_filter(
            $mailer->mock_sent,
            static fn($mail) => false !== strpos($mail['subject'], 'upgrade request')
        );

        $this->assertCount(1, $emails);

        $email = array_shift($emails);
        $this->assertNotFalse(strpos($email['body'], 'Insufficient details'));
    }

    public function test_save_images_updates_gallery_order_and_featured_image(): void
    {
        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            $this->markTestSkipped('GD/Imagick not available.');
        }

        $user_id = self::factory()->user->create(['role' => 'organization']);
        $org_id  = $this->create_org_for_user($user_id, 'publish');
        RoleUpgradeManager::attach_owner($org_id, $user_id);

        $attachment_one = $this->create_attachment_from_fixture('gallery-one.jpg');
        $attachment_two = $this->create_attachment_from_fixture('gallery-two.jpg');

        update_post_meta($org_id, '_ap_gallery_ids', [$attachment_one, $attachment_two]);
        set_post_thumbnail($org_id, $attachment_one);

        $_POST = [
            'existing_gallery_ids' => [$attachment_one, $attachment_two],
            'gallery_order'        => [
                $attachment_one => 2,
                $attachment_two => 1,
            ],
            'ap_featured_image'    => (string) $attachment_two,
        ];
        $_FILES = [];

        $reflector = new \ReflectionClass(OrgBuilderShortcode::class);
        $method    = $reflector->getMethod('save_images');
        $method->setAccessible(true);
        $errors = $method->invoke(null, $org_id);

        $this->assertSame([], $errors);
        $this->assertSame([$attachment_two, $attachment_one], get_post_meta($org_id, '_ap_gallery_ids', true));
        $this->assertSame($attachment_two, get_post_thumbnail_id($org_id));
    }

    public function test_event_form_locks_organization_to_owner(): void
    {
        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            $this->markTestSkipped('GD/Imagick not available.');
        }

        $owner_id = self::factory()->user->create(['role' => 'organization']);
        $owned_org_id = $this->create_org_for_user($owner_id, 'publish');
        RoleUpgradeManager::attach_owner($owned_org_id, $owner_id);

        $other_org_id = $this->create_org_for_user(self::factory()->user->create(), 'publish');

        wp_set_current_user($owner_id);

        [$temp_file, $file_size] = self::createTempFileFromBase64(self::JPEG_FIXTURE_BASE64, 'event-flyer.jpg');

        $_POST = [
            'title'          => 'Owner Event',
            'description'    => 'Description',
            'event_date'     => '2025-01-01',
            'event_location' => 'Gallery Space',
            'event_type'     => '',
            'org_id'         => $other_org_id,
        ];

        $_FILES = [
            'event_flyer' => [
                'name'     => 'event-flyer.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => $temp_file,
                'error'    => UPLOAD_ERR_OK,
                'size'     => $file_size,
            ],
        ];

        $post_id = OrganizationEventForm::handle_submission(false);
        $this->assertIsInt($post_id);

        $this->assertSame($owned_org_id, (int) get_post_meta($post_id, '_ap_event_organization', true));

        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
    }

    public function test_rest_submission_locks_event_to_owned_org(): void
    {
        $owner_id = self::factory()->user->create(['role' => 'organization']);
        $owned_org_id = $this->create_org_for_user($owner_id, 'publish');
        RoleUpgradeManager::attach_owner($owned_org_id, $owner_id);

        $other_org_id = $this->create_org_for_user(self::factory()->user->create(), 'publish');

        wp_set_current_user($owner_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type'          => 'artpulse_event',
            'title'              => 'REST Event',
            'content'            => 'REST body',
            'event_date'         => '2025-02-01',
            'event_location'     => 'Main Hall',
            'event_organization' => $other_org_id,
        ]);

        $response = SubmissionRestController::handle_submission($request);

        $this->assertNotWPError($response);
        $data    = $response->get_data();
        $post_id = (int) $data['id'];

        $this->assertSame($owned_org_id, (int) get_post_meta($post_id, '_ap_event_organization', true));
    }

    public function test_rest_permissions_block_event_submission_without_org(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type'      => 'artpulse_event',
            'title'          => 'Unauthorized Event',
            'event_date'     => '2025-03-01',
            'event_location' => 'Secret Space',
        ]);

        $error = SubmissionRestController::permissions_check($request);
        $this->assertWPError($error);
    }

    private function create_org_for_user(int $user_id, string $status = 'draft'): int
    {
        $org_id = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => $status,
            'post_title'  => 'Org ' . $user_id,
            'post_author' => $user_id,
        ]);

        return (int) $org_id;
    }

    private function create_attachment_from_fixture(string $filename): int
    {
        [$temp_file, $file_size] = self::createTempFileFromBase64(self::JPEG_FIXTURE_BASE64, $filename);

        $upload = wp_upload_bits($filename, null, file_get_contents($temp_file));
        $filetype = wp_check_filetype($filename);

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $filetype['type'] ?? 'image/jpeg',
            'post_title'     => sanitize_file_name($filename),
            'post_status'    => 'inherit',
        ], $upload['file']);

        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));

        if (file_exists($temp_file)) {
            unlink($temp_file);
        }

        return (int) $attachment_id;
    }

    private static function createTempFileFromBase64(string $base64, string $filename): array
    {
        $data = base64_decode($base64);
        $temp = wp_tempnam($filename);

        if (false === $temp) {
            throw new \RuntimeException('Unable to create temporary file for fixture.');
        }

        file_put_contents($temp, $data);

        return [$temp, strlen($data)];
    }
}
