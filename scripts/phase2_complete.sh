#!/usr/bin/env bash
set -e

BASE="$(pwd)"
SRC_CORE="$BASE/src/Core"
MAIN_FILE="$BASE/artpulse-management.php"
PTR_FILE="$SRC_CORE/PostTypeRegistrar.php"
MBR_FILE="$SRC_CORE/MetaBoxRegistrar.php"

echo "🚀 Completing Phase 2: meta‐boxes + capabilities…"

# 1. Overwrite PostTypeRegistrar.php with capability mappings
cat > "$PTR_FILE" << 'EOF'
<?php
namespace ArtPulse\Core;

class PostTypeRegistrar
{
    public static function register()
    {
        $common = [
            'public'       => true,
            'show_in_rest' => true,
            'has_archive'  => true,
        ];

        // 1) Events
        register_post_type('artpulse_event', array_merge($common, [
            'label'           => __('Events', 'artpulse'),
            'supports'        => ['title','editor','thumbnail','excerpt'],
            'rewrite'         => ['slug'=>'events'],
            'taxonomies'      => ['artpulse_event_type'],
            'capability_type' => 'artpulse_event',
            'capabilities'    => [
                'edit_post'           => 'edit_artpulse_event',
                'read_post'           => 'read_artpulse_event',
                'delete_post'         => 'delete_artpulse_event',
                'edit_posts'          => 'edit_artpulse_events',
                'edit_others_posts'   => 'edit_others_artpulse_events',
                'publish_posts'       => 'publish_artpulse_events',
                'read_private_posts'  => 'read_private_artpulse_events',
            ],
            'map_meta_cap'    => true,
        ]));

        // 2) Artists
        register_post_type('artpulse_artist', array_merge($common, [
            'label'           => __('Artists', 'artpulse'),
            'supports'        => ['title','editor','thumbnail','custom-fields'],
            'rewrite'         => ['slug'=>'artists'],
            'capability_type' => 'artpulse_artist',
            'capabilities'    => [
                'edit_post'           => 'edit_artpulse_artist',
                'read_post'           => 'read_artpulse_artist',
                'delete_post'         => 'delete_artpulse_artist',
                'edit_posts'          => 'edit_artpulse_artists',
                'edit_others_posts'   => 'edit_others_artpulse_artists',
                'publish_posts'       => 'publish_artpulse_artists',
                'read_private_posts'  => 'read_private_artpulse_artists',
            ],
            'map_meta_cap'    => true,
        ]));

        // 3) Artworks
        register_post_type('artpulse_artwork', array_merge($common, [
            'label'           => __('Artworks', 'artpulse'),
            'supports'        => ['title','editor','thumbnail','custom-fields'],
            'rewrite'         => ['slug'=>'artworks'],
            'capability_type' => 'artpulse_artwork',
            'capabilities'    => [
                'edit_post'           => 'edit_artpulse_artwork',
                'read_post'           => 'read_artpulse_artwork',
                'delete_post'         => 'delete_artpulse_artwork',
                'edit_posts'          => 'edit_artpulse_artworks',
                'edit_others_posts'   => 'edit_others_artpulse_artworks',
                'publish_posts'       => 'publish_artpulse_artworks',
                'read_private_posts'  => 'read_private_artpulse_artworks',
            ],
            'map_meta_cap'    => true,
        ]));

        // 4) Organizations
        register_post_type('artpulse_org', array_merge($common, [
            'label'           => __('Organizations', 'artpulse'),
            'supports'        => ['title','editor','thumbnail'],
            'rewrite'         => ['slug'=>'organizations'],
            'capability_type' => 'artpulse_org',
            'capabilities'    => [
                'edit_post'           => 'edit_artpulse_org',
                'read_post'           => 'read_artpulse_org',
                'delete_post'         => 'delete_artpulse_org',
                'edit_posts'          => 'edit_artpulse_orgs',
                'edit_others_posts'   => 'edit_others_artpulse_orgs',
                'publish_posts'       => 'publish_artpulse_orgs',
                'read_private_posts'  => 'read_private_artpulse_orgs',
            ],
            'map_meta_cap'    => true,
        ]));

        // Taxonomies
        register_taxonomy('artpulse_event_type','artpulse_event',[
            'label'=>__('Event Types','artpulse'),
            'public'=>true,'show_in_rest'=>true,'hierarchical'=>true,'rewrite'=>['slug'=>'event-types'],
        ]);
        register_taxonomy('artpulse_medium','artpulse_artwork',[
            'label'=>__('Medium','artpulse'),
            'public'=>true,'show_in_rest'=>true,'hierarchical'=>true,'rewrite'=>['slug'=>'medium'],
        ]);
    }
}
EOF
echo "✅ Updated PostTypeRegistrar.php"

# 2. Overwrite MetaBoxRegistrar.php with all four meta boxes
cat > "$MBR_FILE" << 'EOF'
<?php
namespace ArtPulse\Core;

class MetaBoxRegistrar
{
    public static function register()
    {
        add_action('add_meta_boxes', [self::class,'addMetaBoxes']);
        add_action('save_post',        [self::class,'saveMetaBoxes']);
    }

    public static function addMetaBoxes()
    {
        // Event
        add_meta_box('ap_event_details', __('Event Details','artpulse'),'renderEventBox','artpulse_event');
        // Artist
        add_meta_box('ap_artist_bio',     __('Artist Profile','artpulse'),'renderArtistBox','artpulse_artist');
        // Artwork
        add_meta_box('ap_artwork_info',   __('Artwork Info','artpulse'),'renderArtworkBox','artpulse_artwork');
        // Organization
        add_meta_box('ap_org_info',       __('Organization Info','artpulse'),'renderOrgBox','artpulse_org');
    }

