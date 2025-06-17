#!/usr/bin/env bash
set -e

BASE="$(pwd)"
SRC_CORE="$BASE/src/Core"
MAIN_FILE="$BASE/artpulse-management.php"

echo "🚀 Scaffolding Phase 4: Admin Dashboard & Shortcodes…"

# 1) Create AdminDashboard.php
cat > "$SRC_CORE/AdminDashboard.php" << 'EOF'
<?php
namespace ArtPulse\Core;

class AdminDashboard
{
    public static function register()
    {
        add_action('admin_menu', [ self::class, 'addMenus' ]);
    }

    public static function addMenus()
    {
        add_menu_page(
            __('ArtPulse', 'artpulse'),
            __('ArtPulse', 'artpulse'),
            'manage_options',
            'artpulse-dashboard',
            [ self::class, 'renderDashboard' ],
            'dashicons-art', // choose an appropriate dashicon
            60
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Events','artpulse'),
            __('Events','artpulse'),
            'edit_artpulse_events',
            'edit.php?post_type=artpulse_event'
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Artists','artpulse'),
            __('Artists','artpulse'),
            'edit_artpulse_artists',
            'edit.php?post_type=artpulse_artist'
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Artworks','artpulse'),
            __('Artworks','artpulse'),
            'edit_artpulse_artworks',
            'edit.php?post_type=artpulse_artwork'
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Organizations','artpulse'),
            __('Organizations','artpulse'),
            'edit_artpulse_orgs',
            'edit.php?post_type=artpulse_org'
        );
    }

    public static function renderDashboard()
    {
        echo '<div class="wrap"><h1>' . __('ArtPulse Dashboard','artpulse') . '</h1>';
        echo '<p>' . __('Quick links to manage Events, Artists, Artworks, and Organizations.','artpulse') . '</p>';
        echo '<ul>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_event') . '">' . __('Manage Events','artpulse') . '</a></li>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_artist') . '">' . __('Manage Artists','artpulse') . '</a></li>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_artwork') . '">' . __('Manage Artworks','artpulse') . '</a></li>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_org') . '">' . __('Manage Organizations','artpulse') . '</a></li>';
        echo '</ul></div>';
    }
}
EOF
echo "✅ Created src/Core/AdminDashboard.php"

# 2) Create ShortcodeManager.php
cat > "$SRC_CORE/ShortcodeManager.php" << 'EOF'
<?php
namespace ArtPulse\Core;

class ShortcodeManager
{
    public static function register()
    {
        add_shortcode('ap_events',       [ self::class, 'renderEvents' ]);
        add_shortcode('ap_artists',      [ self::class, 'renderArtists' ]);
        add_shortcode('ap_artworks',     [ self::class, 'renderArtworks' ]);
        add_shortcode('ap_organizations',[ self::class, 'renderOrganizations' ]);
    }

    public static function renderEvents($atts)
    {
        $atts = shortcode_atts(['limit'=>10], $atts, 'ap_events');
        $query = new \WP_Query([
            'post_type'      => 'artpulse_event',
            'posts_per_page' => intval($atts['limit']),
        ]);
        ob_start();
        echo '<div class="ap-portfolio-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<div class="portfolio-item">';
            the_post_thumbnail('medium');
            echo '<h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
            echo '</div>';
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function renderArtists($atts)
    {
        $atts = shortcode_atts(['limit'=>10], $atts, 'ap_artists');
        $query = new \WP_Query([
            'post_type'      => 'artpulse_artist',
            'posts_per_page' => intval($atts['limit']),
        ]);
        ob_start();
        echo '<div class="ap-portfolio-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<div class="portfolio-item">';
            the_post_thumbnail('medium');
            echo '<h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
            echo '</div>';
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function renderArtworks($atts)
    {
        $atts = shortcode_atts(['limit'=>10], $atts, 'ap_artworks');
        $query = new \WP_Query([
            'post_type'      => 'artpulse_artwork',
            'posts_per_page' => intval($atts['limit']),
        ]);
        ob_start();
        echo '<div class="ap-portfolio-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<div class="portfolio-item">';
            the_post_thumbnail('medium');
            echo '<h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
            echo '</div>';
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function renderOrganizations($atts)
    {
        $atts = shortcode_atts(['limit'=>10], $atts, 'ap_organizations');
        $query = new \WP_Query([
            'post_type'      => 'artpulse_org',
            'posts_per_page' => intval($atts['limit']),
        ]);
        ob_start();
        echo '<div class="ap-portfolio-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<div class="portfolio-item">';
            the_post_thumbnail('medium');
            echo '<h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
            echo '</div>';
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }
}
EOF
echo "✅ Created src/Core/ShortcodeManager.php"

# 3) Hook them into plugin bootstrap
if ! grep -q "AdminDashboard::register" "$MAIN_FILE"; then
  sed -i "/<\\?php/a \\
use ArtPulse\\Core\\AdminDashboard;\\
use ArtPulse\\Core\\ShortcodeManager;" "$MAIN_FILE"
  sed -i "/MetaBoxRegistrar::register();/a \\
    AdminDashboard::register();\\
    ShortcodeManager::register();" "$MAIN_FILE"
  echo "✅ Hooked AdminDashboard & ShortcodeManager into bootstrap"
fi

echo "🎉 Phase 4 scaffolding complete!"
echo
echo "Next steps:"
echo "  1) Create CSS rules (in assets/) for .ap-portfolio-grid & .portfolio-item to match Salient’s grid."
echo "  2) Optionally add template overrides under templates/salient/"
echo "  3) Commit & push:"
echo "     git add src/Core/{AdminDashboard.php,ShortcodeManager.php} artpulse-management.php"
echo "     git commit -m \"Phase 4: scaffold admin dashboard & shortcodes\""
echo "     ./push-with-pat.sh"
