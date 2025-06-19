#!/usr/bin/env bash
set -e

BASE="$(pwd)"
SRC_CORE="$BASE/src/Core"
JS_DIR="$BASE/assets/js"
CSS_DIR="$BASE/assets/css"
MAIN_FILE="$BASE/artpulse-management.php"

echo "🚀 Scaffolding Phase 6: User/Profile Dashboard…"

# 1) Create UserDashboardManager.php
mkdir -p "$SRC_CORE"
cat > "$SRC_CORE/UserDashboardManager.php" << 'EOF'
<?php
namespace ArtPulse\Core;

class UserDashboardManager
{
    public static function register()
    {
        add_shortcode('ap_user_dashboard', [ self::class, 'renderDashboard' ]);
        add_action('wp_enqueue_scripts',   [ self::class, 'enqueueAssets' ]);
        add_action('rest_api_init',        [ self::class, 'registerRestRoutes' ]);
    }

    public static function enqueueAssets()
    {
        wp_enqueue_script(
            'ap-user-dashboard-js',
            plugins_url('assets/js/ap-user-dashboard.js', __FILE__),
            ['wp-api-fetch'],
            '1.0.0',
            true
        );
        wp_localize_script('ap-user-dashboard-js', 'ArtPulseDashboardApi', [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
        wp_enqueue_style(
            'ap-user-dashboard-css',
            plugins_url('assets/css/ap-user-dashboard.css', __FILE__),
            [],
            '1.0.0'
        );
    }

    public static function registerRestRoutes()
    {
        register_rest_route('artpulse/v1', '/user/dashboard', [
            'methods'  => 'GET',
            'callback' => [ self::class, 'getDashboardData' ],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);

        register_rest_route('artpulse/v1', '/user/profile', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'updateProfile' ],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);
    }

    public static function getDashboardData(\WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $data = [
            'membership_level'  => get_user_meta($user_id, 'ap_membership_level', true),
            'membership_expires'=> get_user_meta($user_id, 'ap_membership_expires', true),
            'events' => [],
            'artists'=> [],
            'artworks'=> [],
        ];

        // Fetch user’s CPT items
        foreach ( ['event','artist','artwork'] as $type ) {
            $posts = get_posts([
                'post_type'      => \"artpulse_\$type\",
                'author'         => $user_id,
                'posts_per_page' => -1,
            ]);
            foreach ( $posts as $p ) {
                $data[\$type . 's'][] = [
                    'id'    => $p->ID,
                    'title' => $p->post_title,
                    'link'  => get_permalink($p),
                ];
            }
        }

        return rest_ensure_response($data);
    }

    public static function updateProfile(\WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();
        if ( isset($params['display_name']) ) {
            wp_update_user([ 'ID' => $user_id, 'display_name' => sanitize_text_field($params['display_name']) ]);
        }
        // Add more profile fields here...
        return rest_ensure_response([ 'success' => true ]);
    }

    public static function renderDashboard($atts)
    {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __('Please log in to view your dashboard.', 'artpulse') . '</p>';
        }
        ob_start(); ?>
        <div class="ap-user-dashboard">
            <h2><?php _e('Your Membership','artpulse'); ?></h2>
            <div id="ap-membership-info"></div>
            <h2><?php _e('Your Content','artpulse'); ?></h2>
            <div id="ap-user-content"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
EOF
echo "✅ Created src/Core/UserDashboardManager.php"

# 2) Create the JavaScript
mkdir -p "$JS_DIR"
cat > "$JS_DIR/ap-user-dashboard.js" << 'EOF'
document.addEventListener('DOMContentLoaded', () => {
  const dash = document.querySelector('.ap-user-dashboard');
  if (!dash) return;

  fetch(`${ArtPulseDashboardApi.root}artpulse/v1/user/dashboard`, {
    headers: { 'X-WP-Nonce': ArtPulseDashboardApi.nonce }
  })
  .then(res => res.json())
  .then(data => {
    // Membership
    const info = document.getElementById('ap-membership-info');
    info.innerHTML = `<p>${apL10n.membership_level}: ${data.membership_level}</p>
                      <p>${apL10n.expires}: ${data.membership_expires ? new Date(data.membership_expires * 1000).toLocaleDateString() : apL10n.never}</p>`;

    // Content
    const content = document.getElementById('ap-user-content');
    ['events','artists','artworks'].forEach(type => {
      if (data[type].length) {
        const ul = document.createElement('ul');
        data[type].forEach(item => {
          const li = document.createElement('li');
          li.innerHTML = `<a href="${item.link}">${item.title}</a>`;
          ul.appendChild(li);
        });
        content.appendChild(document.createElement('h3')).textContent = apL10n[type];
        content.appendChild(ul);
      }
    });
  });
});
EOF
echo "✅ Created assets/js/ap-user-dashboard.js"

# 3) Create the CSS
mkdir -p "$CSS_DIR"
cat > "$CSS_DIR/ap-user-dashboard.css" << 'EOF'
.ap-user-dashboard { padding: 20px; background: #f9f9f9; border-radius: 6px; }
.ap-user-dashboard h2 { margin-top: 1.5em; font-size: 1.5rem; }
.ap-user-dashboard ul { list-style: disc; margin-left: 20px; }
EOF
echo "✅ Created assets/css/ap-user-dashboard.css"

# 4) Hook into plugin bootstrap
if ! grep -q "UserDashboardManager::register" "$MAIN_FILE"; then
  sed -i "/AccessControlManager::register()/a \\
    \ArtPulse\\Core\\UserDashboardManager::register();\\" "$MAIN_FILE"
  echo "✅ Hooked UserDashboardManager into init"
fi

echo "🎉 Phase 6 scaffolding complete!"
echo
echo "Next: commit & push:"
echo "  git add src/Core/UserDashboardManager.php assets/js/ap-user-dashboard.js assets/css/ap-user-dashboard.css artpulse-management.php"
echo "  git commit -m \"Phase 6: scaffold user/profile dashboard\""
echo "  ./push-with-pat.sh"
