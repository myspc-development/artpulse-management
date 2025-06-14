<?php
use PHPUnit\Framework\TestCase;
use EAD\Dashboard\OrganizationDashboard;
use Tests\Stubs;

class OrganizationDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$last_query_args = [];
        Stubs::$caps = [];
        $_GET = [];
        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
    }

    public function test_query_args_upcoming()
    {
        $_GET['event_filter'] = 'upcoming';
        Stubs::$posts = [(object)['ID'=>1,'post_title'=>'Event 1','post_status'=>'publish']];
        OrganizationDashboard::render_events_table();
        $args = Stubs::$last_query_args;
        $today = date('Y-m-d');
        $this->assertSame('ead_event', $args['post_type']);
        $this->assertSame(['publish','pending','draft'], $args['post_status']);
        $this->assertSame(-1, $args['posts_per_page']);
        $this->assertSame(Stubs::$current_user_id, $args['author']);
        $this->assertSame('date', $args['orderby']);
        $this->assertSame('DESC', $args['order']);
        $this->assertSame('event_end_date', $args['meta_key']);
        $this->assertSame($today, $args['meta_value']);
        $this->assertSame('>=', $args['meta_compare']);
    }

    public function test_query_args_expired()
    {
        $_GET['event_filter'] = 'expired';
        Stubs::$posts = [(object)['ID'=>2,'post_title'=>'Event 2','post_status'=>'draft']];
        OrganizationDashboard::render_events_table();
        $args = Stubs::$last_query_args;
        $today = date('Y-m-d');
        $this->assertSame('event_end_date', $args['meta_key']);
        $this->assertSame($today, $args['meta_value']);
        $this->assertSame('<', $args['meta_compare']);
    }

    public function test_output_markup_contains_event_rows()
    {
        $_GET['event_filter'] = 'all';
        Stubs::$posts = [
            (object)['ID'=>1,'post_title'=>'Alpha','post_status'=>'publish'],
            (object)['ID'=>2,'post_title'=>'Beta','post_status'=>'draft'],
        ];
        OrganizationDashboard::render_events_table();
        $output = ob_get_clean();
        $this->assertStringContainsString('<table', $output);
        $this->assertStringContainsString('value="1"', $output);
        $this->assertStringContainsString('Alpha', $output);
        $this->assertStringContainsString('value="2"', $output);
        $this->assertStringContainsString('Beta', $output);
        ob_start();
    }

    public function test_profile_form_displays_org_name()
    {
        Stubs::$user_meta = ['ead_organisation_id' => 10];
        Stubs::$meta = [10 => ['ead_org_name' => 'Example Org']];

        OrganizationDashboard::render_profile_form();
        $output = ob_get_clean();
        $this->assertStringContainsString('value="Example Org"', $output);
        ob_start();
    }

    /**
     * @runInSeparateProcess
     */
    public function test_handle_profile_submission_saves_org_name()
    {
        Stubs::$user_meta = ['ead_organisation_id' => 15];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'ead_organization_profile_nonce' => 'nonce',
            'ead_organization_profile_submit' => '1',
            'ead_org_name' => 'New Org'
        ];

        OrganizationDashboard::handle_profile_submission();

        $this->assertSame('New Org', Stubs::$meta[15]['ead_org_name']);
        $this->assertSame('New Org', Stubs::$updated_posts[15]['post_title']);
    }

    public function test_render_dashboard_requires_view_capability()
    {
        Stubs::$caps = [];
        $output = OrganizationDashboard::render_dashboard([]);
        $this->assertStringContainsString('You do not have permission', $output);
    }

    public function test_render_dashboard_displays_with_view_capability()
    {
        Stubs::$caps = ['view_dashboard'];
        $output = OrganizationDashboard::render_dashboard([]);
        $this->assertStringNotContainsString('You do not have permission', $output);
    }
}
