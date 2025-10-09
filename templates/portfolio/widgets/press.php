<?php
if (!isset($portfolio_widget_scope['post_id'])) {
    return;
}

$post_id = (int) $portfolio_widget_scope['post_id'];
$press_items = get_post_meta($post_id, '_ap_press_items', true);

if (!is_array($press_items) || empty($press_items)) {
    return;
}

$press_items = array_values(array_filter(array_map(static function ($item) {
    if (!is_array($item)) {
        return null;
    }

    $title = isset($item['title']) ? sanitize_text_field($item['title']) : '';
    $url   = isset($item['url']) ? esc_url($item['url']) : '';

    if ($title === '' && $url === '') {
        return null;
    }

    return [
        'title' => $title,
        'url'   => $url,
    ];
}, $press_items)));

if (empty($press_items)) {
    return;
}
?>
<section class="ap-portfolio-widget ap-portfolio-widget--press" id="ap-widget-press">
    <h2 class="ap-portfolio-widget__title"><?php esc_html_e('Press & Highlights', 'artpulse-management'); ?></h2>
    <ul class="ap-portfolio-press">
        <?php foreach ($press_items as $item) : ?>
            <li class="ap-portfolio-press__item">
                <?php if (!empty($item['url'])) : ?>
                    <a href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($item['title'] ?: $item['url']); ?></a>
                <?php else : ?>
                    <span><?php echo esc_html($item['title']); ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
