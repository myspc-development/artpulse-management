#!/usr/bin/env bash
set -e

BASE="$(pwd)"
SRC_CORE="$BASE/src/Core"

echo "🚀 Scaffolding MetaBoxRegistrar…"

# 1. Create MetaBoxRegistrar.php
cat > "$SRC_CORE/MetaBoxRegistrar.php" << 'EOF'
<?php
namespace ArtPulse\Core;

class MetaBoxRegistrar
{
    public static function register()
    {
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post',        [self::class, 'saveMetaBoxes']);
    }

    public static function addMetaBoxes()
    {
        // Event Details
        add_meta_box(
            'artpulse_event_details',
            __('Event Details', 'artpulse'),
            [self::class, 'renderEventBox'],
            'artpulse_event',
            'normal',
            'high'
        );

        // TODO: Add boxes for Artists, Artworks, Organizations...
    }

    public static function renderEventBox(\WP_Post $post)
    {
        wp_nonce_field('artpulse_event_nonce', 'artpulse_event_nonce');

        $date     = get_post_meta($post->ID, '_artpulse_event_date', true);
        $location = get_post_meta($post->ID, '_artpulse_event_location', true);

        echo '<p><label for="artpulse_event_date">'.__('Date','artpulse').'</label><br />';
        echo '<input type="date" id="artpulse_event_date" name="artpulse_event_date" value="'.esc_attr($date).'" /></p>';

        echo '<p><label for="artpulse_event_location">'.__('Location','artpulse').'</label><br />';
        echo '<input type="text" id="artpulse_event_location" name="artpulse_event_location" value="'.esc_attr($location).'" style="width:100%;" /></p>';
    }

    public static function saveMetaBoxes($post_id)
    {
        // Verify nonce
        if ( ! ( isset($_POST['artpulse_event_nonce'])
              && wp_verify_nonce($_POST['artpulse_event_nonce'], 'artpulse_event_nonce') )
           ) {
            return;
        }

        // Bail on autosave
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return;
        }

        if ( isset($_POST['artpulse_event_date']) ) {
            update_post_meta($post_id, '_artpulse_event_date', sanitize_text_field($_POST['artpulse_event_date']));
        }
        if ( isset($_POST['artpulse_event_location']) ) {
            update_post_meta($post_id, '_artpulse_event_location', sanitize_text_field($_POST['artpulse_event_location']));
        }
    }
}
EOF

echo "✅ Created src/Core/MetaBoxRegistrar.php"

# 2. Hook it into your plugin bootstrap
MAIN_FILE="$BASE/artpulse-management.php"
if ! grep -q "MetaBoxRegistrar::register" "$MAIN_FILE"; then
  sed -i "/PostTypeRegistrar::register();/a \\
    \ArtPulse\\Core\\MetaBoxRegistrar::register();\\" "$MAIN_FILE"
  echo "✅ Injected MetaBoxRegistrar::register() into artpulse-management.php"
else
  echo "ℹ️  MetaBoxRegistrar already hooked"
fi

echo "🎉 Done! Now:"
echo "   1) git add src/Core/MetaBoxRegistrar.php artpulse-management.php"
echo "   2) git commit -m \"Phase 2.1: add MetaBoxRegistrar & hook it\""
echo "   3) Test by editing an Event—your Date & Location fields should appear."

echo
echo "🔜 Next Steps (manual):"
echo "   • Expand MetaBoxRegistrar with boxes for Artists, Artworks, Organizations"
echo "   • In PostTypeRegistrar, add 'capability_type' & 'capabilities' arrays to your CPT definitions"
echo "   • In your activation hook, assign those custom capabilities to appropriate roles"
echo "   • Commit, push, then we’ll move on to the management UI scaffolding!"
