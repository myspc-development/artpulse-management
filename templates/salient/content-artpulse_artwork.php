<?php
get_header(); 
while ( have_posts() ) : the_post(); ?>
  <div class="nectar-portfolio-single-media">
    <?php the_post_thumbnail('full',['class'=>'img-responsive']); ?>
  </div>
  <h1 class="entry-title"><?php the_title(); ?></h1>
  <div class="entry-content"><?php the_content(); ?></div>
  <?php 
    $medium     = get_post_meta(get_the_ID(),'_ap_artwork_medium',true);
    $dimensions = get_post_meta(get_the_ID(),'_ap_artwork_dimensions',true);
    $materials  = get_post_meta(get_the_ID(),'_ap_artwork_materials',true);
    if($medium||$dimensions||$materials): ?>
    <ul class="portfolio-meta">
      <?php if($medium): ?>
        <li><strong><?php esc_html_e('Medium:','artpulse'); ?></strong> <?php echo esc_html($medium); ?></li>
      <?php endif; ?>
      <?php if($dimensions): ?>
        <li><strong><?php esc_html_e('Dimensions:','artpulse'); ?></strong> <?php echo esc_html($dimensions); ?></li>
      <?php endif; ?>
      <?php if($materials): ?>
        <li><strong><?php esc_html_e('Materials:','artpulse'); ?></strong> <?php echo esc_html($materials); ?></li>
      <?php endif; ?>
    </ul>
  <?php endif;
endwhile;
get_footer();
