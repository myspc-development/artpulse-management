<?php
/*
 * Archive Template: Artworks Portfolio Grid (Salient Style)
 * Place in child theme as archive-ead_artwork.php
 */
get_header();

$columns = 'cols-3';
$span_num = 'span_4';
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$artworks_query = new WP_Query([
    'post_type'      => 'ead_artwork',
    'posts_per_page' => 12,
    'paged'          => $paged
]);
?>

<div class="container-wrap">
    <div class="container main-content">
        <div class="nectar-portfolio-wrap">
            <div class="portfolio-items <?php echo esc_attr($columns); ?>">
                <?php if ($artworks_query->have_posts()) :
                    while ($artworks_query->have_posts()) : $artworks_query->the_post(); ?>
                        <div <?php post_class('portfolio-item ' . $span_num); ?>>
                            <a href="<?php the_permalink(); ?>">
                                <div class="portfolio-thumb">
                                    <?php
                                    if (has_post_thumbnail()) {
                                        the_post_thumbnail('portfolio-thumb', [
                                            'alt' => get_the_title(),
                                            'loading' => 'lazy'
                                        ]);
                                    } else {
                                        echo '<img src="' . esc_url(get_template_directory_uri() . '/img/placeholder.png') .
                                             '" alt="' . esc_attr(get_the_title()) . ' - No image available" loading="lazy">';
                                    }
                                    ?>
                                </div>
                                <div class="portfolio-desc">
                                    <h2 class="portfolio-title"><?php the_title(); ?></h2>
                                    <div class="portfolio-excerpt"><?php the_excerpt(); ?></div>
                                    <?php
                                    // Show year and medium under title for richer cards
                                    $year = ead_get_meta(get_the_ID(), 'artwork_year');
                                    $medium = ead_get_meta(get_the_ID(), 'artwork_medium');
                                    if ($year || $medium) {
                                        echo '<div class="portfolio-meta">';
                                        if ($year) {
                                            echo '<span class="meta-year">' . esc_html($year) . '</span>';
                                        }
                                        if ($year && $medium) {
                                            echo ' &middot; ';
                                        }
                                        if ($medium) {
                                            echo '<span class="meta-medium">' . esc_html($medium) . '</span>';
                                        }
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                            </a>
                        </div>
                    <?php endwhile;
                else : ?>
                    <p><?php esc_html_e('No artworks found.', 'artpulse-management'); ?></p>
                <?php endif;
                wp_reset_postdata(); ?>
            </div>
            <?php
            if (function_exists('nectar_pagination')) {
                nectar_pagination();
            } else {
                the_posts_pagination();
            }
            ?>
        </div>
    </div>
</div>
<?php get_footer(); ?>
