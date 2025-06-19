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
