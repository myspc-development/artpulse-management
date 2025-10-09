<?php
$location = $portfolio_widget_scope['location'] ?? [];
$address  = isset($location['address']) ? trim((string) $location['address']) : '';
$lat      = isset($location['lat']) ? (float) $location['lat'] : 0.0;
$lng      = isset($location['lng']) ? (float) $location['lng'] : 0.0;

if (!$address && (0.0 === $lat || 0.0 === $lng)) {
    return;
}

$query = $address ? rawurlencode($address) : sprintf('%s,%s', $lat, $lng);
$map_url = sprintf('https://www.google.com/maps/search/?api=1&query=%s', $query);
?>
<section class="ap-portfolio-widget ap-portfolio-widget--map" id="ap-widget-map">
    <h2 class="ap-portfolio-widget__title"><?php esc_html_e('Location', 'artpulse-management'); ?></h2>
    <div class="ap-portfolio-map">
        <?php if ($address) : ?>
            <p class="ap-portfolio-map__address"><?php echo esc_html($address); ?></p>
        <?php endif; ?>
        <a class="ap-portfolio-map__link" href="<?php echo esc_url($map_url); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('View on Google Maps', 'artpulse-management'); ?>
        </a>
    </div>
</section>