    public static function renderEventBox(\WP_Post $post)
    {
        wp_nonce_field('ap_event_nonce','ap_event_nonce');
        $date     = get_post_meta($post->ID,'_ap_event_date',true);
        $location = get_post_meta($post->ID,'_ap_event_location',true);
        echo '<p><label>Date</label><input type="date" name="ap_event_date" value="'.esc_attr($date).'"/></p>';
        echo '<p><label>Location</label><input type="text" name="ap_event_location" style="width:100%" value="'.esc_attr($location).'"/></p>';
    }

    public static function renderArtistBox(\WP_Post $post)
    {
        wp_nonce_field('ap_artist_nonce','ap_artist_nonce');
        $bio   = get_post_meta($post->ID,'_ap_artist_bio',true);
        $org   = get_post_meta($post->ID,'_ap_artist_org',true);
        echo '<p><label>Biography</label><textarea name="ap_artist_bio" style="width:100%">'.esc_textarea($bio).'</textarea></p>';
        echo '<p><label>Organization ID</label><input type="number" name="ap_artist_org" value="'.esc_attr($org).'"/></p>';
    }

    public static function renderArtworkBox(\WP_Post $post)
    {
        wp_nonce_field('ap_artwork_nonce','ap_artwork_nonce');
        $medium     = get_post_meta($post->ID,'_ap_artwork_medium',true);
        $dimensions = get_post_meta($post->ID,'_ap_artwork_dimensions',true);
        $materials  = get_post_meta($post->ID,'_ap_artwork_materials',true);
        echo '<p><label>Medium</label><input type="text" name="ap_artwork_medium" value="'.esc_attr($medium).'"/></p>';
        echo '<p><label>Dimensions</label><input type="text" name="ap_artwork_dimensions" value="'.esc_attr($dimensions).'"/></p>';
        echo '<p><label>Materials</label><input type="text" name="ap_artwork_materials" value="'.esc_attr($materials).'"/></p>';
    }

    public static function renderOrgBox(\WP_Post $post)
    {
        wp_nonce_field('ap_org_nonce','ap_org_nonce');
        $address = get_post_meta($post->ID,'_ap_org_address',true);
        $website = get_post_meta($post->ID,'_ap_org_website',true);
        echo '<p><label>Address</label><input type="text" name="ap_org_address" style="width:100%" value="'.esc_attr($address).'"/></p>';
        echo '<p><label>Website</label><input type="url" name="ap_org_website" style="width:100%" value="'.esc_attr($website).'"/></p>';
    }

    public static function saveMetaBoxes($post_id)
    {
        // Bail on autosave or missing nonce
        foreach (['event','artist','artwork','org'] as $type) {
            if (
                !isset($_POST["ap_{$type}_nonce"]) ||
                !wp_verify_nonce($_POST["ap_{$type}_nonce"],"ap_{$type}_nonce") ||
                (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            ) continue;

            switch($type) {
                case 'event':
                    update_post_meta($post_id,'_ap_event_date', sanitize_text_field($_POST['ap_event_date'] ?? ''));
                    update_post_meta($post_id,'_ap_event_location', sanitize_text_field($_POST['ap_event_location'] ?? ''));
                    break;
                case 'artist':
                    update_post_meta($post_id,'_ap_artist_bio', sanitize_textarea_field($_POST['ap_artist_bio'] ?? ''));
                    update_post_meta($post_id,'_ap_artist_org', absint($_POST['ap_artist_org'] ?? 0));
                    break;
                case 'artwork':
                    update_post_meta($post_id,'_ap_artwork_medium', sanitize_text_field($_POST['ap_artwork_medium'] ?? ''));
                    update_post_meta($post_id,'_ap_artwork_dimensions', sanitize_text_field($_POST['ap_artwork_dimensions'] ?? ''));
                    update_post_meta($post_id,'_ap_artwork_materials', sanitize_text_field($_POST['ap_artwork_materials'] ?? ''));
                    break;
                case 'org':
                    update_post_meta($post_id,'_ap_org_address', sanitize_text_field($_POST['ap_org_address'] ?? ''));
                    update_post_meta($post_id,'_ap_org_website', esc_url_raw($_POST['ap_org_website'] ?? ''));
                    break;
            }
        }
    }
}
EOF
echo "✅ Updated MetaBoxRegistrar.php"

# 3. Hook MetaBoxRegistrar if missing
if ! grep -q "MetaBoxRegistrar::register" "$MAIN_FILE"; then
  sed -i "/PostTypeRegistrar::register();/a \\
    \\ArtPulse\\Core\\MetaBoxRegistrar::register();\\" "$MAIN_FILE"
  echo "✅ Hooked MetaBoxRegistrar into init"
fi

# 4. Inject capability assignment into activation hook
if ! grep -q "get_role('administrator')" "$MAIN_FILE"; then
  sed -i "/add_option( 'artpulse_settings'/a \\
    \n    // Assign custom capabilities to roles\n    \$roles = ['administrator','editor'];\n    \$caps = array_merge(\n        // events\n        ['edit_artpulse_event','read_artpulse_event','delete_artpulse_event','edit_artpulse_events','edit_others_artpulse_events','publish_artpulse_events','read_private_artpulse_ev]()_
