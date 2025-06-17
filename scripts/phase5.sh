#!/usr/bin/env bash
set -e

BASE="$(pwd)"
SRC_CORE="$BASE/src/Core"
JS_DIR="$BASE/assets/js"
CSS_DIR="$BASE/assets/css"
MAIN_FILE="$BASE/artpulse-management.php"

echo "🚀 Scaffolding Phase 5: Directory & Filtering…"

# Create directories
mkdir -p "$SRC_CORE" "$JS_DIR" "$CSS_DIR"

# 1) DirectoryManager.php
cat > "$SRC_CORE/DirectoryManager.php" << 'EOF'
<?php
namespace ArtPulse\Core;

class DirectoryManager {
    public static function register() {
        add_shortcode('ap_directory', [self::class, 'renderDirectory']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('rest_api_init', [self::class, 'registerRestRoutes']);
    }

    public static function enqueueAssets() {
        wp_enqueue_script(
            'ap-directory-js',
            plugins_url('assets/js/ap-directory.js', __FILE__),
            ['wp-api-fetch'],
            '1.0.0',
            true
        );
        wp_localize_script('ap-directory-js', 'ArtPulseApi', [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
        wp_enqueue_style(
            'ap-directory-css',
            plugins_url('assets/css/ap-directory.css', __FILE__),
            [],
            '1.0.0'
        );
    }

    public static function registerRestRoutes() {
        register_rest_route('artpulse/v1', '/filter', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'handleFilter'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handleFilter(\WP_REST_Request $request) {
        $type  = sanitize_text_field($request->get_param('type'));
        $limit = intval($request->get_param('limit') ?? 10);

        $args = [
            'post_type'      => 'artpulse_' . $type,
            'posts_per_page' => $limit,
        ];
        $posts = get_posts($args);

        // Attach featured image URL
        $data = array_map(function($p){
            return [
                'id'      => $p->ID,
                'title'   => $p->post_title,
                'link'    => get_permalink($p),
                'featured_media_url' => get_the_post_thumbnail_url($p, 'medium'),
            ];
        }, $posts);

        return rest_ensure_response($data);
    }

    public static function renderDirectory($atts) {
        $atts = shortcode_atts([
            'type'  => 'event',
            'limit' => 10,
        ], $atts, 'ap_directory');
        ob_start(); ?>
        <div class="ap-directory" data-type="<?php echo esc_attr($atts['type']); ?>" data-limit="<?php echo esc_attr($atts['limit']); ?>">
            <div class="ap-directory-filters">
                <?php if ($atts['type'] === 'event'): ?>
                    <label><?php _e('Filter by Event Type','artpulse'); ?>:</label>
                    <select class="ap-filter-event-type"></select>
                <?php endif; ?>
                <label><?php _e('Limit','artpulse'); ?>:</label>
                <input type="number" class="ap-filter-limit" value="<?php echo esc_attr($atts['limit']); ?>" />
                <button class="ap-filter-apply"><?php _e('Apply','artpulse'); ?></button>
            </div>
            <div class="ap-directory-results"></div>
        </div>
        <?php return ob_get_clean();
    }
}
EOF
echo "✅ Created src/Core/DirectoryManager.php"

# 2) assets/js/ap-directory.js
cat > "$JS_DIR/ap-directory.js" << 'EOF'
(function(){
    document.querySelectorAll('.ap-directory').forEach(initDirectory);
    function initDirectory(container){
        const type        = container.dataset.type;
        const results     = container.querySelector('.ap-directory-results');
        const limitInput  = container.querySelector('.ap-filter-limit');
        const applyBtn    = container.querySelector('.ap-filter-apply');
        const selectEl    = container.querySelector('.ap-filter-event-type');

        // Load taxonomy terms for events
        if(selectEl){
            wp.apiFetch({ path: '/wp/v2/artpulse_event_type' }).then(terms=>{
                selectEl.innerHTML = '<option value=\"\">All</option>';
                terms.forEach(t=>{
                    const o = document.createElement('option');
                    o.value = t.id; o.textContent = t.name;
                    selectEl.appendChild(o);
                });
            });
        }

        applyBtn.addEventListener('click', ()=>{
            const params = new URLSearchParams();
            params.append('type', type);
            params.append('limit', limitInput.value);
            if(selectEl && selectEl.value) params.append('event_type', selectEl.value);

            wp.apiFetch({ path: '/artpulse/v1/filter?' + params.toString() })
            .then(posts=>{
                results.innerHTML = '';
                posts.forEach(post => {
                    const div = document.createElement('div');
                    div.className = 'portfolio-item';
                    div.innerHTML = `
                        <a href="${post.link}">
                          <img src="${post.featured_media_url}" alt="${post.title}" />
                          <h3>${post.title}</h3>
                        </a>`;
                    results.appendChild(div);
                });
            });
        });

        applyBtn.click(); // initial load
    }
})();
EOF
echo "✅ Created assets/js/ap-directory.js"

# 3) assets/css/ap-directory.css
cat > "$CSS_DIR/ap-directory.css" << 'EOF'
.ap-directory { margin-bottom: 2em; }
.ap-directory-filters { display:flex; gap:10px; margin-bottom:1em; align-items:center; }
.ap-directory-results { display:grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap:20px; }
.ap-directory-results .portfolio-item img { width:100%; border-radius:6px; }
.ap-directory-results .portfolio-item h3 { text-align:center; margin-top:0.5em; }
EOF
echo "✅ Created assets/css/ap-directory.css"

# 4) Hook into bootstrap
if ! grep -q "DirectoryManager::register" "$MAIN_FILE"; then
  sed -i "/AccessControlManager::register()/a \\
    \ArtPulse\\Core\\DirectoryManager::register();\\" "$MAIN_FILE"
  echo "✅ Hooked DirectoryManager into init"
fi

echo "🎉 Phase 5 scaffolding complete!"
echo
echo "Next: commit & push:"
echo "  git add src/Core/DirectoryManager.php assets/js/ap-directory.js assets/css/ap-directory.css $MAIN_FILE"
echo "  git commit -m \"Phase 5: scaffold directory & filtering UI\""
echo "  ./push-with-pat.sh"
