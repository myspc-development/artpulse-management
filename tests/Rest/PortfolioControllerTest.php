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
}
