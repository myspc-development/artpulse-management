<?php
/*
 * Archive Template: Organizations Portfolio Grid (Salient Style)
 * Place in child theme as archive-ead_organization.php
 */

get_header();

$columns = 'cols-3'; // Can be cols-2, cols-3, cols-4, etc.
$span_num = 'span_4'; // Use span_4 for 3-column, span_3 for 4-column, etc.

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$orgs_query = new WP_Query(array(
    'post_type'      => 'ead_organization',
    'posts_per_page' => 12, // Adjust as needed
    'paged'          => $paged
));
?>

<div class="container-wrap">
  <div class="container main-content">
    <div class="nectar-portfolio-wrap">
      <div class="portfolio-items <?php echo esc_attr($columns); ?>">
        <?php if ($orgs_query->have_posts()) : while ($orgs_query->have_posts()) : $orgs_query->the_post(); ?>
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
                  // Display custom fields: organization description, website, logo (using ACF or native fields)
                  if(function_exists('get_field')) {
                    $org_description = get_field('organization_description');
                    $org_website = get_field('organization_website');
                    $org_logo = get_field('organization_logo');
                    if($org_logo) {
                      echo '<div class="organization-meta organization-logo"><img src="'. esc_url($org_logo) .'" alt="Logo"></div>';
                    }
                    if($org_description) {
                      echo '<div class="organization-meta"><strong>Description:</strong> '. esc_html($org_description) .'</div>';
                    }
                    if($org_website) {
                      echo '<div class="organization-meta"><a href="'. esc_url($org_website) .'" target="_blank" rel="noopener">Website</a></div>';
                    }
                  }
                ?>
              </div>
            </a>
          </div>
        <?php endwhile; else: ?>
          <p>No organizations found.</p>
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
