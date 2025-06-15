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

                <?php
                // Artist portrait (from meta, else fallback to post thumbnail or placeholder)
                $artist_portrait_id = get_post_meta(get_the_ID(), 'artist_portrait', true);
                $artist_portrait_url = $artist_portrait_id ? wp_get_attachment_image_src($artist_portrait_id, 'full')[0] : '';
                if ($artist_portrait_url) {
                    echo '<div class="artist-meta artist-portrait"><img src="' . esc_url($artist_portrait_url) . '" alt="' . esc_attr(get_the_title()) . ' portrait" loading="lazy"></div>';
                } elseif (has_post_thumbnail()) {
                    the_post_thumbnail('large', ['alt' => get_the_title(), 'loading' => 'lazy']);
                } else {
                    echo '<img src="' . esc_url(get_template_directory_uri() . '/img/placeholder.png') . '" alt="' . esc_attr(get_the_title()) . ' - No portrait available" loading="lazy">';
                }
                ?>

                <div class="single-artist-content">
                    <h1 class="artist-title"><?php the_title(); ?></h1>
                    <div class="artist-description"><?php the_content(); ?></div>
                    <?php
                    // Custom fields/meta
                    $artist_bio      = get_post_meta(get_the_ID(), 'artist_bio', true);
                    $artist_website  = get_post_meta(get_the_ID(), 'artist_website', true);
                    $artist_phone    = get_post_meta(get_the_ID(), 'artist_phone', true);
                    $artist_email    = get_post_meta(get_the_ID(), 'artist_email', true);
                    $artist_name     = get_post_meta(get_the_ID(), 'artist_name', true);
                    $artist_instagram = get_post_meta(get_the_ID(), 'artist_instagram', true);
                    $artist_facebook  = get_post_meta(get_the_ID(), 'artist_facebook', true);
                    $artist_twitter   = get_post_meta(get_the_ID(), 'artist_twitter', true);
                    $artist_linkedin  = get_post_meta(get_the_ID(), 'artist_linkedin', true);

                    echo '<dl class="artist-details">';
                    if ($artist_name) {
                        echo '<dt>' . esc_html__('Name', 'artpulse-management') . ':</dt><dd>' . esc_html($artist_name) . '</dd>';
                    }
                    if ($artist_bio) {
                        echo '<dt>' . esc_html__('Bio', 'artpulse-management') . ':</dt><dd>' . esc_html($artist_bio) . '</dd>';
                    }
                    if ($artist_email) {
                        echo '<dt>' . esc_html__('Email', 'artpulse-management') . ':</dt><dd>' . antispambot(esc_html($artist_email)) . '</dd>';
                    }
                    if ($artist_phone) {
                        echo '<dt>' . esc_html__('Phone', 'artpulse-management') . ':</dt><dd>' . esc_html($artist_phone) . '</dd>';
                    }
                    if ($artist_website) {
                        echo '<dt>' . esc_html__('Website', 'artpulse-management') . ':</dt><dd><a href="' . esc_url($artist_website) . '" target="_blank" rel="noopener">' . esc_html($artist_website) . '</a></dd>';
                    }
                    if ($artist_instagram) {
                        $insta_url = preg_match('#^https?://#', $artist_instagram) ? $artist_instagram : 'https://instagram.com/' . ltrim($artist_instagram, '@');
                        echo '<dt>' . esc_html__('Instagram', 'artpulse-management') . ':</dt><dd><a href="' . esc_url($insta_url) . '" target="_blank" rel="noopener">' . esc_html($artist_instagram) . '</a></dd>';
                    }
                    if ($artist_facebook) {
                        $fb_url = preg_match('#^https?://#', $artist_facebook) ? $artist_facebook : 'https://facebook.com/' . ltrim($artist_facebook, '@');
                        echo '<dt>' . esc_html__('Facebook', 'artpulse-management') . ':</dt><dd><a href="' . esc_url($fb_url) . '" target="_blank" rel="noopener">' . esc_html($artist_facebook) . '</a></dd>';
                    }
                    if ($artist_twitter) {
                        $twitter_url = preg_match('#^https?://#', $artist_twitter) ? $artist_twitter : 'https://twitter.com/' . ltrim($artist_twitter, '@');
                        echo '<dt>' . esc_html__('Twitter', 'artpulse-management') . ':</dt><dd><a href="' . esc_url($twitter_url) . '" target="_blank" rel="noopener">' . esc_html($artist_twitter) . '</a></dd>';
                    }
                    if ($artist_linkedin) {
                        echo '<dt>' . esc_html__('LinkedIn', 'artpulse-management') . ':</dt><dd><a href="' . esc_url($artist_linkedin) . '" target="_blank" rel="noopener">' . esc_html($artist_linkedin) . '</a></dd>';
                    }
                    echo '</dl>';

                    // Gallery images
                    $gallery_ids = get_post_meta(get_the_ID(), 'artist_gallery_images', true);
                    if (!empty($gallery_ids)) {
                        if (is_string($gallery_ids)) {
                            $gallery_ids = maybe_unserialize($gallery_ids);
                            if (!is_array($gallery_ids)) {
                                $gallery_ids = array_filter(array_map('trim', explode(',', $gallery_ids)));
                            }
                        }
                        echo '<div class="artist-gallery">';
                        foreach ($gallery_ids as $img_id) {
                            $img_id = intval($img_id);
                            if ($img_id > 0) {
                                $img_url = wp_get_attachment_image_src($img_id, 'large');
                                if ($img_url) {
                                    echo '<img src="' . esc_url($img_url[0]) . '" alt="' . esc_attr(get_the_title($img_id)) . '" loading="lazy" class="artist-gallery-img">';
                                }
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
