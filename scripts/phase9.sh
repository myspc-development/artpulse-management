#!/usr/bin/env bash
set -e

# Phase 9: Documentation & Help Integration
BASE="$(pwd)"
SRC_CORE="$BASE/src/Core"
DOCS_DIR="$BASE/assets/docs"

echo "🚀 Scaffolding Phase 9: Documentation Manager…"

# 1) Install Parsedown for Markdown parsing
composer require erusev/parsedown

echo "✅ Parsedown installed"

# 2) Create docs directory if not exists
mkdir -p "$DOCS_DIR"

echo "Place your Admin_Help.md, Member_Help.md, Onboarding_Guide.md files into $DOCS_DIR"

# 3) Create DocumentationManager.php
cat > "$SRC_CORE/DocumentationManager.php" << 'EOF'
<?php
namespace ArtPulse\Core;

use Parsedown;

class DocumentationManager
{
    public static function register()
    {
        add_action('admin_head', [ self::class, 'addHelpTabs' ]);
    }

    public static function addHelpTabs()
    {
        if ( ! function_exists('get_current_screen') ) {
            return;
        }
        $screen = get_current_screen();
        $docsPath = plugin_dir_path(__DIR__ . '/../../') . 'assets/docs/';
        $parsedown = new Parsedown();

        // Admin Help on Dashboard
        if ($screen->id === 'toplevel_page_artpulse-dashboard') {
            $md = file_get_contents($docsPath . 'Admin_Help.md');
            $content = '<div class="ap-doc">' . $parsedown->text($md) . '</div>';
            $screen->add_help_tab([
                'id'      => 'ap-admin-help',
                'title'   => __('Admin Help', 'artpulse'),
                'content' => $content,
            ]);
        }
        
        // Member Help on Profile and CPT screens
        $memberScreens = [
            'profile',
            'user-edit',
            'artpulse_event',
            'artpulse_artist',
            'artpulse_artwork',
            'artpulse_org',
        ];
        if ( in_array( $screen->id, $memberScreens, true ) ) {
            $md = file_get_contents($docsPath . 'Member_Help.md');
            $content = '<div class="ap-doc">' . $parsedown->text($md) . '</div>';
            $screen->add_help_tab([
                'id'      => 'ap-member-help',
                'title'   => __('Member Help', 'artpulse'),
                'content' => $content,
            ]);
        }
    }
}
EOF

echo "✅ Created src/Core/DocumentationManager.php"

# 4) Hook into plugin bootstrap
MAIN_FILE="$BASE/artpulse-management.php"
if ! grep -q "DocumentationManager::register" "$MAIN_FILE"; then
  sed -i "/AccessControlManager::register()/a \
    \ArtPulse\\Core\\DocumentationManager::register();\" "$MAIN_FILE"
  echo "✅ Hooked DocumentationManager into init"
fi

echo "🎉 Phase 9 scaffolding complete!"
echo "Next steps:"
echo "  1. Copy your markdown docs (Admin_Help.md, Member_Help.md) into assets/docs/" \
     "  2. Commit & push:"
echo "     git add src/Core/DocumentationManager.php assets/docs/" \
     "assets/js/ assets/css/ artpulse-management.php" \
     "git commit -m 'Phase 9: scaffold documentation manager'" \
     "./push-with-pat.sh"
