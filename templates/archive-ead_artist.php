<?php
/*
 * Archive Template: Artists Portfolio Grid (Salient Style)
 * Place in child theme as archive-ead_artist.php
 */
get_header();

$columns = 'cols-3';
$span_num = 'span_4';
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$artists_query = new WP_Query([
    'post_type'      => 'ead_artist',
    'posts_per_page' => 12,
    'paged'          => $paged
]);
?>

<div class="container-wrap">
  <div class="container main-content">
    <div class="nectar-portfolio-wrap">
      <div class="portfolio-items <?php echo esc_attr($columns); ?>">
        <?php if ($artists_query->have_posts()) :
          while ($artists_query->have_posts()) : $artists_query->the_post(); ?>
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
                  $artist_bio = get_post_meta(get_the_ID(), 'artist_bio', true);
                  $artist_name = get_post_meta(get_the_ID(), 'artist_name', true);
                  if ($artist_name) {
                      echo '<div class="portfolio-meta"><span class="meta-name">' . esc_html($artist_name) . '</span></div>';
                  }
                  if ($artist_bio) {
                      echo '<div class="portfolio-meta"><span class="meta-bio">' . esc_html(wp_trim_words($artist_bio, 20)) . '</span></div>';
                  }
                  ?>
                </div>
              </a>
            </div>
          <?php endwhile;
        else : ?>
          <p><?php esc_html_e('No artists found.', 'artpulse-management'); ?></p>
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
