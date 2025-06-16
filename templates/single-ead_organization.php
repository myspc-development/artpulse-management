<?php
/*
 * Single Template: Organization Post (Salient Style)
 * Place in child theme as single-ead_organization.php
 */

get_header();

if (have_posts()) : while (have_posts()) : the_post();
    \EAD\Analytics\ListingAnalytics::track_view();
?>

<div class="container-wrap">
  <div class="container main-content">

    <article id="post-<?php the_ID(); ?>" <?php post_class('single-organization'); ?>>

      <?php if (has_post_thumbnail()) : ?>
        <div class="single-organization-thumb">
          <?php the_post_thumbnail('large'); ?>
        </div>
      <?php endif; ?>

      <div class="single-organization-content">
        <h1 class="organization-title">
            <?php
            $name = ead_get_meta( get_the_ID(), 'ead_org_name');
            if ( $name ) {
                echo esc_html( $name );
            } else {
                the_title();
            }
            ?>
        </h1>

        <?php
          $map_template = trailingslashit( EAD_PLUGIN_DIR_PATH ) . 'templates/EadMap.php';
          if ( file_exists( $map_template ) ) {
            include $map_template;
          }
        ?>

        <div class="organization-description">
          <?php the_content(); ?>
        </div>

        <?php
          // Display custom fields
            $org_description = ead_get_meta(get_the_ID(), 'ead_org_description');
            $org_website = ead_get_meta(get_the_ID(), 'ead_org_website_url');
            $org_logo_id = ead_get_meta(get_the_ID(), 'ead_org_logo_id');
            $org_type = ead_get_meta(get_the_ID(), 'ead_org_type');
            $org_size = ead_get_meta(get_the_ID(), 'ead_org_size');

            $type_labels = [
              'gallery'             => __( 'Art Gallery', 'artpulse-management' ),
              'museum'              => __( 'Museum', 'artpulse-management' ),
              'studio'              => __( 'Artist Studio', 'artpulse-management' ),
              'collective'          => __( 'Artist Collective', 'artpulse-management' ),
              'non-profit'          => __( 'Non-Profit Arts Organization', 'artpulse-management' ),
              'commercial-gallery'  => __( 'Commercial Gallery', 'artpulse-management' ),
              'public-art-space'    => __( 'Public Art Space', 'artpulse-management' ),
              'educational-institution' => __( 'Educational Institution (Arts Dept.)', 'artpulse-management' ),
              'other'               => __( 'Other', 'artpulse-management' ),
            ];

            $size_labels = [
              'small'  => __( 'Small', 'artpulse-management' ),
              'medium' => __( 'Medium', 'artpulse-management' ),
              'large'  => __( 'Large', 'artpulse-management' ),
              'other'  => __( 'Other', 'artpulse-management' ),
            ];
            $org_facebook = ead_get_meta(get_the_ID(), 'ead_org_facebook_url');
            $org_twitter = ead_get_meta(get_the_ID(), 'ead_org_twitter_url');
            $org_instagram = ead_get_meta(get_the_ID(), 'ead_org_instagram_url');
            $org_linkedin = ead_get_meta(get_the_ID(), 'ead_org_linkedin_url');
            $org_artsy = ead_get_meta(get_the_ID(), 'ead_org_artsy_url');
            $org_pinterest = ead_get_meta(get_the_ID(), 'ead_org_pinterest_url');
            $org_youtube = ead_get_meta(get_the_ID(), 'ead_org_youtube_url');
            $primary_contact_name = ead_get_meta(get_the_ID(), 'ead_org_primary_contact_name');
            $primary_contact_email = ead_get_meta(get_the_ID(), 'ead_org_primary_contact_email');
            $primary_contact_phone = ead_get_meta(get_the_ID(), 'ead_org_primary_contact_phone');
            $primary_contact_role = ead_get_meta(get_the_ID(), 'ead_org_primary_contact_role');
            $org_street_address = ead_get_meta(get_the_ID(), 'ead_org_street_address');
            $org_postal_address = ead_get_meta(get_the_ID(), 'ead_org_postal_address');
            $org_venue_address = ead_get_meta(get_the_ID(), 'ead_org_venue_address');
            $org_venue_email = ead_get_meta(get_the_ID(), 'ead_org_venue_email');
            $org_venue_phone = ead_get_meta(get_the_ID(), 'ead_org_venue_phone');
            $org_country = ead_get_meta(get_the_ID(), 'ead_country');
            $org_state   = ead_get_meta(get_the_ID(), 'ead_state');
            $org_city    = ead_get_meta(get_the_ID(), 'ead_city');
            $org_venue_monday_start_time = ead_get_meta(get_the_ID(), 'ead_org_venue_monday_start_time');
            $org_venue_monday_end_time = ead_get_meta(get_the_ID(), 'ead_org_venue_monday_end_time');
            $org_venue_tuesday_start_time = ead_get_meta(get_the_ID(), 'ead_org_venue_tuesday_start_time');
            $org_venue_tuesday_end_time = ead_get_meta(get_the_ID(), 'ead_org_venue_tuesday_end_time');
            $org_venue_wednesday_start_time = ead_get_meta(get_the_ID(), 'ead_org_venue_wednesday_start_time');
            $org_venue_wednesday_end_time = ead_get_meta(get_the_ID(), 'ead_org_venue_wednesday_end_time');
            $org_venue_thursday_start_time = ead_get_meta(get_the_ID(), 'ead_org_venue_thursday_start_time');
            $org_venue_thursday_end_time = ead_get_meta(get_the_ID(), 'ead_org_venue_thursday_end_time');
            $org_venue_friday_start_time = ead_get_meta(get_the_ID(), 'ead_org_venue_friday_start_time');
            $org_venue_friday_end_time = ead_get_meta(get_the_ID(), 'ead_org_venue_friday_end_time');
            $org_venue_saturday_start_time = ead_get_meta(get_the_ID(), 'ead_org_venue_saturday_start_time');
            $org_venue_saturday_end_time = ead_get_meta(get_the_ID(), 'ead_org_venue_saturday_end_time');
            $org_venue_sunday_start_time = ead_get_meta(get_the_ID(), 'ead_org_venue_sunday_start_time');
            $org_venue_sunday_end_time = ead_get_meta(get_the_ID(), 'ead_org_venue_sunday_end_time');

            if ($org_logo_id) {
              $info = wp_get_attachment_image_src($org_logo_id, 'full');
              if ($info) {
                $org_logo = $info[0];
                echo '<div class="organization-meta organization-logo"><img src="' . esc_url($org_logo) . '" alt="Logo"></div>';
              }
            }
            if ($org_description) {
              echo '<div class="organization-meta"><strong>Description:</strong> ' . esc_html($org_description) . '</div>';
            }
            if ($org_website) {
              echo '<div class="organization-meta"><a href="' . esc_url($org_website) . '" target="_blank" rel="noopener">Website</a></div>';
            }
            if ($org_type) {
              $label = isset($type_labels[$org_type]) ? $type_labels[$org_type] : $org_type;
              echo '<div class="organization-meta"><strong>Type:</strong> ' . esc_html($label) . '</div>';
            }
            if ($org_size) {
              $label = isset($size_labels[$org_size]) ? $size_labels[$org_size] : $org_size;
              echo '<div class="organization-meta"><strong>Size:</strong> ' . esc_html($label) . '</div>';
            }
            if ($org_facebook) {
              echo '<div class="organization-meta"><a href="' . esc_url($org_facebook) . '" target="_blank" rel="noopener">Facebook</a></div>';
            }
            if ($org_twitter) {
              echo '<div class="organization-meta"><a href="' . esc_url($org_twitter) . '" target="_blank" rel="noopener">Twitter</a></div>';
            }
            if ($org_instagram) {
              echo '<div class="organization-meta"><a href="' . esc_url($org_instagram) . '" target="_blank" rel="noopener">Instagram</a></div>';
            }
            if ($org_linkedin) {
              echo '<div class="organization-meta"><a href="' . esc_url($org_linkedin) . '" target="_blank" rel="noopener">LinkedIn</a></div>';
            }
            if ($primary_contact_name) {
              echo '<div class="organization-meta"><strong>Primary Contact Name:</strong> ' . esc_html($primary_contact_name) . '</div>';
            }
            if ($primary_contact_email) {
              echo '<div class="organization-meta"><strong>Primary Contact Email:</strong> ' . esc_html($primary_contact_email) . '</div>';
            }
            if ($primary_contact_phone) {
              echo '<div class="organization-meta"><strong>Primary Contact Phone:</strong> ' . esc_html($primary_contact_phone) . '</div>';
            }
            if ($primary_contact_role) {
              echo '<div class="organization-meta"><strong>Primary Contact Role:</strong> ' . esc_html($primary_contact_role) . '</div>';
            }
            $location_parts = array_filter([$org_city, $org_state, $org_country]);
            if ($location_parts) {
              echo '<p><strong>Location:</strong> ' . esc_html(implode(', ', $location_parts)) . '</p>';
            }
            if ($org_street_address) {
              echo '<div class="organization-meta"><strong>Street Address:</strong> ' . esc_html($org_street_address) . '</div>';
            }
            if ($org_postal_address) {
              echo '<div class="organization-meta"><strong>Postal Address:</strong> ' . esc_html($org_postal_address) . '</div>';
            }
            if ($org_venue_address) {
              echo '<div class="organization-meta"><strong>Venue Address:</strong> ' . esc_html($org_venue_address) . '</div>';
            }
            if ($org_venue_email) {
              echo '<div class="organization-meta"><strong>Venue Email:</strong> ' . esc_html($org_venue_email) . '</div>';
            }
            if ($org_venue_phone) {
              echo '<div class="organization-meta"><strong>Venue Phone:</strong> ' . esc_html($org_venue_phone) . '</div>';
            }
            if ($org_venue_monday_start_time) {
              echo '<div class="organization-meta"><strong>Venue Monday Start Time:</strong> ' . esc_html($org_venue_monday_start_time) . '</div>';
            }
            if ($org_venue_monday_end_time) {
              echo '<div class="organization-meta"><strong>Venue Monday End Time:</strong> ' . esc_html($org_venue_monday_end_time) . '</div>';
            }
            if ($org_venue_tuesday_start_time) {
              echo '<div class="organization-meta"><strong>Venue Tuesday Start Time:</strong> ' . esc_html($org_venue_tuesday_start_time) . '</div>';
            }
            if ($org_venue_tuesday_end_time) {
              echo '<div class="organization-meta"><strong>Venue Tuesday End Time:</strong> ' . esc_html($org_venue_tuesday_end_time) . '</div>';
            }
            if ($org_venue_wednesday_start_time) {
              echo '<div class="organization-meta"><strong>Venue Wednesday Start Time:</strong> ' . esc_html($org_venue_wednesday_start_time) . '</div>';
            }
            if ($org_venue_wednesday_end_time) {
              echo '<div class="organization-meta"><strong>Venue Wednesday End Time:</strong> ' . esc_html($org_venue_wednesday_end_time) . '</div>';
            }
            if ($org_venue_thursday_start_time) {
              echo '<div class="organization-meta"><strong>Venue Thursday Start Time:</strong> ' . esc_html($org_venue_thursday_start_time) . '</div>';
            }
            if ($org_venue_thursday_end_time) {
              echo '<div class="organization-meta"><strong>Venue Thursday End Time:</strong> ' . esc_html($org_venue_thursday_end_time) . '</div>';
            }
            if ($org_venue_friday_start_time) {
              echo '<div class="organization-meta"><strong>Venue Friday Start Time:</strong> ' . esc_html($org_venue_friday_start_time) . '</div>';
            }
            if ($org_venue_friday_end_time) {
              echo '<div class="organization-meta"><strong>Venue Friday End Time:</strong> ' . esc_html($org_venue_friday_end_time) . '</div>';
            }
            if ($org_venue_saturday_start_time) {
              echo '<div class="organization-meta"><strong>Venue Saturday Start Time:</strong> ' . esc_html($org_venue_saturday_start_time) . '</div>';
            }
            if ($org_venue_saturday_end_time) {
              echo '<div class="organization-meta"><strong>Venue Saturday End Time:</strong> ' . esc_html($org_venue_saturday_end_time) . '</div>';
            }
            if ($org_venue_sunday_start_time) {
              echo '<div class="organization-meta"><strong>Venue Sunday Start Time:</strong> ' . esc_html($org_venue_sunday_start_time) . '</div>';
            }
            if ($org_venue_sunday_end_time) {
              echo '<div class="organization-meta"><strong>Venue Sunday End Time:</strong> ' . esc_html($org_venue_sunday_end_time) . '</div>';
            }

            // Gallery images
            $gallery_ids = [];
            $featured_id  = ead_get_meta(get_the_ID(), 'ead_org_featured_image');
            for ($i = 1; $i <= 5; $i++) {
              $img_id = ead_get_meta(get_the_ID(), 'ead_org_image' . $i . '_id');
              if ($img_id) {
                $gallery_ids[] = $img_id;
              }
            }
            if (empty($gallery_ids)) {
              $stored = ead_get_meta(get_the_ID(), 'ead_org_gallery_images');
              if (!empty($stored)) {
                if (is_string($stored)) {
                  $stored = maybe_unserialize($stored);
                }
                if (is_array($stored)) {
                  $gallery_ids = array_filter($stored);
                }
              }
            }

            if ($gallery_ids) {
              if ($featured_id) {
                $gallery_ids = array_unique(array_merge([$featured_id], $gallery_ids));
              }
              echo '<div class="organization-gallery">';
              foreach ($gallery_ids as $gid) {
                $img = wp_get_attachment_image(($gid ?: 0), 'medium_large');
                if ($img) {
                  echo '<div class="organization-gallery-item">' . $img . '</div>';
                }
              }
              echo '</div>';
            }
        ?>

      </div>

    </article>

  </div>
</div>

<?php endwhile; endif;
get_footer();