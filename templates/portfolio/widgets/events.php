<?php
if (!isset($portfolio_widget_scope['post_id'])) {
    return;
}

$post_id   = (int) $portfolio_widget_scope['post_id'];
$post_type = $portfolio_widget_scope['post_type'] ?? get_post_type($post_id);

$meta_key = ('artpulse_artist' === $post_type) ? '_ap_artist_id' : '_ap_org_id';

$limit = (int) apply_filters('artpulse/portfolio/widgets/events/limit', 6, $portfolio_widget_scope);
if ($limit <= 0) {
    return;
}

$now = (int) apply_filters('artpulse/portfolio/widgets/events/now', current_time('timestamp', true), $portfolio_widget_scope);

$events = get_posts([
    'post_type'              => 'artpulse_event',
    'post_status'            => 'publish',
    'posts_per_page'         => $limit,
    'meta_key'               => '_ap_start_ts',
    'meta_type'              => 'NUMERIC',
    'orderby'                => [
        'meta_value_num' => 'ASC',
        'ID'             => 'ASC',
    ],
    'order'                  => 'ASC',
    'no_found_rows'          => true,
    'meta_query'             => [
        [
            'key'   => $meta_key,
            'value' => $post_id,
            'type'  => 'NUMERIC',
        ],
        [
            'key'     => '_ap_start_ts',
            'value'   => $now,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        ],
    ],
    'update_post_term_cache' => false,
]);

if (empty($events)) {
    return;
}
?>
<section class="ap-portfolio-widget ap-portfolio-widget--events" id="ap-widget-events">
    <h2 class="ap-portfolio-widget__title"><?php esc_html_e('Upcoming Events', 'artpulse-management'); ?></h2>
    <ul class="ap-portfolio-events ap-grid">
        <?php
        foreach ($events as $event) :
            $event_id   = $event->ID;
            $start_ts   = (int) get_post_meta($event_id, '_ap_start_ts', true);
            $event_date = get_post_meta($event_id, '_ap_event_date', true);
            $location   = get_post_meta($event_id, '_ap_event_location', true);
            $thumbnail_id = (int) get_post_thumbnail_id($event_id);

            $display_date = '';
            $datetime_attr = '';
            if ($start_ts > 0) {
                $display_date  = wp_date(get_option('date_format'), $start_ts);
                $datetime_attr = wp_date(DATE_ATOM, $start_ts);
            } elseif (!empty($event_date)) {
                $display_date  = mysql2date(get_option('date_format'), $event_date);
                $fallback_ts   = mysql2date('U', $event_date);
                $datetime_attr = $fallback_ts ? wp_date(DATE_ATOM, $fallback_ts) : $event_date;
            }
            ?>
            <li class="ap-portfolio-events__item">
                <a href="<?php echo esc_url(get_permalink($event)); ?>" class="ap-portfolio-events__link">
                    <div class="ap-portfolio-events__media">
                        <?php if ($thumbnail_id) : ?>
                            <?php echo wp_get_attachment_image($thumbnail_id, 'ap-grid', false, [
                                'class'   => 'ap-portfolio-events__image',
                                'loading' => 'lazy',
                            ]); ?>
                        <?php else : ?>
                            <div class="ap-portfolio-events__image-placeholder" aria-hidden="true" style="display:block; width:100%; aspect-ratio:4 / 3; background-color:rgba(0, 0, 0, 0.05);"></div>
                        <?php endif; ?>
                    </div>
                    <div class="ap-portfolio-events__content">
                        <span class="ap-portfolio-events__title"><?php echo esc_html(get_the_title($event)); ?></span>
                        <?php if ($display_date) : ?>
                            <time datetime="<?php echo esc_attr($datetime_attr); ?>" class="ap-portfolio-events__date">
                                <?php echo esc_html($display_date); ?>
                            </time>
                        <?php endif; ?>
                        <?php if (!empty($location)) : ?>
                            <span class="ap-portfolio-events__location"><?php echo esc_html($location); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
