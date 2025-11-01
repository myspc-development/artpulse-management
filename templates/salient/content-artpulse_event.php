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

            $attachment_id = 0;
            $img_html      = '';

            if ( has_post_thumbnail() ) {
              $attachment_id = (int) get_post_thumbnail_id();
            } else {
              $ids = (array) get_post_meta( get_the_ID(), '_ap_submission_images', true );
              $attachment_id = ! empty( $ids[0] ) ? (int) $ids[0] : 0;
            }

            if ( $attachment_id > 0 ) {
              $best = \ArtPulse\Core\ImageTools::best_image_src( $attachment_id );
              $size = $best['size'] ?? 'ap-grid';
              $alt  = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
              if ( '' === trim( $alt ) ) {
                $alt = get_the_title();
              }
              $alt = sanitize_text_field( $alt );

              $img_html = wp_get_attachment_image(
                $attachment_id,
                $size,
                false,
                [
                  'alt'      => $alt,
                  'loading'  => 'lazy',
                  'decoding' => 'async',
                  'class'    => 'ap-event-img img-responsive',
                ]
              );
            }

            if ( $img_html ) {
              echo '<div class="nectar-portfolio-single-media">' . $img_html . '</div>';
            } else {
              echo '<div class="nectar-portfolio-single-media"><div class="ap-event-placeholder" aria-hidden="true"></div></div>';
            }

            ?>
            <h1 class="entry-title"><?php the_title(); ?></h1>
            <?php
            $object_id    = get_the_ID();
            $object_type  = get_post_type($object_id);
            $user_id      = get_current_user_id();
            $is_favorited = $user_id && class_exists('\\ArtPulse\\Community\\FavoritesManager')
              ? \ArtPulse\Community\FavoritesManager::is_favorited($user_id, $object_id, $object_type)
              : false;
            $is_following = $user_id && class_exists('\\ArtPulse\\Community\\FollowManager')
              ? \ArtPulse\Community\FollowManager::is_following($user_id, $object_id, $object_type)
              : false;
            $favorite_label_on  = esc_html__('Unfavorite', 'artpulse-management');
            $favorite_label_off = esc_html__('Favorite', 'artpulse-management');
            $follow_label_on    = esc_html__('Unfollow', 'artpulse-management');
            $follow_label_off   = esc_html__('Follow', 'artpulse-management');
            ?>
            <div class="ap-social-actions">
              <button
                type="button"
                class="ap-favorite-btn<?php echo $is_favorited ? ' is-active' : ''; ?>"
                data-ap-fav="1"
                data-ap-object-id="<?php echo esc_attr($object_id); ?>"
                data-ap-object-type="<?php echo esc_attr($object_type); ?>"
                data-ap-active="<?php echo $is_favorited ? '1' : '0'; ?>"
                data-label-on="<?php echo esc_attr($favorite_label_on); ?>"
                data-label-off="<?php echo esc_attr($favorite_label_off); ?>"
                aria-pressed="<?php echo $is_favorited ? 'true' : 'false'; ?>"
              >
                <?php echo $is_favorited ? $favorite_label_on : $favorite_label_off; ?>
              </button>
              <button
                type="button"
                class="ap-follow-btn<?php echo $is_following ? ' is-following' : ''; ?>"
                data-ap-follow="1"
                data-ap-object-id="<?php echo esc_attr($object_id); ?>"
                data-ap-object-type="<?php echo esc_attr($object_type); ?>"
                data-ap-active="<?php echo $is_following ? '1' : '0'; ?>"
                data-label-on="<?php echo esc_attr($follow_label_on); ?>"
                data-label-off="<?php echo esc_attr($follow_label_off); ?>"
                aria-pressed="<?php echo $is_following ? 'true' : 'false'; ?>"
              >
                <?php echo $is_following ? $follow_label_on : $follow_label_off; ?>
              </button>
            </div>
            <div class="entry-content">
              <?php the_content(); ?>
            </div>

            <?php
            // Event meta
            $date     = get_post_meta( get_the_ID(), '_ap_event_date', true );
            $location = get_post_meta( get_the_ID(), '_ap_event_location', true );

            if ( $date || $location ) {
              echo '<ul class="portfolio-meta">';
              if ( $date ) {
                echo '<li><strong>'. esc_html__( 'Date:', 'artpulse-management' ) .'</strong> '. esc_html( $date ) .'</li>';
              }
              if ( $location ) {
                echo '<li><strong>'. esc_html__( 'Location:', 'artpulse-management' ) .'</strong> '. esc_html( $location ) .'</li>';
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
