<?php
if (!isset($portfolio_widget_scope['post_id'])) {
    return;
}

$post_id   = (int) $portfolio_widget_scope['post_id'];
$meta      = $portfolio_widget_scope['meta'] ?? [];
$media     = $portfolio_widget_scope['media'] ?? [];
$post_type = $portfolio_widget_scope['post_type'] ?? get_post_type($post_id);

$cover_id  = isset($media['cover_id']) ? (int) $media['cover_id'] : 0;
$logo_id   = isset($media['logo_id']) ? (int) $media['logo_id'] : 0;
$tagline   = isset($meta['tagline']) ? $meta['tagline'] : '';
$title     = get_the_title($post_id);

$cover_src = $cover_id ? wp_get_attachment_image_url($cover_id, 'large') : '';
if (!$cover_src) {
    $cover_src = get_the_post_thumbnail_url($post_id, 'large');
}

$user_id      = get_current_user_id();
$is_favorited = $user_id && class_exists('\\ArtPulse\\Community\\FavoritesManager')
    ? \ArtPulse\Community\FavoritesManager::is_favorited($user_id, $post_id, $post_type)
    : false;
$is_following = $user_id && class_exists('\\ArtPulse\\Community\\FollowManager')
    ? \ArtPulse\Community\FollowManager::is_following($user_id, $post_id, $post_type)
    : false;

$favorite_label_on  = esc_html__('Unfavorite', 'artpulse-management');
$favorite_label_off = esc_html__('Favorite', 'artpulse-management');
$follow_label_on    = esc_html__('Unfollow', 'artpulse-management');
$follow_label_off   = esc_html__('Follow', 'artpulse-management');
?>
<section class="ap-portfolio-widget ap-portfolio-widget--hero" id="ap-widget-hero">
    <?php if ($cover_src) : ?>
        <div class="ap-portfolio-hero__cover">
            <img src="<?php echo esc_url($cover_src); ?>" alt="" />
        </div>
    <?php endif; ?>
    <div class="ap-portfolio-hero__content">
        <?php if ($logo_id) : ?>
            <div class="ap-portfolio-hero__logo">
                <?php echo wp_get_attachment_image($logo_id, 'medium', false, ['class' => 'ap-portfolio-hero__logo-image']); ?>
            </div>
        <?php endif; ?>
        <h1 class="ap-portfolio-hero__title"><?php echo esc_html($title); ?></h1>
        <?php if (!empty($tagline)) : ?>
            <p class="ap-portfolio-hero__tagline"><?php echo esc_html($tagline); ?></p>
        <?php endif; ?>
        <div class="ap-social-actions">
            <button
                type="button"
                class="ap-favorite-btn<?php echo $is_favorited ? ' is-active' : ''; ?>"
                data-ap-fav="1"
                data-ap-object-id="<?php echo esc_attr($post_id); ?>"
                data-ap-object-type="<?php echo esc_attr($post_type); ?>"
                data-ap-active="<?php echo $is_favorited ? '1' : '0'; ?>"
                data-label-on="<?php echo esc_attr($favorite_label_on); ?>"
                data-label-off="<?php echo esc_attr($favorite_label_off); ?>"
                aria-pressed="<?php echo $is_favorited ? 'true' : 'false'; ?>"
            >
                <?php echo $is_favorited ? $favorite_label_on : $favorite_label_off; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
            <button
                type="button"
                class="ap-follow-btn<?php echo $is_following ? ' is-following' : ''; ?>"
                data-ap-follow="1"
                data-ap-object-id="<?php echo esc_attr($post_id); ?>"
                data-ap-object-type="<?php echo esc_attr($post_type); ?>"
                data-ap-active="<?php echo $is_following ? '1' : '0'; ?>"
                data-label-on="<?php echo esc_attr($follow_label_on); ?>"
                data-label-off="<?php echo esc_attr($follow_label_off); ?>"
                aria-pressed="<?php echo $is_following ? 'true' : 'false'; ?>"
            >
                <?php echo $is_following ? $follow_label_on : $follow_label_off; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
        </div>
    </div>
</section>
