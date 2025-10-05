<?php
/**
 * Directory item partial for ArtPulse directories.
 *
 * @var array   $item_data  The prepared data for the directory card.
 * @var WP_Post $item_post  The post object being rendered.
 * @var string  $post_type  The post type slug.
 */

if (!isset($item_data) || !is_array($item_data)) {
    return;
}

$thumbnail = $item_data['thumbnail'] ?? '';
$permalink = isset($item_data['permalink']) ? $item_data['permalink'] : '';
$title     = isset($item_data['title']) ? $item_data['title'] : '';
$excerpt   = isset($item_data['excerpt']) ? $item_data['excerpt'] : '';
$meta      = isset($item_data['meta']) && is_array($item_data['meta']) ? $item_data['meta'] : [];
?>
<li class="ap-directory__item">
    <article class="ap-directory__card">
        <?php if (!empty($thumbnail)) : ?>
            <figure class="ap-directory__thumb">
                <?php echo wp_kses_post($thumbnail); ?>
            </figure>
        <?php endif; ?>
        <div class="ap-directory__body">
            <h3 class="ap-directory__title">
                <a href="<?php echo esc_url($permalink); ?>">
                    <?php echo esc_html($title); ?>
                </a>
            </h3>
            <?php if (!empty($excerpt)) : ?>
                <p class="ap-directory__excerpt"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>
            <?php if (!empty($meta)) : ?>
                <p class="ap-directory__meta">
                    <?php echo esc_html(implode(' • ', array_filter(array_map('trim', $meta)))); ?>
                </p>
            <?php endif; ?>
        </div>
    </article>
</li>
