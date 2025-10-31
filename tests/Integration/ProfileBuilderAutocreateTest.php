<?php

namespace Tests\Integration;

use ArtPulse\Frontend\ArtistBuilderShortcode;
use ArtPulse\Frontend\OrgBuilderShortcode;
use ArtPulse\Core\ProfileState;
use ArtPulse\Core\RoleSetup;
use WP_UnitTestCase;
use function esc_url;
use function get_post_meta;
use function get_posts;
use function home_url;
use function substr_count;
use function update_post_meta;
use function wp_set_current_user;

class ProfileBuilderAutocreateTest extends WP_UnitTestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        RoleSetup::install();
    }

    protected function tear_down(): void
    {
        parent::tear_down();

        $_GET = [];
        wp_set_current_user(0);
    }

    public function test_artist_autocreate_creates_single_draft(): void
    {
        $user_id = self::factory()->user->create([
            'role' => 'artist',
        ]);

        wp_set_current_user($user_id);

        $_GET['autocreate'] = '1';

        $output = ArtistBuilderShortcode::render();

        $this->assertStringContainsString('data-ap-autosave-root', $output);

        $posts = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => 'any',
            'author'         => $user_id,
            'posts_per_page' => -1,
        ]);

        $this->assertCount(1, $posts);

        $post_id = (int) $posts[0];
        $this->assertSame($user_id, (int) get_post_meta($post_id, '_ap_owner_user', true));

        $render_again = ArtistBuilderShortcode::render();
        $this->assertStringContainsString('data-ap-autosave-root', $render_again);

        $posts_after = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => 'any',
            'author'         => $user_id,
            'posts_per_page' => -1,
        ]);

        $this->assertCount(1, $posts_after);
    }

    public function test_org_autocreate_creates_single_draft(): void
    {
        $user_id = self::factory()->user->create([
            'role' => 'organization',
        ]);

        wp_set_current_user($user_id);

        $_GET['autocreate'] = '1';

        $output = OrgBuilderShortcode::render();
        $this->assertStringContainsString('data-ap-autosave-root', $output);

        $posts = get_posts([
            'post_type'      => 'artpulse_org',
            'post_status'    => 'any',
            'author'         => $user_id,
            'posts_per_page' => -1,
        ]);

        $this->assertCount(1, $posts);

        $post_id = (int) $posts[0];
        $this->assertSame($user_id, (int) get_post_meta($post_id, '_ap_owner_user', true));

        $render_again = OrgBuilderShortcode::render();
        $this->assertStringContainsString('data-ap-autosave-root', $render_again);

        $posts_after = get_posts([
            'post_type'      => 'artpulse_org',
            'post_status'    => 'any',
            'author'         => $user_id,
            'posts_per_page' => -1,
        ]);

        $this->assertCount(1, $posts_after);
    }

    public function test_redirect_cta_present_when_redirect_param_given(): void
    {
        $user_id = self::factory()->user->create([
            'role' => 'artist',
        ]);

        wp_set_current_user($user_id);

        $post_id = self::factory()->post->create([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'draft',
            'post_author' => $user_id,
        ]);

        update_post_meta($post_id, '_ap_owner_user', $user_id);
        ProfileState::purge_by_post_id($post_id);

        $_GET['redirect']   = home_url('/dashboard');
        $_GET['autocreate'] = '0';

        $output = ArtistBuilderShortcode::render();

        $this->assertStringContainsString('ap-profile-builder__back', $output);
        $this->assertSame(1, substr_count($output, 'ap-profile-builder__back'));
        $this->assertStringContainsString(esc_url(home_url('/dashboard')), $output);

        $output_again = ArtistBuilderShortcode::render();
        $this->assertSame(1, substr_count($output_again, 'ap-profile-builder__back'));
    }
}
