#!/usr/bin/env bash
set -e

PLUGIN_DIR="$(pwd)"
SRC_DIR="${PLUGIN_DIR}/src/Core"

echo "🚀 Starting Phase 2 scaffolding…"

# 1. Create directories
mkdir -p "$SRC_DIR"

# 2. Generate PostTypeRegistrar.php
cat > "$SRC_DIR/PostTypeRegistrar.php" << 'EOF'
<?php
namespace ArtPulse\Core;

class PostTypeRegistrar
{
    public static function register()
    {
        // Events
        register_post_type('artpulse_event', [
            'label'        => __('Events', 'artpulse'),
            'public'       => true,
            'show_in_rest' => true,
            'supports'     => ['title','editor','thumbnail','excerpt'],
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'events'],
            'taxonomies'   => ['artpulse_event_type'],
        ]);

        // Artists
        register_post_type('artpulse_artist', [
            'label'        => __('Artists', 'artpulse'),
            'public'       => true,
            'show_in_rest' => true,
            'supports'     => ['title','editor','thumbnail','custom-fields'],
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'artists'],
        ]);

        // Artworks
        register_post_type('artpulse_artwork', [
            'label'        => __('Artworks', 'artpulse'),
            'public'       => true,
            'show_in_rest' => true,
            'supports'     => ['title','editor','thumbnail','custom-fields'],
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'artworks'],
        ]);

        // Organizations
        register_post_type('artpulse_org', [
            'label'        => __('Organizations', 'artpulse'),
            'public'       => true,
            'show_in_rest' => true,
            'supports'     => ['title','editor','thumbnail'],
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'organizations'],
        ]);

        // Event Types taxonomy
        register_taxonomy('artpulse_event_type', 'artpulse_event', [
            'label'        => __('Event Types', 'artpulse'),
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite'      => ['slug' => 'event-types'],
        ]);

        // Artwork Medium taxonomy
        register_taxonomy('artpulse_medium', 'artpulse_artwork', [
            'label'        => __('Medium', 'artpulse'),
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite'      => ['slug' => 'medium'],
        ]);
    }
}
EOF

echo "✅ Created src/Core/PostTypeRegistrar.php"

# 3. Inject the init hook into artpulse-management.php (if not already present)
MAIN_FILE="${PLUGIN_DIR}/artpulse-management.php"
HOOK_SNIPPET="add_action('init', function() {\
\n    \\ArtPulse\\Core\\PostTypeRegistrar::register();\
\n});"

if ! grep -q "PostTypeRegistrar::register" "$MAIN_FILE"; then
  # Insert right after the autoloader require_once
  sed -i "/require_once.*vendor\/autoload.php.*/a \\
$HOOK_SNIPPET
" "$MAIN_FILE"
  echo "✅ Injected init hook into artpulse-management.php"
else
  echo "ℹ️  init hook already present in artpulse-management.php"
fi

echo "🎉 Phase 2 scaffolding complete! Next: commit these changes and test your CPTs."
