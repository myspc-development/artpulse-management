<?php

namespace Tests\Rest;

use ArtPulse\Core\Capabilities;
use WP_REST_Request;
use WP_UnitTestCase;

class PortfolioControllerTest extends WP_UnitTestCase
{
    private $user_id;
    private $post_id;

    protected function set_up(): void
    {
        parent::set_up();

        Capabilities::add_roles_and_capabilities();

        $this->user_id = $this->factory->user->create([
            'role' => 'organization',
        ]);

        $this->post_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_author' => $this->user_id,
        ]);

        add_post_meta($this->post_id, '_ap_owner_user', $this->user_id);

        wp_set_current_user($this->user_id);

        do_action('rest_api_init');
    }

    public function test_update_portfolio_valid_payload_updates_meta(): void
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id);
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode([
            'tagline'    => 'Updated tagline',
            'website'    => 'https://example.com',
            'socials'    => ['https://example.org/profile'],
            'phone'      => '123-456-7890',
            'email'      => 'team@example.com',
            'address'    => '123 Main Street',
            'visibility' => 'private',
        ]));

        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('Updated tagline', get_post_meta($this->post_id, '_ap_tagline', true));
        $this->assertSame('https://example.com', get_post_meta($this->post_id, '_ap_website', true));
        $this->assertSame('https://example.org/profile', get_post_meta($this->post_id, '_ap_socials', true));
        $this->assertSame('123-456-7890', get_post_meta($this->post_id, '_ap_phone', true));
        $this->assertSame('team@example.com', get_post_meta($this->post_id, '_ap_email', true));
        $this->assertSame('123 Main Street', get_post_meta($this->post_id, '_ap_address', true));
        $this->assertSame('private', get_post_meta($this->post_id, 'portfolio_visibility', true));
    }

    public function test_update_portfolio_rejects_invalid_url(): void
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id);
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode([
            'website' => 'not-a-valid-url',
        ]));

        $response = rest_do_request($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('invalid_portfolio_payload', $data['code']);
        $this->assertArrayHasKey('errors', $data['data']);
        $this->assertArrayHasKey('website', $data['data']['errors']);
    }

    public function test_update_portfolio_respects_rate_limit(): void
    {
        set_transient('ap_rate_builder_write_' . $this->user_id, [
            'count' => 30,
            'reset' => time() + 30,
        ], 30);

        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id);
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode([]));

        $response = rest_do_request($request);

        $this->assertSame(429, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('rate_limited', $data['code']);
        $this->assertArrayHasKey('retry_after', $data['data']);
        $this->assertGreaterThan(0, $data['data']['retry_after']);

        delete_transient('ap_rate_builder_write_' . $this->user_id);
    }

    public function test_media_upload_validates_dimensions(): void
    {
        $tmp = wp_tempnam('small-image');
        $image = imagecreatetruecolor(100, 100);
        imagepng($image, $tmp);
        imagedestroy($image);

        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id . '/media');
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_file_params([
            'file' => [
                'name'     => 'small.png',
                'type'     => 'image/png',
                'tmp_name' => $tmp,
                'size'     => filesize($tmp),
                'error'    => UPLOAD_ERR_OK,
            ],
        ]);

        $response = rest_do_request($request);

        unlink($tmp);

        $this->assertSame(415, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('code', $data);
        $this->assertSame('image_too_small', $data['code']);
    }

    public function test_media_upload_sets_parent_on_success(): void
    {
        $tmp = wp_tempnam('valid-image');
        $image = imagecreatetruecolor(400, 400);
        $background = imagecolorallocate($image, 255, 0, 0);
        imagefilledrectangle($image, 0, 0, 399, 399, $background);
        imagejpeg($image, $tmp);
        imagedestroy($image);

        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id . '/media');
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_file_params([
            'file' => [
                'name'     => 'valid.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => $tmp,
                'size'     => filesize($tmp),
                'error'    => UPLOAD_ERR_OK,
            ],
        ]);

        $response = rest_do_request($request);

        unlink($tmp);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('attachment', $data);
        $attachment_id = (int) $data['attachment']['id'];
        $this->assertGreaterThan(0, $attachment_id);

        $attachment = get_post($attachment_id);
        $this->assertSame($this->post_id, (int) $attachment->post_parent);

        wp_delete_attachment($attachment_id, true);
    }

    public function test_non_owner_cannot_delete_media(): void
    {
        $attachment_id = $this->create_attachment('gallery-item.jpg');
        wp_update_post([
            'ID'          => $attachment_id,
            'post_parent' => $this->post_id,
        ]);
        update_post_meta($this->post_id, '_ap_gallery_ids', [$attachment_id]);

        $intruder_id = self::factory()->user->create([
            'role' => 'organization',
        ]);
        wp_set_current_user($intruder_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id . '/media');
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode([
            'gallery_ids' => [],
        ]));

        $response = rest_do_request($request);

        $this->assertSame(403, $response->get_status());

        $data = $response->get_data();
        $this->assertSame('forbidden', $data['code']);

        wp_set_current_user($this->user_id);
        wp_delete_attachment($attachment_id, true);
    }

    public function test_replace_logo_updates_parent_and_meta(): void
    {
        $old_logo = $this->create_attachment('old-logo.jpg');
        update_post_meta($this->post_id, '_ap_logo_id', $old_logo);
        wp_update_post([
            'ID'          => $old_logo,
            'post_parent' => 0,
        ]);

        $new_logo = $this->create_attachment('new-logo.jpg');

        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id);
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_body_params([
            'logo_id' => $new_logo,
        ]);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());

        $this->assertSame($new_logo, (int) get_post_meta($this->post_id, '_ap_logo_id', true));
        $this->assertSame([
            $new_logo,
        ], array_map('intval', get_post_meta($this->post_id, '_ap_logo_id')));

        $logo_post = get_post($new_logo);
        $this->assertSame($this->post_id, (int) $logo_post->post_parent);

        wp_delete_attachment($old_logo, true);
        wp_delete_attachment($new_logo, true);
    }

    public function test_replace_cover_updates_parent_and_meta(): void
    {
        $old_cover = $this->create_attachment('old-cover.jpg');
        update_post_meta($this->post_id, '_ap_cover_id', $old_cover);
        wp_update_post([
            'ID'          => $old_cover,
            'post_parent' => 0,
        ]);

        $new_cover = $this->create_attachment('new-cover.jpg');

        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id);
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_body_params([
            'cover_id' => $new_cover,
        ]);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());

        $this->assertSame($new_cover, (int) get_post_meta($this->post_id, '_ap_cover_id', true));
        $cover_post = get_post($new_cover);
        $this->assertSame($this->post_id, (int) $cover_post->post_parent);

        wp_delete_attachment($old_cover, true);
        wp_delete_attachment($new_cover, true);
    }

    private function create_attachment(string $filename): int
    {
        $upload_dir = wp_upload_dir();
        $path       = trailingslashit($upload_dir['path']) . $filename;

        $image   = imagecreatetruecolor(400, 400);
        $color   = imagecolorallocate($image, 0, 255, 0);
        imagefilledrectangle($image, 0, 0, 399, 399, $color);
        imagejpeg($image, $path);
        imagedestroy($image);

        $filetype = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name($filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $path);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $path);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return (int) $attachment_id;
    }
}
