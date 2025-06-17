<?php
namespace ArtPulse\Admin;

class OrgDashboardAdmin {
    public static function register() {
        add_menu_page(
            'Organization Dashboard',
            'Org Dashboard',
            'manage_options', // You can use a custom org-admin cap if desired
            'ap-org-dashboard',
            [self::class, 'render'],
            'dashicons-building'
        );
    }

    public static function render() {
        echo '<div class="wrap"><h1>Organization Dashboard</h1>';
        // Optionally: Admin org dropdown could go here
        self::render_linked_artists();
        self::render_org_artworks();
        self::render_org_events();
        self::render_org_analytics();
        self::render_billing_history();
        echo '</div>';
    }

    // --- Helper: Get the current org id for this admin ---
    private static function get_current_org_id() {
        // Super admins can choose any org via dropdown (?org_id=)
        if (current_user_can('administrator')) {
            if (isset($_GET['org_id'])) {
                return intval($_GET['org_id']);
            }
            // Optionally default to first org
            $orgs = get_posts([
                'post_type' => 'artpulse_org',
                'numberposts' => 1,
                'post_status' => 'publish',
            ]);
            return $orgs ? $orgs[0]->ID : 0;
        }
        // For org admins, load from user meta (example key: ap_org_id)
        $org_id = intval(get_user_meta(get_current_user_id(), 'ap_org_id', true));
        return $org_id;
    }
    
    public static function render() {
    echo '<div class="wrap"><h1>Organization Dashboard</h1>';

    // Show org select dropdown for super admins only
    if (current_user_can('administrator')) {
        $all_orgs = get_posts([
            'post_type' => 'artpulse_org',
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);
        $selected_org = self::get_current_org_id();
        echo '<form method="get" style="margin-bottom:1em;"><input type="hidden" name="page" value="ap-org-dashboard" />';
        echo '<label for="ap-org-select"><strong>Select Organization: </strong></label>';
        echo '<select name="org_id" id="ap-org-select" onchange="this.form.submit()">';
        foreach ($all_orgs as $org) {
            $selected = ($org->ID == $selected_org) ? 'selected' : '';
            echo '<option value="' . esc_attr($org->ID) . '" ' . $selected . '>' . esc_html(get_the_title($org)) . '</option>';
        }
        echo '</select></form>';
    }

    self::render_linked_artists();
    self::render_org_artworks();
    self::render_org_events();
    self::render_org_analytics();
    self::render_billing_history();
    echo '</div>';
}


    // --- SECTION: Linked Artists ---
    private static function render_linked_artists() {
        echo '<h2>Linked Artists</h2>';
        $org_id = self::get_current_org_id();
        if (!$org_id) {
            echo '<p>No organization assigned to your user.</p>';
            return;
        }
        $args = [
            'post_type' => 'ap_profile_link',
            'meta_query' => [
                [ 'key' => 'org_id', 'value' => $org_id ],
                [ 'key' => 'status', 'value' => 'approved' ]
            ],
            'post_status' => 'publish',
            'numberposts' => 50
        ];
        $requests = get_posts($args);
        echo '<table class="widefat"><thead><tr><th>Artist ID</th><th>Requested On</th></tr></thead><tbody>';
        foreach ($requests as $req) {
            $artist_user_id = get_post_meta($req->ID, 'artist_user_id', true);
            $requested_on = get_post_meta($req->ID, 'requested_on', true);
            echo '<tr><td>' . esc_html($artist_user_id) . '</td><td>' . esc_html($requested_on) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    // --- SECTION: Org Artworks ---
    private static function render_org_artworks() {
        echo '<h2>Artworks</h2>';
        $org_id = self::get_current_org_id();
        if (!$org_id) {
            echo '<p>No organization assigned to your user.</p>';
            return;
        }
        $args = [
            'post_type' => 'artpulse_artwork',
            'meta_query' => [
                [ 'key' => 'org_id', 'value' => $org_id ]
            ],
            'post_status' => 'publish',
            'numberposts' => 50
        ];
        $artworks = get_posts($args);
        echo '<table class="widefat"><thead><tr><th>Artwork ID</th><th>Title</th></tr></thead><tbody>';
        foreach ($artworks as $artwork) {
            echo '<tr><td>' . $artwork->ID . '</td><td>' . esc_html(get_the_title($artwork)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    // --- SECTION: Org Events ---
    private static function render_org_events() {
        echo '<h2>Events</h2>';
        $org_id = self::get_current_org_id();
        if (!$org_id) {
            echo '<p>No organization assigned to your user.</p>';
            return;
        }
        $args = [
            'post_type' => 'artpulse_event',
            'meta_query' => [
                [ 'key' => 'org_id', 'value' => $org_id ]
            ],
            'post_status' => 'publish',
            'numberposts' => 50
        ];
        $events = get_posts($args);
        echo '<table class="widefat"><thead><tr><th>Event ID</th><th>Title</th></tr></thead><tbody>';
        foreach ($events as $event) {
            echo '<tr><td>' . $event->ID . '</td><td>' . esc_html(get_the_title($event)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    // --- SECTION: Org Analytics (stub) ---
    private static function render_org_analytics() {
        echo '<h2>Analytics</h2>';
        // Example analytics: total views and favorites from artworks
        $org_id = self::get_current_org_id();
        if (!$org_id) {
            echo '<p>No organization assigned to your user.</p>';
            return;
        }
        $args = [
            'post_type' => 'artpulse_artwork',
            'meta_query' => [
                [ 'key' => 'org_id', 'value' => $org_id ]
            ],
            'post_status' => 'publish',
            'numberposts' => 50
        ];
        $artworks = get_posts($args);
        $total_views = 0;
        $total_favorites = 0;
        foreach ($artworks as $artwork) {
            $views = intval(get_post_meta($artwork->ID, 'ap_views', true));
            $favs = intval(get_post_meta($artwork->ID, 'ap_favorites', true));
            $total_views += $views;
            $total_favorites += $favs;
        }
        echo '<p>Total Artwork Views: <strong>' . esc_html($total_views) . '</strong></p>';
        echo '<p>Total Artwork Favorites: <strong>' . esc_html($total_favorites) . '</strong></p>';
    }

    // --- SECTION: Billing History ---
    private static function render_billing_history() {
        echo '<h2>Billing History</h2>';
        $org_id = self::get_current_org_id();
        if (!$org_id) {
            echo '<p>No organization assigned to your user.</p>';
            return;
        }
        // Example: Payments stored in org meta as array of Stripe charge IDs
        $payments = get_post_meta($org_id, 'stripe_payment_ids', true);
        if (empty($payments) || !is_array($payments)) {
            echo '<p>No billing history found.</p>';
            return;
        }
        echo '<table class="widefat"><thead><tr><th>Charge ID</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
        foreach ($payments as $charge_id) {
            // You’d fetch more details from Stripe’s API here!
            // For demonstration, just show the ID:
            echo '<tr><td>' . esc_html($charge_id) . '</td><td>-</td><td>-</td><td>-</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><em>Tip: Integrate Stripe API to fetch full charge info live.</em></p>';
    }
}

add_action('admin_menu', ['\\ArtPulse\\Admin\\OrgDashboardAdmin', 'register']);
