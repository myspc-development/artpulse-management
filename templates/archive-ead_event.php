<?php
/*
 * Archive Template: Events Portfolio Grid (Salient Style)
 * Place in child theme as archive-ead_event.php
 */

get_header();

$columns = 'cols-3'; // Can be cols-2, cols-3, cols-4, etc.
$span_num = 'span_4'; // Use span_4 for 3-column, span_3 for 4-column, etc.

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$events_query = new WP_Query(array(
    'post_type'      => 'ead_event',
    'posts_per_page' => 12, // Adjust as needed
    'paged'          => $paged
));
?>

<div class="container-wrap">
  <div class="container main-content">
    <div class="nectar-portfolio-wrap">
      <div class="portfolio-items <?php echo esc_attr($columns); ?>">
        <?php if ($events_query->have_posts()) : while ($events_query->have_posts()) : $events_query->the_post(); ?>
          <div <?php post_class('portfolio-item '.$span_num); ?>>
            <a href="<?php the_permalink(); ?>" class="ead-track-click" data-post-id="<?php the_ID(); ?>">
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
                  // Display custom fields: event date and location (using ACF or native fields)
                  if(function_exists('get_field')) {
                    $event_date = get_field('event_date');
                    $event_location = get_field('event_location');
                    if($event_date) {
                      echo '<div class="event-meta"><strong>Date:</strong> '. esc_html($event_date) .'</div>';
                    }
                    if($event_location) {
                      echo '<div class="event-meta"><strong>Location:</strong> '. esc_html($event_location) .'</div>';
                    }
                  }
                ?>
              </div>
            </a>
          </div>
        <?php endwhile; else: ?>
          <p>No events found.</p>
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
