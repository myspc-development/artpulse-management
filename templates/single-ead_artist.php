<?php
/*
 * Archive Template: Artists Portfolio Grid (Salient Style)
 * Place in child theme as archive-ead_artist.php
 */

get_header();

$columns = 'cols-3'; // Can be cols-2, cols-3, cols-4, etc.
$span_num = 'span_4'; // Use span_4 for 3-column, span_3 for 4-column, etc.

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$artists_query = new WP_Query(array(
    'post_type'      => 'ead_artist',
    'posts_per_page' => 12, // Adjust as needed
    'paged'          => $paged
));
?>

<div class="container-wrap">
  <div class="container main-content">
    <div class="nectar-portfolio-wrap">
      <div class="portfolio-items <?php echo esc_attr($columns); ?>">
        <?php if ($artists_query->have_posts()) : while ($artists_query->have_posts()) : $artists_query->the_post(); ?>
          <div <?php post_class('portfolio-item '.$span_num); ?>>
            <a href="<?php the_permalink(); ?>">
              <div class="portfolio-thumb">
                <?php
                  if (has_post_thumbnail()) {
                    the_post_thumbnail('portfolio-thumb');
                  } else {
                    // Placeholder image (optional)
                    echo '<img src="' . get_template_directory_uri() . '/img/placeholder.png" alt="No image">';
                  }
                ?>
              </div>
              <div class="portfolio-desc">
                <h2 class="portfolio-title"><?php the_title(); ?></h2>
                <div class="portfolio-excerpt"><?php the_excerpt(); ?></div>
                <?php
                  // Display custom fields: artist bio, website, etc. (using post meta directly)
                  $artist_bio      = get_post_meta(get_the_ID(), 'artist_bio', true);
                  $artist_website  = get_post_meta(get_the_ID(), 'artist_website', true);
                  $artist_email    = get_post_meta(get_the_ID(), 'artist_email', true);
                  $artist_name     = get_post_meta(get_the_ID(), 'artist_name', true);
                  $artist_twitter  = get_post_meta(get_the_ID(), 'artist_twitter', true);
                  $artist_linkedin = get_post_meta(get_the_ID(), 'artist_linkedin', true);

                  if ($artist_bio) {
                    echo '<div class="artist-meta"><strong>Bio:</strong> ' . esc_html($artist_bio) . '</div>';
                  }
                  if ($artist_website) {
                    echo '<div class="artist-meta"><a href="' . esc_url($artist_website) . '" target="_blank" rel="noopener">Website</a></div>';
                  }
                  if ($artist_name) {
                    echo '<div class="artist-meta"><strong>Name:</strong> ' . esc_html($artist_name) . '</div>';
                  }
                  if ($artist_email) {
                    echo '<div class="artist-meta"><strong>Email:</strong> ' . esc_html($artist_email) . '</div>';
                  }
                  if ($artist_twitter) {
                    $twitter_url = preg_match('#^https?://#', $artist_twitter) ? $artist_twitter : 'https://twitter.com/' . ltrim($artist_twitter, '@');
                    echo '<div class="artist-meta"><a href="' . esc_url($twitter_url) . '" target="_blank" rel="noopener">Twitter</a></div>';
                  }
                  if ($artist_linkedin) {
                    echo '<div class="artist-meta"><a href="' . esc_url($artist_linkedin) . '" target="_blank" rel="noopener">LinkedIn</a></div>';
                  }
                ?>
              </div>
            </a>
          </div>
        <?php endwhile; else: ?>
          <p>No artists found.</p>
        <?php endif; wp_reset_postdata(); ?>
      </div>
      <?php
        // Salient's pagination function (make sure it's available in your theme)
        if (function_exists('nectar_pagination')) {
          nectar_pagination();
        } else {
          // Fallback pagination
          the_posts_pagination();
        }
      ?>
    </div>
  </div>
</div>

<?php get_footer(); ?>

