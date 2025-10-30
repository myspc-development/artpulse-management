<?php

declare(strict_types=1);

class MemberOrgUpgradeTemplateTest extends WP_UnitTestCase
{
    public function test_reason_hidden_for_non_denied_status(): void
    {
        $org_upgrade = $this->getOrgUpgradeState();
        $org_upgrade['artist']['status'] = 'approved';
        $org_upgrade['artist']['reason'] = 'Add more portfolio pieces.';

        $output = $this->renderTemplate($org_upgrade);

        $this->assertStringNotContainsString('Add more portfolio pieces.', $output);
        $this->assertStringNotContainsString('Moderator feedback', $output);
    }

    public function test_reason_displayed_for_denied_status(): void
    {
        $org_upgrade = $this->getOrgUpgradeState();
        $org_upgrade['artist']['status'] = 'denied';
        $org_upgrade['artist']['reason'] = '<strong>Update your artist statement.</strong>';

        $output = $this->renderTemplate($org_upgrade);

        $this->assertStringContainsString('Moderator feedback', $output);
        $this->assertStringContainsString('<strong>Update your artist statement.</strong>', $output);
    }

    public function test_start_profile_cta_uses_builder_link(): void
    {
        $org_upgrade = $this->getOrgUpgradeState();
        $org_upgrade['artist'] = array_merge(
            $org_upgrade['artist'],
            [
                'exists'      => false,
                'status'      => 'none',
                'builder_url' => 'https://example.com/builder',
            ]
        );

        $output = $this->renderTemplate($org_upgrade);

        $this->assertStringContainsString('href="https://example.com/builder"', $output);
        $this->assertStringContainsString('Start your profile', $output);
    }

    public function test_pending_profile_shows_notice_and_edit_submission_cta(): void
    {
        $org_upgrade = $this->getOrgUpgradeState();
        $org_upgrade['artist'] = array_merge(
            $org_upgrade['artist'],
            [
                'exists'      => true,
                'status'      => 'pending',
                'builder_url' => 'https://example.com/edit',
            ]
        );

        $output = $this->renderTemplate($org_upgrade);

        $this->assertStringContainsString('Your profile submission is under review. We will email you when a moderator responds.', $output);
        $this->assertStringContainsString('href="https://example.com/edit"', $output);
        $this->assertStringContainsString('Edit submission', $output);
    }

    public function test_denied_profile_has_support_link_and_edit_cta(): void
    {
        $org_upgrade = $this->getOrgUpgradeState();
        $org_upgrade['artist'] = array_merge(
            $org_upgrade['artist'],
            [
                'exists'      => true,
                'status'      => 'denied',
                'builder_url' => 'https://example.com/edit',
                'public_url'  => 'https://example.com/profile',
                'support_url' => 'https://example.com/support',
            ]
        );

        $output = $this->renderTemplate($org_upgrade);

        $this->assertStringContainsString('href="https://example.com/edit"', $output);
        $this->assertStringContainsString('Edit profile', $output);
        $this->assertStringContainsString('href="https://example.com/support"', $output);
        $this->assertStringContainsString('What to fix', $output);
    }

    /**
     * @return array<string, mixed>
     */
    private function getOrgUpgradeState(): array
    {
        return [
            'artist' => [
                'status' => 'not_started',
                'reason' => '',
                'journey' => [
                    'anchor' => '#ap-journey-artist',
                    'status_label' => '',
                    'description' => '',
                    'progress_percent' => 0,
                    'portfolio' => [],
                ],
                'cta' => [],
            ],
            'organization' => [
                'status' => 'not_started',
                'reason' => '',
                'journey' => [
                    'anchor' => '#ap-journey-organization',
                    'status_label' => '',
                    'description' => '',
                    'progress_percent' => 0,
                    'portfolio' => [],
                ],
                'cta' => [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $org_upgrade
     */
    private function renderTemplate(array $org_upgrade): string
    {
        ob_start();
        $org_upgrade_local = $org_upgrade;
        $org_upgrade = $org_upgrade_local;
        require dirname(__DIR__, 2) . '/templates/dashboard/partials/member-org-upgrade.php';

        return (string) ob_get_clean();
    }
}
