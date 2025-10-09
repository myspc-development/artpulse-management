<?php
if (!isset($portfolio_widget_scope['post_id'])) {
    return;
}

$post_id   = (int) $portfolio_widget_scope['post_id'];
$post_type = $portfolio_widget_scope['post_type'] ?? get_post_type($post_id);

$meta_key = ('artpulse_artist' === $post_type) ? '_ap_artist_id' : '_ap_org_id';

$events = get_posts([
    'post_type'      => 'artpulse_event',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'orderby'        => 'meta_value',
    'meta_key'       => '_ap_event_date',
    'order'          => 'ASC',
    'meta_query'     => [
        [
            'key'   => $meta_key,
            'value' => $post_id,
        ],
        [
            'key'     => '_ap_event_date',
            'value'   => gmdate('Y-m-d'),
            'compare' => '>=',
            'type'    => 'DATE',
        ],
    ],
]);

if (empty($events)) {
    return;
}
?>
<section class="ap-portfolio-widget ap-portfolio-widget--events" id="ap-widget-events">
    <h2 class="ap-portfolio-widget__title"><?php esc_html_e('Upcoming Events', 'artpulse-management'); ?></h2>
    <ul class="ap-portfolio-events">
        <?php foreach ($events as $event) :
            $event_date = get_post_meta($event->ID, '_ap_event_date', true);
            $location   = get_post_meta($event->ID, '_ap_event_location', true);
            ?>
            <li class="ap-portfolio-events__item">
                <a href="<?php echo esc_url(get_permalink($event)); ?>" class="ap-portfolio-events__link">
                    <span class="ap-portfolio-events__title"><?php echo esc_html(get_the_title($event)); ?></span>
                    <?php if (!empty($event_date)) : ?>
                        <time datetime="<?php echo esc_attr($event_date); ?>" class="ap-portfolio-events__date">
                            <?php echo esc_html(mysql2date(get_option('date_format'), $event_date)); ?>
                        </time>
                    <?php endif; ?>
                    <?php if (!empty($location)) : ?>
                        <span class="ap-portfolio-events__location"><?php echo esc_html($location); ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
