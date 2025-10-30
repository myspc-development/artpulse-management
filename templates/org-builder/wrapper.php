<?php
/** @var WP_Post $org_post */
/** @var array $builder_meta */
/** @var array $builder_preview */
/** @var string $builder_step */
/** @var string $builder_message */
/** @var string $builder_event_url */
/** @var array $builder_progress */
/** @var array $builder_checklist */
/** @var array $builder_errors */

$builder_nonce      = wp_create_nonce('ap_portfolio_update');
$builder_nonce_html = wp_nonce_field('ap_portfolio_update', '_ap_nonce', false, false);
$steps              = $builder_progress['steps'] ?? [];
$overall_progress   = $builder_progress['overall']['percent'] ?? 0;
?>
<div class="ap-org-builder" data-org-id="<?php echo esc_attr($org_post->ID); ?>" data-ap-nonce="<?php echo esc_attr($builder_nonce); ?>" data-ap-autosave-root="1">
    <?php echo $builder_nonce_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <header class="ap-org-builder__header">
        <h2><?php echo esc_html(get_the_title($org_post)); ?></h2>
        <div class="ap-org-builder__progress">
            <span class="ap-org-builder__progress-label"><?php esc_html_e('Setup progress', 'artpulse-management'); ?></span>
            <div class="ap-dashboard-progress" role="progressbar" aria-valuenow="<?php echo esc_attr($overall_progress); ?>" aria-valuemin="0" aria-valuemax="100">
                <span class="ap-dashboard-progress__bar" style="width: <?php echo esc_attr($overall_progress); ?>%"></span>
            </div>
        </div>
        <nav class="ap-org-builder__nav" aria-label="<?php esc_attr_e('Organization builder steps', 'artpulse-management'); ?>">
            <ol>
                <?php foreach ($steps as $step_data) :
                    $step_slug   = $step_data['slug'] ?? '';
                    $is_active   = $builder_step === $step_slug;
                    $is_complete = !empty($step_data['complete']);
                    $is_locked   = !empty($step_data['locked']);
                    $classes     = ['ap-org-builder__nav-item'];
                    if ($is_active) {
                        $classes[] = 'is-active';
                    }
                    if ($is_complete) {
                        $classes[] = 'is-complete';
                    }
                    if ($is_locked && !$is_active) {
                        $classes[] = 'is-locked';
                    }
                    ?>
                    <li class="<?php echo esc_attr(implode(' ', $classes)); ?>">
                        <a href="<?php echo esc_url(add_query_arg('step', $step_slug)); ?>">
                            <span class="ap-org-builder__nav-label"><?php echo esc_html($step_data['label'] ?? ucfirst($step_slug)); ?></span>
                            <?php if (!empty($step_data['summary'])) : ?>
                                <span class="ap-org-builder__nav-summary"><?php echo esc_html($step_data['summary']); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </header>
    <div id="ap-save-status" aria-live="polite" role="status" class="ap-status"></div>

    <?php if ($builder_message !== '') : ?>
        <div class="ap-org-builder__notice ap-org-builder__notice--success" role="status" aria-live="polite">
            <?php echo esc_html($builder_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($builder_errors)) : ?>
        <div class="ap-org-builder__notice ap-org-builder__notice--error" role="alert">
            <ul>
                <?php foreach ($builder_errors as $error_message) : ?>
                    <li><?php echo esc_html($error_message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="ap-org-builder__content">
        <?php if ('profile' === $builder_step) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-org-builder__panel" data-ap-autosave="profile">
                <?php wp_nonce_field('ap_portfolio_update', '_ap_nonce'); ?>
                <input type="hidden" name="action" value="ap_org_builder_save" />
                <input type="hidden" name="org_id" value="<?php echo esc_attr($org_post->ID); ?>" />
                <input type="hidden" name="builder_step" value="profile" />

                <fieldset class="ap-org-builder__fieldset">
                    <legend><?php esc_html_e('Identity', 'artpulse-management'); ?></legend>
                    <p class="ap-org-builder__field">
                        <label for="ap_org_title"><?php esc_html_e('Organization Name', 'artpulse-management'); ?></label>
                        <input id="ap_org_title" type="text" value="<?php echo esc_attr(get_the_title($org_post)); ?>" disabled data-ap-autosave-field="title" data-ap-autosave-track="true" />
                    </p>
                    <p class="ap-org-builder__field">
                        <label for="ap_org_tagline"><?php esc_html_e('Tagline', 'artpulse-management'); ?></label>
                        <input id="ap_org_tagline" type="text" name="ap_tagline" value="<?php echo esc_attr($builder_meta['tagline']); ?>" data-ap-autosave-field="tagline" data-ap-autosave-track="true" />
                        <span class="ap-org-builder__field-error" data-ap-error="tagline" role="alert" aria-live="polite"></span>
                    </p>
                    <p class="ap-org-builder__field">
                        <label for="ap_org_about"><?php esc_html_e('About', 'artpulse-management'); ?></label>
                        <?php
                        ob_start();
                        wp_editor(
                            wp_kses_post($builder_meta['about']),
                            'ap_org_about',
                            [
                                'textarea_name' => 'ap_about',
                                'textarea_rows' => 8,
                                'media_buttons' => false,
                                'teeny'         => true,
                            ]
                        );
                        $editor_markup = (string) ob_get_clean();
                        $editor_markup = preg_replace(
                            '/<textarea/',
                            '<textarea data-ap-autosave-field="about" data-ap-autosave-track="true"',
                            $editor_markup,
                            1
                        );
                        echo $editor_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                        <span class="ap-org-builder__field-error" data-ap-error="about" role="alert" aria-live="polite"></span>
                    </p>
                </fieldset>

                <fieldset class="ap-org-builder__fieldset">
                    <legend><?php esc_html_e('Contact', 'artpulse-management'); ?></legend>
                    <p class="ap-org-builder__field">
                        <label for="ap_org_website"><?php esc_html_e('Website', 'artpulse-management'); ?></label>
                        <input id="ap_org_website" type="url" name="ap_website" value="<?php echo esc_attr($builder_meta['website']); ?>" data-ap-autosave-field="website" data-ap-autosave-track="true" />
                        <span class="ap-org-builder__field-error" data-ap-error="website" role="alert" aria-live="polite"></span>
                    </p>
                    <p class="ap-org-builder__field">
                        <label for="ap_org_socials"><?php esc_html_e('Social Links', 'artpulse-management'); ?></label>
                        <textarea id="ap_org_socials" name="ap_socials" rows="3" placeholder="<?php esc_attr_e('One URL per line', 'artpulse-management'); ?>" data-ap-autosave-field="socials" data-ap-autosave-track="true"><?php echo esc_textarea($builder_meta['socials']); ?></textarea>
                        <span class="ap-org-builder__field-error" data-ap-error="socials" role="alert" aria-live="polite"></span>
                    </p>
                    <div class="ap-org-builder__field-grid">
                        <p class="ap-org-builder__field">
                            <label for="ap_org_phone"><?php esc_html_e('Phone', 'artpulse-management'); ?></label>
                            <input id="ap_org_phone" type="text" name="ap_phone" value="<?php echo esc_attr($builder_meta['phone']); ?>" data-ap-autosave-field="phone" data-ap-autosave-track="true" />
                            <span class="ap-org-builder__field-error" data-ap-error="phone" role="alert" aria-live="polite"></span>
                        </p>
                        <p class="ap-org-builder__field">
                            <label for="ap_org_email"><?php esc_html_e('Public Email', 'artpulse-management'); ?></label>
                            <input id="ap_org_email" type="email" name="ap_email" value="<?php echo esc_attr($builder_meta['email']); ?>" data-ap-autosave-field="email" data-ap-autosave-track="true" />
                            <span class="ap-org-builder__field-error" data-ap-error="email" role="alert" aria-live="polite"></span>
                        </p>
                    </div>
                    <p class="ap-org-builder__field">
                        <label for="ap_org_address"><?php esc_html_e('Address', 'artpulse-management'); ?></label>
                        <textarea id="ap_org_address" name="ap_address" rows="3" data-ap-autosave-field="address" data-ap-autosave-track="true"><?php echo esc_textarea($builder_meta['address']); ?></textarea>
                        <span class="ap-org-builder__field-error" data-ap-error="address" role="alert" aria-live="polite"></span>
                    </p>
                </fieldset>

                <button type="submit" class="ap-org-builder__submit button button-primary"><?php esc_html_e('Save profile', 'artpulse-management'); ?></button>
            </form>
        <?php elseif ('images' === $builder_step) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="ap-org-builder__panel" data-ap-autosave="media">
                <?php wp_nonce_field('ap_portfolio_update', '_ap_nonce'); ?>
                <input type="hidden" name="action" value="ap_org_builder_save" />
                <input type="hidden" name="org_id" value="<?php echo esc_attr($org_post->ID); ?>" />
                <input type="hidden" name="builder_step" value="images" />

                <fieldset class="ap-org-builder__fieldset">
                    <legend><?php esc_html_e('Branding', 'artpulse-management'); ?></legend>
                    <div class="ap-org-builder__media-grid">
                        <div>
                            <label class="ap-org-builder__upload-label" for="ap_org_logo_input"><?php esc_html_e('Logo', 'artpulse-management'); ?></label>
                            <?php if (!empty($builder_meta['logo_id'])) : ?>
                                <div class="ap-org-builder__image-preview" style="aspect-ratio: 1 / 1;">
                                    <?php echo wp_get_attachment_image((int) $builder_meta['logo_id'], 'medium', false, [
                                        'class'     => 'ap-org-builder__image',
                                        'loading'   => 'lazy',
                                        'decoding'  => 'async',
                                    ]); ?>
                                </div>
                            <?php endif; ?>
                            <?php $logo_help_id = 'ap_org_logo_help'; ?>
                            <input id="ap_org_logo_input" type="file" name="ap_logo" accept="image/jpeg,image/png,image/webp" data-test="org-logo-input" aria-describedby="<?php echo esc_attr($logo_help_id); ?>" />
                            <p class="description" id="<?php echo esc_attr($logo_help_id); ?>"><?php esc_html_e('JPG, PNG, or WebP. Max 10MB.', 'artpulse-management'); ?></p>
                        </div>
                        <div>
                            <label class="ap-org-builder__upload-label" for="ap_org_cover_input"><?php esc_html_e('Cover Image', 'artpulse-management'); ?></label>
                            <?php if (!empty($builder_meta['cover_id'])) : ?>
                                <div class="ap-org-builder__image-preview" style="aspect-ratio: 3 / 2;">
                                    <?php echo wp_get_attachment_image((int) $builder_meta['cover_id'], 'large', false, [
                                        'class'     => 'ap-org-builder__image',
                                        'loading'   => 'lazy',
                                        'decoding'  => 'async',
                                    ]); ?>
                                </div>
                            <?php endif; ?>
                            <?php $cover_help_id = 'ap_org_cover_help'; ?>
                            <input id="ap_org_cover_input" type="file" name="ap_cover" accept="image/jpeg,image/png,image/webp" aria-describedby="<?php echo esc_attr($cover_help_id); ?>" />
                            <p class="description" id="<?php echo esc_attr($cover_help_id); ?>"><?php esc_html_e('JPG, PNG, or WebP. Max 10MB.', 'artpulse-management'); ?></p>
                            <?php if (!empty($builder_meta['cover_id'])) : ?>
                                <label class="ap-org-builder__radio">
                                    <input type="radio" name="ap_featured_image" value="<?php echo esc_attr((int) $builder_meta['cover_id']); ?>" <?php checked((int) $builder_meta['featured_id'], (int) $builder_meta['cover_id']); ?> data-ap-autosave-track="true" />
                                    <span><?php esc_html_e('Use cover image as featured', 'artpulse-management'); ?></span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="ap-org-builder__fieldset">
                    <legend><?php esc_html_e('Gallery', 'artpulse-management'); ?></legend>
                    <div class="ap-org-builder__gallery">
                        <?php foreach ($builder_meta['gallery_ids'] as $index => $gallery_id) : ?>
                            <div class="ap-org-builder__gallery-item">
                                <div class="ap-org-builder__image-preview" style="aspect-ratio: 1 / 1;">
                                    <?php echo wp_get_attachment_image((int) $gallery_id, 'ap-grid', false, [
                                        'class'     => 'ap-org-builder__image',
                                        'loading'   => 'lazy',
                                        'decoding'  => 'async',
                                    ]); ?>
                                </div>
                                <div class="ap-org-builder__gallery-controls">
                                    <label>
                                        <span class="screen-reader-text"><?php esc_html_e('Display order', 'artpulse-management'); ?></span>
                                        <input type="number" name="gallery_order[<?php echo esc_attr((int) $gallery_id); ?>]" value="<?php echo esc_attr($index + 1); ?>" min="1" data-ap-autosave-track="true" data-ap-gallery-order="<?php echo esc_attr((int) $gallery_id); ?>" />
                                    </label>
                                    <label class="ap-org-builder__radio">
                                        <input type="radio" name="ap_featured_image" value="<?php echo esc_attr((int) $gallery_id); ?>" <?php checked((int) $builder_meta['featured_id'], (int) $gallery_id); ?> data-ap-autosave-track="true" />
                                        <span><?php esc_html_e('Use as featured', 'artpulse-management'); ?></span>
                                    </label>
                                </div>
                                <input type="hidden" name="existing_gallery_ids[]" value="<?php echo esc_attr((int) $gallery_id); ?>" />
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <label class="ap-org-builder__upload-label" for="ap_org_gallery_input"><?php esc_html_e('Add gallery images', 'artpulse-management'); ?></label>
                    <?php $gallery_help_id = 'ap_org_gallery_help'; ?>
                    <input id="ap_org_gallery_input" type="file" name="ap_gallery[]" multiple accept="image/jpeg,image/png,image/webp" aria-describedby="<?php echo esc_attr($gallery_help_id); ?>" />
                    <p class="description" id="<?php echo esc_attr($gallery_help_id); ?>"><?php esc_html_e('Upload multiple images up to 10MB each. Use the order fields to arrange them.', 'artpulse-management'); ?></p>
                </fieldset>

                <button type="submit" class="ap-org-builder__submit button button-primary" data-test="org-builder-save"><?php esc_html_e('Save images', 'artpulse-management'); ?></button>
            </form>
        <?php elseif ('preview' === $builder_step) : ?>
            <article class="ap-org-builder__panel ap-org-builder__panel--preview">
                <?php if (!empty($builder_preview['cover_id'])) : ?>
                    <div class="ap-org-builder__preview-cover" style="aspect-ratio: 3 / 2;">
                        <?php echo wp_get_attachment_image((int) $builder_preview['cover_id'], 'ap-grid', false, [
                            'class'     => 'ap-org-builder__preview-cover-img',
                            'loading'   => 'lazy',
                            'decoding'  => 'async',
                        ]); ?>
                    </div>
                <?php elseif (!empty($builder_preview['cover_src'])) : ?>
                    <div class="ap-org-builder__preview-cover" style="aspect-ratio: 3 / 2; background-image: url('<?php echo esc_url($builder_preview['cover_src']); ?>');" aria-hidden="true"></div>
                <?php else : ?>
                    <div class="ap-org-builder__preview-cover ap-org-builder__preview-cover--placeholder" style="aspect-ratio: 3 / 2;" aria-hidden="true"></div>
                <?php endif; ?>

                <div class="ap-org-builder__preview-content">
                    <?php if (!empty($builder_preview['logo_id'])) : ?>
                        <div class="ap-org-builder__preview-logo" style="aspect-ratio: 1 / 1;">
                            <?php echo wp_get_attachment_image((int) $builder_preview['logo_id'], 'thumbnail', false, [
                                'class'     => 'ap-org-builder__preview-logo-img',
                                'loading'   => 'lazy',
                                'decoding'  => 'async',
                            ]); ?>
                        </div>
                    <?php endif; ?>
                    <h3><?php echo esc_html($builder_preview['title']); ?></h3>
                    <?php if (!empty($builder_preview['tagline'])) : ?>
                        <p class="ap-org-builder__preview-tagline"><?php echo esc_html($builder_preview['tagline']); ?></p>
                    <?php endif; ?>
                    <div class="ap-org-builder__preview-about"><?php echo wp_kses_post(wpautop($builder_preview['about'])); ?></div>
                </div>
            </article>
        <?php elseif ('publish' === $builder_step) : ?>
            <section class="ap-org-builder__panel ap-org-builder__panel--publish">
                <header class="ap-org-builder__publish-header">
                    <h3><?php esc_html_e('Publish checklist', 'artpulse-management'); ?></h3>
                    <p><?php esc_html_e('Complete these items to unlock publishing and let visitors view your organization profile.', 'artpulse-management'); ?></p>
                </header>
                <ul class="ap-org-builder__checklist">
                    <?php foreach ($builder_checklist['items'] ?? [] as $item) :
                        $complete = !empty($item['complete']);
                        ?>
                        <li class="<?php echo $complete ? 'is-complete' : ''; ?>">
                            <span class="ap-org-builder__check-indicator" aria-hidden="true"></span>
                            <span><?php echo esc_html($item['label'] ?? ''); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-org-builder__publish-actions">
                    <?php wp_nonce_field('ap_portfolio_update', '_ap_nonce'); ?>
                    <input type="hidden" name="action" value="ap_org_builder_save" />
                    <input type="hidden" name="org_id" value="<?php echo esc_attr($org_post->ID); ?>" />
                    <input type="hidden" name="builder_step" value="publish" />
                    <?php if (!empty($builder_checklist['ready'])) : ?>
                        <button type="submit" class="ap-org-builder__submit button button-primary"><?php esc_html_e('Publish organization', 'artpulse-management'); ?></button>
                    <?php else : ?>
                        <button type="button" class="ap-org-builder__submit button" disabled><?php esc_html_e('Complete previous steps to publish', 'artpulse-management'); ?></button>
                    <?php endif; ?>
                </form>
                <div class="ap-org-builder__publish-guidance">
                    <h4><?php esc_html_e('Next steps', 'artpulse-management'); ?></h4>
                    <p><?php esc_html_e('Use the dashboard to submit events once you publish. You can update media or story sections at any time—changes will remain in draft until you publish again.', 'artpulse-management'); ?></p>
                    <a class="ap-dashboard-button ap-dashboard-button--secondary" href="<?php echo esc_url($builder_event_url); ?>"><?php esc_html_e('Submit an event', 'artpulse-management'); ?></a>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
