<?php

use ArtPulse\Admin\AdminColumnsArtist;
use ArtPulse\Admin\AdminColumnsArtwork;
use ArtPulse\Admin\AdminColumnsEvent;
use ArtPulse\Admin\AdminColumnsOrganisation;
use ArtPulse\Core\Plugin;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class AdminColumnsTest extends TestCase
{
    private array $actions = [];
    private array $filters = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->actions = [];
        $this->filters = [];

        $actions = &$this->actions;
        Functions\when( 'add_action' )->alias( function ( $hook, $callback, $priority = 10, $accepted_args = 1 ) use ( &$actions ) {
            $actions[ $hook ][] = $callback;
            return true;
        } );

        $filters = &$this->filters;
        Functions\when( 'add_filter' )->alias( function ( $hook, $callback, $priority = 10, $accepted_args = 1 ) use ( &$filters ) {
            $filters[ $hook ][] = $callback;
            return true;
        } );

        Functions\when( 'get_option' )->alias( static fn( $option, $default = false ) => [] );
    }

    protected function tearDown(): void
    {
        \Patchwork\restoreAll();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testPluginRegistersAdminColumnsOnce(): void
    {
        $this->stubNonAdminColumnRegistrations();

        $calls = [
            AdminColumnsArtist::class        => 0,
            AdminColumnsArtwork::class       => 0,
            AdminColumnsEvent::class         => 0,
            AdminColumnsOrganisation::class  => 0,
        ];

        foreach ( $calls as $class => &$count ) {
            class_exists( $class );
            \Patchwork\replace( $class . '::register', function () use ( &$count ) {
                $count++;
            } );
        }

        $plugin = ( new \ReflectionClass( Plugin::class ) )->newInstanceWithoutConstructor();
        $plugin->register_core_modules();

        foreach ( $calls as $class => $count ) {
            $this->assertSame( 1, $count, $class . '::register should be called once.' );
        }
    }

    /**
     * @dataProvider adminColumnProvider
     */
    public function testRenderColumnsRunsOncePerColumn( string $class, string $hook, string $column, callable $prepareExpectations ): void
    {
        $this->actions = [];
        $actions = &$this->actions;
        Functions\when( 'add_action' )->alias( function ( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) use ( &$actions ) {
            $actions[ $hook_name ][] = $callback;
            return true;
        } );

        class_exists( $class );
        $this->assertArrayNotHasKey( $hook, $this->actions, 'Autoloading should not register hooks.' );

        $class::register();

        $prepareExpectations();

        $callbacks = $this->actions[ $hook ] ?? [];
        $this->assertCount( 1, $callbacks, 'Expected a single callback registered for the admin column.' );

        foreach ( $callbacks as $callback ) {
            $callback( $column, 123 );
        }
    }

    public function adminColumnProvider(): array
    {
        return [
            'artist portrait' => [
                AdminColumnsArtist::class,
                'manage_artpulse_artist_posts_custom_column',
                'portrait',
                function () {
                    Functions\when( 'get_post_meta' )->alias( static fn() => 55 );
                    Functions\expect( 'wp_get_attachment_image' )
                        ->once()
                        ->with( 55, [ 60, 60 ] );
                },
            ],
            'artwork image' => [
                AdminColumnsArtwork::class,
                'manage_artpulse_artwork_posts_custom_column',
                'image',
                function () {
                    Functions\when( 'get_post_meta' )->alias( static fn() => 77 );
                    Functions\expect( 'wp_get_attachment_image' )
                        ->once()
                        ->with( 77, [ 60, 60 ] );
                },
            ],
            'event banner' => [
                AdminColumnsEvent::class,
                'manage_artpulse_event_posts_custom_column',
                'event_banner',
                function () {
                    Functions\when( 'get_post_meta' )->alias( static function ( $post_id, $key ) {
                        return 'event_banner_id' === $key ? 33 : '';
                    } );
                    Functions\expect( 'wp_get_attachment_image' )
                        ->once()
                        ->with( 33, [ 60, 60 ] );
                },
            ],
            'organisation logo' => [
                AdminColumnsOrganisation::class,
                'manage_artpulse_org_posts_custom_column',
                'logo',
                function () {
                    Functions\when( 'get_post_meta' )->alias( static fn() => 'https://example.test/logo.png' );
                    Functions\when( 'esc_url' )->alias( static fn( $url ) => $url );
                    Functions\expect( 'printf' )->once();
                },
            ],
        ];
    }

    private function stubNonAdminColumnRegistrations(): void
    {
        $classes = [
            '\\ArtPulse\\Core\\PostTypeRegistrar',
            '\\ArtPulse\\Core\\MetaBoxRegistrar',
            '\\ArtPulse\\Core\\AdminDashboard',
            '\\ArtPulse\\Core\\ShortcodeManager',
            '\\ArtPulse\\Admin\\SettingsPage',
            '\\ArtPulse\\Core\\MembershipManager',
            '\\ArtPulse\\Core\\AccessControlManager',
            '\\ArtPulse\\Core\\DirectoryManager',
            '\\ArtPulse\\Core\\UserDashboardManager',
            '\\ArtPulse\\Core\\AnalyticsManager',
            '\\ArtPulse\\Core\\AnalyticsDashboard',
            '\\ArtPulse\\Core\\FrontendMembershipPage',
            '\\ArtPulse\\Community\\ProfileLinkRequestManager',
            '\\ArtPulse\\Core\\MyFollowsShortcode',
            '\\ArtPulse\\Core\\NotificationShortcode',
            '\\ArtPulse\\Admin\\AdminListSorting',
            '\\ArtPulse\\Rest\\RestSortingSupport',
            '\\ArtPulse\\Admin\\AdminListColumns',
            '\\ArtPulse\\Admin\\EnqueueAssets',
            '\\ArtPulse\\Frontend\\Shortcodes',
            '\\ArtPulse\\Frontend\\MyEventsShortcode',
            '\\ArtPulse\\Frontend\\EventSubmissionShortcode',
            '\\ArtPulse\\Frontend\\EditEventShortcode',
            '\\ArtPulse\\Frontend\\OrganizationDashboardShortcode',
            '\\ArtPulse\\Frontend\\OrganizationEventForm',
            '\\ArtPulse\\Frontend\\UserProfileShortcode',
            '\\ArtPulse\\Frontend\\ProfileEditShortcode',
            '\\ArtPulse\\Admin\\MetaBoxesRelationship',
            '\\ArtPulse\\Blocks\\RelatedItemsSelectorBlock',
            '\\ArtPulse\\Admin\\ApprovalManager',
            '\\ArtPulse\\Rest\\RestRoutes',
            '\\ArtPulse\\Admin\\MetaBoxesArtist',
            '\\ArtPulse\\Admin\\MetaBoxesArtwork',
            '\\ArtPulse\\Admin\\MetaBoxesEvent',
            '\\ArtPulse\\Admin\\MetaBoxesOrganisation',
            '\\ArtPulse\\Admin\\QuickStartGuide',
            '\\ArtPulse\\Taxonomies\\TaxonomiesRegistrar',
            '\\ArtPulse\\Core\\WooCommerceIntegration',
            '\\ArtPulse\\Core\\PurchaseShortcode',
        ];

        foreach ( $classes as $class ) {
            if ( ! class_exists( $class ) ) {
                continue;
            }

            \Patchwork\replace( $class . '::register', static function () {} );
        }
    }
}
