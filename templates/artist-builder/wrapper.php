<?php
/**
 * Artist builder wrapper template.
 *
 * @var int[] $builder_artist_ids
 * @var bool  $is_mobile_view
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
        <h1><?php esc_html_e('Manage Artist Profile', 'artpulse-management'); ?></h1>
        <p><?php esc_html_e('Update profile details, media, and preview your public page before publishing.', 'artpulse-management'); ?></p>
    </header>

    <nav class="ap-artist-builder__steps" aria-label="<?php esc_attr_e('Artist builder steps', 'artpulse-management'); ?>">
        <ol>
            <li data-step="profile" class="is-active"><?php esc_html_e('Profile', 'artpulse-management'); ?></li>
            <li data-step="media"><?php esc_html_e('Media', 'artpulse-management'); ?></li>
            <li data-step="preview"><?php esc_html_e('Preview', 'artpulse-management'); ?></li>
            <li data-step="publish"><?php esc_html_e('Publish', 'artpulse-management'); ?></li>
        </ol>
    </nav>

    <section class="ap-artist-builder__portfolios" data-test="artist-portfolios">
        <h2 class="screen-reader-text"><?php esc_html_e('Artist profiles', 'artpulse-management'); ?></h2>
        <ul>
            <?php foreach ($builder_artist_ids as $artist_id) : ?>
                <li data-artist-id="<?php echo esc_attr((string) $artist_id); ?>">
                    <span class="ap-artist-builder__name"><?php echo esc_html(get_the_title($artist_id)); ?></span>
                    <a class="ap-artist-builder__edit" href="<?php echo esc_url(add_query_arg(['artist_id' => $artist_id], home_url('/artist-builder/'))); ?>">
                        <?php esc_html_e('Open Builder', 'artpulse-management'); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <footer class="ap-artist-builder__actions">
        <a class="button button-primary" data-test="org-submit-event" href="<?php echo esc_url(home_url('/submit-event/?artist_id=' . (int) ($builder_artist_ids[0] ?? 0))); ?>">
            <?php esc_html_e('Submit Event', 'artpulse-management'); ?>
        </a>
    </footer>
</div>
