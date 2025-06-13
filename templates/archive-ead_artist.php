<?php
/*
 * Single Template: Artist Post (Salient Style)
 * Place in child theme as single-ead_artist.php
 */

get_header();

if (have_posts()) : while (have_posts()) : the_post(); ?>

<div class="container-wrap">
  <div class="container main-content">

    <article id="post-<?php the_ID(); ?>" <?php post_class('single-artist'); ?>>

      <?php if (has_post_thumbnail()) : ?>
        <div class="single-artist-thumb">
          <?php the_post_thumbnail('large'); ?>
        </div>
      <?php endif; ?>

      <div class="single-artist-content">
        <h1 class="artist-title"><?php the_title(); ?></h1>
        <div class="artist-description">
          <?php the_content(); ?>
        </div>

        <?php
          // Display custom fields
          $artist_bio = get_post_meta( get_the_ID(), 'artist_bio', true );
          $artist_website = get_post_meta( get_the_ID(), 'artist_website', true );
          $artist_phone     = get_post_meta( get_the_ID(), 'artist_phone', true );
          $artist_instagram = get_post_meta( get_the_ID(), 'artist_instagram', true );
          $artist_facebook  = get_post_meta( get_the_ID(), 'artist_facebook', true );
          $artist_email     = get_post_meta( get_the_ID(), 'artist_email', true );
          $artist_name      = get_post_meta( get_the_ID(), 'artist_name', true );
          $artist_twitter   = get_post_meta( get_the_ID(), 'artist_twitter', true );
          $artist_linkedin  = get_post_meta( get_the_ID(), 'artist_linkedin', true );
          $artist_portrait_id = get_post_meta( get_the_ID(), 'artist_portrait', true );
          $artist_portrait_url = $artist_portrait_id ? wp_get_attachment_image_src( $artist_portrait_id, 'full' )[0] : '';

          if($artist_portrait_url){
            echo '<div class="artist-meta artist-portrait"><img src="'. esc_url($artist_portrait_url) .'" alt="Artist Portrait"></div>';
          }
          if ($artist_bio) {
            echo '<div class="artist-meta"><strong>Bio:</strong> ' . esc_html($artist_bio) . '</div>';
          }
          if ($artist_website) {
            echo '<div class="artist-meta"><a href="' . esc_url($artist_website) . '" target="_blank" rel="noopener">Website</a></div>';
          }
          if ($artist_phone) {
            echo '<div class="artist-meta"><strong>Phone:</strong> ' . esc_html($artist_phone) . '</div>';
          }
          if ($artist_instagram) {
            echo '<div class="artist-meta"><strong>Instagram:</strong> ' . esc_html($artist_instagram) . '</div>';
          }
          if ($artist_facebook) {
            echo '<div class="artist-meta"><strong>Facebook:</strong> ' . esc_html($artist_facebook) . '</div>';
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

          // Gallery images
          $gallery_ids = get_post_meta(get_the_ID(), 'artist_gallery_images', true);
          if (!empty($gallery_ids)) {
            if (is_string($gallery_ids)) {
              $gallery_ids = maybe_unserialize($gallery_ids);
              if (!is_array($gallery_ids)) {
                $gallery_ids = array_filter(array_map('trim', explode(',', $gallery_ids)));
              }
            }
            if (is_array($gallery_ids) && $gallery_ids) {
              echo '<div class="artist-gallery">';
              foreach ($gallery_ids as $gid) {
                $img = wp_get_attachment_image($gid, 'large');
                if ($img) {
                  echo '<div class="artist-gallery-item">' . $img . '</div>';
                }
              }
              echo '</div>';
            }
          }
        ?>

      </div>

    </article>

  </div>
</div>

<?php endwhile; endif;
get_footer();
