<?php
/**
 * Event grid item template.
 *
 * @var array $context Event context provided by the shortcode renderer.
 */

$event = $context ?? [];
$title = isset($event['title']) ? $event['title'] : '';
$url   = isset($event['url']) ? $event['url'] : '';
$start = isset($event['start']) ? strtotime((string) $event['start']) : false;
$end   = isset($event['end']) ? strtotime((string) $event['end']) : false;

$start_formatted = $start ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $start) : '';
$end_formatted   = $end ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $end) : '';
$location        = isset($event['location']) ? $event['location'] : '';
$cost            = isset($event['cost']) ? $event['cost'] : '';
$image           = isset($event['image']) && is_array($event['image']) ? $event['image'] : null;
$thumbnail       = $image['url'] ?? (isset($event['thumbnail']) ? $event['thumbnail'] : '');
$thumb_width     = isset($image['width']) ? (int) $image['width'] : 0;
$thumb_height    = isset($image['height']) ? (int) $image['height'] : 0;
$categories      = isset($event['categoryNames']) ? (array) $event['categoryNames'] : [];
$favorite        = !empty($event['favorite']);
$schema          = isset($event['schema']) ? $event['schema'] : [];

$schema_json = $schema ? wp_json_encode($schema) : '';
?>
<li class="work-item style-3 ap-events-card" data-event-id="<?php echo esc_attr($event['id']); ?>">
    <a class="work-item-link" href="<?php echo esc_url($url); ?>">
        <div class="ap-events-card__media">
            <?php if ($thumbnail) : ?>
                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy"<?php echo $thumb_width > 0 ? ' width="' . esc_attr((string) $thumb_width) . '"' : ''; ?><?php echo $thumb_height > 0 ? ' height="' . esc_attr((string) $thumb_height) . '"' : ''; ?> />
            <?php else : ?>
                <div class="ap-events-card__media--placeholder" aria-hidden="true"></div>
            <?php endif; ?>
        </div>
        <div class="ap-events-card__content">
            <h3 class="ap-events-card__title"><?php echo esc_html($title); ?></h3>
            <div class="ap-events-card__meta">
                <?php if ($start_formatted) : ?>
                    <span class="ap-events-card__date"><?php echo esc_html($start_formatted); ?><?php if ($end_formatted) : ?> – <?php echo esc_html($end_formatted); ?><?php endif; ?></span>
                <?php endif; ?>
                <?php if ($location) : ?>
                    <span class="ap-events-card__location"><?php echo esc_html($location); ?></span>
                <?php endif; ?>
                <?php if ($cost) : ?>
                    <span class="ap-events-card__cost"><?php echo esc_html($cost); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($categories) : ?>
                <div class="ap-events-card__categories">
                    <?php foreach ($categories as $name) : ?>
                        <span class="ap-events__badge"><?php echo esc_html($name); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </a>
    <a href="#" class="ap-events-card__favorite <?php echo $favorite ? 'is-active' : ''; ?>" data-ap-event-favorite="<?php echo esc_attr($event['id']); ?>" aria-label="<?php esc_attr_e('Toggle favorite', 'artpulse-management'); ?>">
        <span aria-hidden="true">★</span>
    </a>
    <?php if ($schema_json) : ?>
        <script type="application/ld+json"><?php echo wp_kses_post($schema_json); ?></script>
    <?php endif; ?>
</li>
