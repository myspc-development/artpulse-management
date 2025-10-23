<?php
/**
 * Artist builder wrapper template.
 *
 * @var int[] $builder_artist_ids
 * @var bool  $is_mobile_view
 * @var array $builder_profiles
 * @var array $builder_summary
 */

if (!defined('ABSPATH')) {
    exit;
}

$container_class = $is_mobile_view ? 'ap-artist-builder ap-artist-builder--mobile' : 'ap-artist-builder';
$nonce           = wp_create_nonce('ap_portfolio_update');
$nonce_field     = wp_nonce_field('ap_portfolio_update', '_ap_nonce', false, false);
?>
<div class="<?php echo esc_attr($container_class); ?>" data-ap-nonce="<?php echo esc_attr($nonce); ?>">
    <?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <header class="ap-artist-builder__header">
        <h1><?php esc_html_e('Manage Artist Profiles', 'artpulse-management'); ?></h1>
        <p><?php esc_html_e('Review status, continue editing, and publish when you are ready.', 'artpulse-management'); ?></p>
        <dl class="ap-artist-builder__summary">
            <div>
                <dt><?php esc_html_e('Profiles', 'artpulse-management'); ?></dt>
                <dd><?php echo esc_html((string) ($builder_summary['total'] ?? 0)); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('Published', 'artpulse-management'); ?></dt>
                <dd><?php echo esc_html((string) ($builder_summary['published'] ?? 0)); ?></dd>
            </div>
        </dl>
    </header>

    <section class="ap-artist-builder__profiles" data-test="artist-portfolios">
        <h2 class="screen-reader-text"><?php esc_html_e('Artist profiles', 'artpulse-management'); ?></h2>
        <div class="ap-artist-builder__grid">
            <?php foreach ($builder_profiles as $profile) :
                $actions       = $profile['actions'] ?? [];
                $progress      = max(0, min(100, (int) ($profile['progress_percent'] ?? 0)));
                $badge_variant = $profile['badge_variant'] ?? 'info';
                ?>
                <article class="ap-artist-card" data-status="<?php echo esc_attr($profile['status'] ?? 'draft'); ?>">
                    <header class="ap-artist-card__header">
                        <h3 class="ap-artist-card__title"><?php echo esc_html($profile['title'] ?? ''); ?></h3>
                        <span class="ap-dashboard-badge ap-dashboard-badge--<?php echo esc_attr($badge_variant); ?>"><?php echo esc_html($profile['status_label'] ?? ''); ?></span>
                    </header>
                    <?php if (!empty($profile['thumbnail'])) : ?>
                        <div class="ap-artist-card__media">
                            <img src="<?php echo esc_url($profile['thumbnail']); ?>" alt="" loading="lazy" decoding="async" />
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($profile['excerpt'])) : ?>
                        <p class="ap-artist-card__excerpt"><?php echo esc_html($profile['excerpt']); ?></p>
                    <?php endif; ?>
                    <div class="ap-dashboard-progress" role="progressbar" aria-valuenow="<?php echo esc_attr($progress); ?>" aria-valuemin="0" aria-valuemax="100">
                        <span class="ap-dashboard-progress__bar" style="width: <?php echo esc_attr($progress); ?>%"></span>
                    </div>
                    <div class="ap-artist-card__actions">
                        <a class="ap-dashboard-button ap-dashboard-button--primary" href="<?php echo esc_url($actions['builder'] ?? '#'); ?>"><?php esc_html_e('Open Builder', 'artpulse-management'); ?></a>
                        <?php if (!empty($actions['dashboard'])) : ?>
                            <a class="ap-dashboard-button ap-dashboard-button--secondary" href="<?php echo esc_url($actions['dashboard']); ?>"><?php esc_html_e('Dashboard', 'artpulse-management'); ?></a>
                        <?php endif; ?>
                        <?php if (!empty($actions['public'])) : ?>
                            <a class="ap-artist-card__link" href="<?php echo esc_url($actions['public']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View live profile', 'artpulse-management'); ?></a>
                        <?php endif; ?>
                        <?php if (!empty($actions['submit_event'])) : ?>
                            <a class="ap-dashboard-button ap-dashboard-button--primary ap-dashboard-button--outline" href="<?php echo esc_url($actions['submit_event']); ?>"><?php esc_html_e('Submit Event', 'artpulse-management'); ?></a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="ap-artist-builder__guidance">
        <h2><?php esc_html_e('Need a refresher?', 'artpulse-management'); ?></h2>
        <p><?php esc_html_e('Each save is protected by nonce validation and rate limiting. Keep content concise, add media before publishing, and use the dashboard to confirm everything looks great.', 'artpulse-management'); ?></p>
    </aside>
</div>
