<?php
/**
 * Single template for ArtPulse Events, using Salient portfolio wrappers.
 *
 * Place this file in:
 *   wp-content/plugins/artpulse-management-plugin/templates/salient/content-artpulse_event.php
 */

get_header(); ?>

<div id="nectar-outer">
  <div class="container-wrap">
    <div class="container">
      <div class="row">
        <div class="col-md-8 col-md-offset-2">
          <?php
          while ( have_posts() ) : the_post();

            // Featured image
            if ( has_post_thumbnail() ) {
              echo '<div class="nectar-portfolio-single-media">';
              the_post_thumbnail( 'full', [ 'class' => 'img-responsive' ] );
              echo '</div>';
            }

            // Title
            echo '<h1 class="entry-title">'. get_the_title() .'</h1>';

            // Content
            echo '<div class="entry-content">';
            the_content();
            echo '</div>';

            // Event meta
            $date     = get_post_meta( get_the_ID(), '_ap_event_date', true );
            $location = get_post_meta( get_the_ID(), '_ap_event_location', true );

            if ( $date || $location ) {
              echo '<ul class="portfolio-meta">';
              if ( $date ) {
                echo '<li><strong>'. esc_html__( 'Date:', 'artpulse' ) .'</strong> '. esc_html( $date ) .'</li>';
              }
              if ( $location ) {
                echo '<li><strong>'. esc_html__( 'Location:', 'artpulse' ) .'</strong> '. esc_html( $location ) .'</li>';
              }
              echo '</ul>';
            }

          endwhile;
          ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php get_footer(); ?>
