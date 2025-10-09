<?php
if (empty($portfolio_widget_scope['meta'])) {
    return;
}

$meta    = $portfolio_widget_scope['meta'];
$website = !empty($meta['website']) ? $meta['website'] : '';
$phone   = !empty($meta['phone']) ? $meta['phone'] : '';
$email   = !empty($meta['email']) ? $meta['email'] : '';
$socials = isset($meta['socials']) && is_array($meta['socials']) ? array_filter($meta['socials']) : [];

if (!$website && !$phone && !$email && empty($socials)) {
    return;
}
?>
<section class="ap-portfolio-widget ap-portfolio-widget--contact" id="ap-widget-contact">
    <h2 class="ap-portfolio-widget__title"><?php esc_html_e('Connect', 'artpulse-management'); ?></h2>
    <ul class="ap-portfolio-contact">
        <?php if ($website) : ?>
            <li class="ap-portfolio-contact__item">
                <span class="ap-portfolio-contact__label"><?php esc_html_e('Website', 'artpulse-management'); ?>:</span>
                <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($website); ?></a>
            </li>
        <?php endif; ?>
        <?php if ($email) : ?>
            <li class="ap-portfolio-contact__item">
                <span class="ap-portfolio-contact__label"><?php esc_html_e('Email', 'artpulse-management'); ?>:</span>
                <a href="<?php echo esc_url('mailto:' . antispambot($email)); ?>"><?php echo esc_html(antispambot($email)); ?></a>
            </li>
        <?php endif; ?>
        <?php if ($phone) : ?>
            <li class="ap-portfolio-contact__item">
                <span class="ap-portfolio-contact__label"><?php esc_html_e('Phone', 'artpulse-management'); ?>:</span>
                <a href="<?php echo esc_url('tel:' . preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a>
            </li>
        <?php endif; ?>
        <?php if ($socials) : ?>
            <li class="ap-portfolio-contact__item">
                <span class="ap-portfolio-contact__label"><?php esc_html_e('Social', 'artpulse-management'); ?>:</span>
                <ul class="ap-portfolio-contact__socials">
                    <?php foreach ($socials as $social_url) : ?>
                        <li><a href="<?php echo esc_url($social_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($social_url); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </li>
        <?php endif; ?>
    </ul>
</section>
