<?php
get_header(); 
while ( have_posts() ) : the_post(); ?>
  <div class="nectar-portfolio-single-media">
    <?php the_post_thumbnail('full',['class'=>'img-responsive']); ?>
  </div>
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
    $favorite_label_on  = esc_html__('Unfavorite', 'artpulse');
    $favorite_label_off = esc_html__('Favorite', 'artpulse');
    $follow_label_on    = esc_html__('Unfollow', 'artpulse');
    $follow_label_off   = esc_html__('Follow', 'artpulse');
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
