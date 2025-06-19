<?php
get_header(); 
while ( have_posts() ) : the_post(); ?>
  <div class="nectar-portfolio-single-media">
    <?php the_post_thumbnail('full',['class'=>'img-responsive']); ?>
  </div>
  <h1 class="entry-title"><?php the_title(); ?></h1>
  <div class="entry-content"><?php the_content(); ?></div>
  <?php 
    $bio = get_post_meta(get_the_ID(),'_ap_artist_bio',true);
    $org = get_post_meta(get_the_ID(),'_ap_artist_org',true);
    if($bio||$org): ?>
    <ul class="portfolio-meta">
      <?php if($bio): ?>
        <li><strong><?php esc_html_e('Biography:','artpulse'); ?></strong> <?php echo wp_kses_post($bio); ?></li>
      <?php endif; ?>
      <?php if($org): ?>
        <li><strong><?php esc_html_e('Organization ID:','artpulse'); ?></strong> <?php echo esc_html($org); ?></li>
      <?php endif; ?>
    </ul>
  <?php endif;
endwhile;
get_footer();
