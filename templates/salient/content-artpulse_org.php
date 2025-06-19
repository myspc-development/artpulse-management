<?php
get_header(); 
while ( have_posts() ) : the_post(); ?>
  <div class="nectar-portfolio-single-media">
    <?php the_post_thumbnail('full',['class'=>'img-responsive']); ?>
  </div>
  <h1 class="entry-title"><?php the_title(); ?></h1>
  <div class="entry-content"><?php the_content(); ?></div>
  <?php 
    $address = get_post_meta(get_the_ID(),'_ap_org_address',true);
    $website = get_post_meta(get_the_ID(),'_ap_org_website',true);
    if($address||$website): ?>
    <ul class="portfolio-meta">
      <?php if($address): ?>
        <li><strong><?php esc_html_e('Address:','artpulse'); ?></strong> <?php echo esc_html($address); ?></li>
      <?php endif; ?>
      <?php if($website): ?>
        <li><strong><?php esc_html_e('Website:','artpulse'); ?></strong> 
          <a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a>
        </li>
      <?php endif; ?>
    </ul>
  <?php endif;
endwhile;
get_footer();
