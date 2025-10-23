<?php
/**
 * Quick action card partial.
 *
 * @var array $action_context
 */

$action       = $action_context;
$progress     = max(0, min(100, (int) ($action['progress_percent'] ?? 0)));
$badge        = $action['badge'] ?? [];
$cta          = $action['cta'] ?? [];
$status       = $action['status'] ?? 'locked';
$status_label = $action['status_label'] ?? '';
$disabled_reason = $action['disabled_reason'] ?? '';
$action_id    = isset($action_id) ? $action_id : '';

$badge_label   = $badge['label'] ?? '';
$badge_variant = $badge['variant'] ?? 'muted';

$cta_label    = $cta['label'] ?? '';
$cta_url      = $cta['url'] ?? '';
$cta_variant  = $cta['variant'] ?? 'secondary';
$cta_disabled = !empty($cta['disabled']);
?>
<div class="ap-dashboard-quick-action" data-status="<?php echo esc_attr($status); ?>"<?php echo $action_id !== '' ? ' id="' . esc_attr($action_id) . '"' : ''; ?>>
    <header class="ap-dashboard-quick-action__header">
        <?php if ($badge_label !== '') : ?>
            <span class="ap-dashboard-badge ap-dashboard-badge--<?php echo esc_attr($badge_variant); ?>"><?php echo esc_html($badge_label); ?></span>
        <?php endif; ?>
        <h4 class="ap-dashboard-quick-action__title"><?php echo esc_html($action['title'] ?? ''); ?></h4>
    </header>
    <?php if (!empty($action['description'])) : ?>
        <p class="ap-dashboard-quick-action__description"><?php echo esc_html($action['description']); ?></p>
    <?php endif; ?>
    <div class="ap-dashboard-progress" role="progressbar" aria-valuenow="<?php echo esc_attr($progress); ?>" aria-valuemin="0" aria-valuemax="100">
        <span class="ap-dashboard-progress__bar" style="width: <?php echo esc_attr($progress); ?>%"></span>
    </div>
    <?php if ($status_label !== '') : ?>
        <p class="ap-dashboard-quick-action__status"><?php echo esc_html($status_label); ?></p>
    <?php endif; ?>
    <?php if ($cta_label !== '') : ?>
        <?php if ($cta_disabled || $cta_url === '') : ?>
            <span class="ap-dashboard-button ap-dashboard-button--<?php echo esc_attr($cta_variant); ?> is-disabled" role="link" aria-disabled="true"><?php echo esc_html($cta_label); ?></span>
        <?php else : ?>
            <a class="ap-dashboard-button ap-dashboard-button--<?php echo esc_attr($cta_variant); ?>" href="<?php echo esc_url($cta_url); ?>"><?php echo esc_html($cta_label); ?></a>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($cta_disabled && $disabled_reason !== '') : ?>
        <p class="ap-dashboard-quick-action__hint"><?php echo esc_html($disabled_reason); ?></p>
    <?php endif; ?>
</div>
