<?php
/*
 * Single Template: Event Post (Salient Style)
 * Place in child theme as single-ead_event.php
 */

get_header();

if (have_posts()) : while (have_posts()) : the_post();
    \EAD\Analytics\ListingAnalytics::track_view();
?>

<div class="container-wrap">
  <div class="container main-content">

    <article id="post-<?php the_ID(); ?>" <?php post_class('single-event'); ?>>

      <?php if (has_post_thumbnail()) : ?>
        <div class="single-event-thumb">
          <?php the_post_thumbnail('large', ['alt' => get_the_title()]); ?>
        </div>
      <?php endif; ?>

      <div class="single-event-content">
        <h1 class="event-title"><?php the_title(); ?></h1>
        
        <div class="event-meta-block">
          <?php
            $event_date = ead_get_meta( get_the_ID(), 'event_date');
            $event_time = ead_get_meta( get_the_ID(), 'event_time');
            $organizer  = ead_get_meta( get_the_ID(), 'event_organizer');

            $street   = ead_get_meta( get_the_ID(), 'event_street');
            $suburb   = ead_get_meta( get_the_ID(), 'event_suburb');
            $city     = ead_get_meta( get_the_ID(), 'event_city');
            $state    = ead_get_meta( get_the_ID(), 'event_state');
            $postcode = ead_get_meta( get_the_ID(), 'event_postcode');
            $country  = ead_get_meta( get_the_ID(), 'event_country');

            $location_parts = array_filter( [ $street, $suburb, $city, $state, $postcode, $country ] );
            $event_location = implode( ', ', $location_parts );

            if ( $event_date ) {
              echo '<div class="event-meta"><strong>Date:</strong> ' . esc_html( $event_date ) . '</div>';
            }
            if ( $event_time ) {
              echo '<div class="event-meta"><strong>Time:</strong> ' . esc_html( $event_time ) . '</div>';
            }
            if ( $event_location ) {
              echo '<div class="event-meta"><strong>Location:</strong> ' . esc_html( $event_location ) . '</div>';
            }
            if ( $organizer ) {
              echo '<div class="event-meta"><strong>Organizer:</strong> ' . esc_html( $organizer ) . '</div>';
            }
          ?>
        </div>
        <?php
          $ics_url = esc_url( rest_url( 'artpulse/v1/events/' . get_the_ID() . '/ics' ) );
        ?>
        <div class="event-add-calendar">
          <a href="<?php echo $ics_url; ?>" target="_blank" rel="noopener">
            <?php esc_html_e( 'Add to Calendar', 'artpulse-management' ); ?>
          </a>
        </div>

        <?php
          $map_template = trailingslashit( EAD_PLUGIN_DIR_PATH ) . 'templates/EadMap.php';
          if ( file_exists( $map_template ) ) {
            include $map_template;
          }
        ?>

        <?php
          $gallery_ids = ead_get_meta( get_the_ID(), 'event_gallery');
          if ( ! empty( $gallery_ids ) && is_array( $gallery_ids ) ) {
            echo '<div class="event-gallery">';
            foreach ( $gallery_ids as $img_id ) {
              $img = wp_get_attachment_image( ( $img_id ?: 0 ), 'medium_large' );
              if ( $img ) {
                echo '<div class="event-gallery-item">' . $img . '</div>';
              }
            }
            echo '</div>';
          }
        ?>

        <div class="event-description">
          <?php the_content(); ?>
        </div>

        <!-- RSVP FORM START (AJAX-READY) -->
        <div class="event-rsvp">
          <h3>RSVP for this Event</h3>
          <form id="ead-event-rsvp-form" method="post" autocomplete="off">
            <input type="email" name="email" id="ead-rsvp-email" placeholder="Your Email" required>
            <button type="submit">RSVP</button>
            <?php wp_nonce_field('ead_event_rsvp', 'ead_event_rsvp_nonce'); ?>
          </form>
          <div id="ead-rsvp-message" style="display:none;"></div>
        </div>
        <!-- RSVP FORM END -->

        <!-- Social sharing buttons -->
        <div class="event-sharing">
          <span>Share this event:</span>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?php the_permalink(); ?>" target="_blank" rel="noopener" class="share-btn fb">Facebook</a>
          <a href="https://twitter.com/intent/tweet?url=<?php the_permalink(); ?>&text=<?php echo urlencode(get_the_title()); ?>" target="_blank" rel="noopener" class="share-btn tw">Twitter</a>
          <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php the_permalink(); ?>&title=<?php echo urlencode(get_the_title()); ?>" target="_blank" rel="noopener" class="share-btn li">LinkedIn</a>
        </div>
        <!-- End social sharing buttons -->

      </div>
    </article>

  </div>
</div>

<?php endwhile; endif;
get_footer();
?>
