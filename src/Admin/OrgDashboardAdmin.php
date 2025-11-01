<?php

namespace ArtPulse\Admin;

/**
 * Admin dashboard tailored for organization managers.
 */
class OrgDashboardAdmin
{
    public static function register(): void
    {
        add_menu_page(
            __('Organization Dashboard', 'artpulse-management'),
            __('Org Dashboard', 'artpulse-management'),
            'manage_options',
            'ap-org-dashboard',
            [self::class, 'render'],
            'dashicons-building'
        );
    }

    public static function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Organization Dashboard', 'artpulse-management') . '</h1>';

        if (current_user_can('manage_options')) {
            self::render_org_selector();
        }

        self::render_linked_artists();
        self::render_org_artworks();
        self::render_org_events();
        self::render_org_analytics();
        self::render_billing_history();
        echo '</div>';
    }

    private static function get_current_org_id(): int
    {
        if (current_user_can('manage_options')) {
            $selected = filter_input(INPUT_GET, 'org_id', FILTER_SANITIZE_NUMBER_INT);

            if (null === $selected) {
                $selected = $_GET['org_id'] ?? null;
            }

            if (null !== $selected && '' !== $selected) {
                return absint($selected);
            }

            $orgs = get_posts([
                'post_type'      => 'artpulse_org',
                'numberposts'    => 1,
                'post_status'    => 'publish',
                'suppress_filters' => false,
            ]);

            return $orgs ? absint($orgs[0]->ID) : 0;
        }

        $user_id = get_current_user_id();

        $org_id = get_user_meta($user_id, 'ap_organization_id', true);

        if ('' === $org_id || null === $org_id) {
            $org_id = get_user_meta($user_id, 'ap_org_id', true);
        }

        return (int) $org_id;
    }

    private static function render_org_selector(): void
    {
        $all_orgs = get_posts([
            'post_type'      => 'artpulse_org',
            'numberposts'    => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'suppress_filters' => false,
        ]);

        if (!$all_orgs) {
            echo '<p>' . esc_html__('No published organisations found.', 'artpulse-management') . '</p>';
            return;
        }

        $selected_org = self::get_current_org_id();
        echo '<form method="get" style="margin-bottom:1em;">';
        echo '<input type="hidden" name="page" value="' . esc_attr('ap-org-dashboard') . '" />';
        echo '<label for="ap-org-select"><strong>' . esc_html__('Select Organisation:', 'artpulse-management') . '</strong></label> ';
        echo '<select name="org_id" id="ap-org-select">';
        foreach ($all_orgs as $org) {
            $selected = selected($selected_org, $org->ID, false);
            echo '<option value="' . esc_attr((string) $org->ID) . '" ' . $selected . '>' . esc_html(get_the_title($org)) . '</option>';
        }
        echo '</select> ';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Load', 'artpulse-management') . '</button>';
        echo '</form>';
    }

    private static function render_linked_artists(): void
    {
        echo '<h2>' . esc_html__('Linked Artists', 'artpulse-management') . '</h2>';
        $org_id = self::get_current_org_id();
        if (!$org_id) {
            echo '<p>' . esc_html__('No organisation assigned to your user.', 'artpulse-management') . '</p>';
            return;
        }

        $requests = get_posts([
            'post_type'      => 'ap_profile_link',
            'meta_query'     => [
                [ 'key' => 'org_id', 'value' => $org_id ],
                [ 'key' => 'status', 'value' => 'approved' ],
            ],
            'post_status'    => 'publish',
            'numberposts'    => 50,
            'suppress_filters' => false,
        ]);

        if (!$requests) {
            echo '<p>' . esc_html__('No linked artists found.', 'artpulse-management') . '</p>';
            return;
        }

        echo '<table class="widefat"><thead><tr><th>' . esc_html__('Artist ID', 'artpulse-management') . '</th><th>' . esc_html__('Requested On', 'artpulse-management') . '</th></tr></thead><tbody>';
        foreach ($requests as $req) {
            $artist_user_id = get_post_meta($req->ID, 'artist_user_id', true);
            $requested_on   = get_post_meta($req->ID, 'requested_on', true);
            echo '<tr><td>' . esc_html((string) $artist_user_id) . '</td><td>' . esc_html((string) $requested_on) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_org_artworks(): void
    {
        echo '<h2>' . esc_html__('Artworks', 'artpulse-management') . '</h2>';
        $org_id = self::get_current_org_id();
        if (!$org_id) {
            echo '<p>' . esc_html__('No organisation assigned to your user.', 'artpulse-management') . '</p>';
            return;
        }

        $artworks = get_posts([
            'post_type'      => 'artpulse_artwork',
            'meta_query'     => [
                [ 'key' => 'org_id', 'value' => $org_id ],
            ],
            'post_status'    => 'publish',
            'numberposts'    => 50,
            'suppress_filters' => false,
        ]);

        if (!$artworks) {
            echo '<p>' . esc_html__('No artworks found for this organisation.', 'artpulse-management') . '</p>';
            return;
        }

        echo '<table class="widefat"><thead><tr><th>' . esc_html__('Artwork ID', 'artpulse-management') . '</th><th>' . esc_html__('Title', 'artpulse-management') . '</th></tr></thead><tbody>';
        foreach ($artworks as $artwork) {
            echo '<tr><td>' . esc_html((string) $artwork->ID) . '</td><td>' . esc_html(get_the_title($artwork)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_org_events(): void
    {
        echo '<h2>' . esc_html__('Events', 'artpulse-management') . '</h2>';
        $org_id = self::get_current_org_id();
        if (!$org_id) {
            echo '<p>' . esc_html__('No organisation assigned to your user.', 'artpulse-management') . '</p>';
            return;
        }

        $events = get_posts([
            'post_type'      => 'artpulse_event',
            'meta_query'     => [
                [ 'key' => 'org_id', 'value' => $org_id ],
            ],
            'post_status'    => 'publish',
            'numberposts'    => 50,
            'suppress_filters' => false,
        ]);

        if (!$events) {
            echo '<p>' . esc_html__('No events found for this organisation.', 'artpulse-management') . '</p>';
            return;
        }

        echo '<table class="widefat"><thead><tr><th>' . esc_html__('Event ID', 'artpulse-management') . '</th><th>' . esc_html__('Title', 'artpulse-management') . '</th></tr></thead><tbody>';
        foreach ($events as $event) {
            echo '<tr><td>' . esc_html((string) $event->ID) . '</td><td>' . esc_html(get_the_title($event)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_org_analytics(): void
    {
        echo '<h2>' . esc_html__('Analytics', 'artpulse-management') . '</h2>';
        $org_id = self::get_current_org_id();
        if (!$org_id) {
            echo '<p>' . esc_html__('No organisation assigned to your user.', 'artpulse-management') . '</p>';
            return;
        }

        $artworks = get_posts([
            'post_type'      => 'artpulse_artwork',
            'meta_query'     => [
                [ 'key' => 'org_id', 'value' => $org_id ],
            ],
            'post_status'    => 'publish',
            'numberposts'    => 50,
            'suppress_filters' => false,
        ]);

        if (!$artworks) {
            echo '<p>' . esc_html__('No analytics available.', 'artpulse-management') . '</p>';
            return;
        }

        $total_views     = 0;
        $total_favorites = 0;
        foreach ($artworks as $artwork) {
            $total_views     += (int) get_post_meta($artwork->ID, 'ap_views', true);
            $total_favorites += (int) get_post_meta($artwork->ID, 'ap_favorites', true);
        }

        printf('<p>%s <strong>%d</strong></p>', esc_html__('Total Artwork Views:', 'artpulse-management'), $total_views);
        printf('<p>%s <strong>%d</strong></p>', esc_html__('Total Artwork Favorites:', 'artpulse-management'), $total_favorites);
    }

    private static function render_billing_history(): void
    {
        echo '<h2>' . esc_html__('Billing History', 'artpulse-management') . '</h2>';
        $org_id = self::get_current_org_id();
        if (!$org_id) {
            echo '<p>' . esc_html__('No organisation assigned to your user.', 'artpulse-management') . '</p>';
            return;
        }

        $payments = get_post_meta($org_id, 'stripe_payment_ids', true);
        if (!is_array($payments) || !$payments) {
            echo '<p>' . esc_html__('No billing history found.', 'artpulse-management') . '</p>';
            return;
        }

        echo '<table class="widefat"><thead><tr><th>' . esc_html__('Charge ID', 'artpulse-management') . '</th><th>' . esc_html__('Date', 'artpulse-management') . '</th><th>' . esc_html__('Amount', 'artpulse-management') . '</th><th>' . esc_html__('Status', 'artpulse-management') . '</th></tr></thead><tbody>';
        foreach ($payments as $charge_id) {
            echo '<tr><td>' . esc_html((string) $charge_id) . '</td><td>-</td><td>-</td><td>-</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><em>' . esc_html__('Tip: Integrate the Stripe API to surface full charge information.', 'artpulse-management') . '</em></p>';
    }
}

add_action('admin_menu', [OrgDashboardAdmin::class, 'register']);
