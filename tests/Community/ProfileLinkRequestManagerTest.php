<?php

namespace Tests\Community;

use ArtPulse\Community\ProfileLinkRequestManager;
use WP_UnitTestCase;

class ProfileLinkRequestManagerTest extends WP_UnitTestCase
{
    private int $artist_id;
    private int $moderator_id;
    private int $org_id;

    public function set_up(): void
    {
        parent::set_up();

        $this->artist_id    = $this->factory->user->create(['role' => 'subscriber']);
        $this->moderator_id = $this->factory->user->create(['role' => 'administrator']);
        $this->org_id       = $this->factory->post->create([
            'post_type'  => 'artpulse_org',
            'post_title' => 'Test Org',
        ]);
    }

    public function test_create_persists_expected_metadata(): void
    {
        $request_id = ProfileLinkRequestManager::create($this->artist_id, $this->org_id, 'Please link me');

        $this->assertIsInt($request_id);
        $this->assertSame($this->artist_id, (int) get_post_meta($request_id, 'artist_user_id', true));
        $this->assertSame($this->org_id, (int) get_post_meta($request_id, 'org_id', true));
        $this->assertSame('Please link me', get_post_meta($request_id, 'message', true));
        $this->assertSame(ProfileLinkRequestManager::STATUS_PENDING, get_post_meta($request_id, 'status', true));
        $this->assertNotEmpty(get_post_meta($request_id, 'requested_on', true));
    }

    public function test_create_returns_error_for_invalid_org(): void
    {
        $result = ProfileLinkRequestManager::create($this->artist_id, 999999, 'Invalid org');

        $this->assertWPError($result);
        $this->assertSame('invalid_org', $result->get_error_code());
    }

    public function test_approve_updates_status_and_metadata(): void
    {
        $request_id = ProfileLinkRequestManager::create($this->artist_id, $this->org_id, 'Approve me');
        $this->assertIsInt($request_id);

        $result = ProfileLinkRequestManager::approve($request_id, $this->moderator_id);

        $this->assertSame($request_id, $result);
        $this->assertSame(ProfileLinkRequestManager::STATUS_APPROVED, get_post_meta($request_id, 'status', true));
        $this->assertSame($this->moderator_id, (int) get_post_meta($request_id, 'moderated_by', true));
        $this->assertNotEmpty(get_post_meta($request_id, 'moderated_on', true));
    }

    public function test_deny_returns_error_if_request_already_approved(): void
    {
        $request_id = ProfileLinkRequestManager::create($this->artist_id, $this->org_id, 'Approve me');
        $this->assertIsInt($request_id);

        ProfileLinkRequestManager::approve($request_id, $this->moderator_id);
        $result = ProfileLinkRequestManager::deny($request_id, $this->moderator_id);

        $this->assertWPError($result);
        $this->assertSame('invalid_status', $result->get_error_code());
    }
}
