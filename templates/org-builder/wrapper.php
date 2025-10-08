<?php
/** @var WP_Post $org_post */
/** @var array $builder_meta */
/** @var array $builder_preview */
/** @var string $builder_step */
/** @var string $builder_message */
/** @var string $builder_event_url */
?>
<div class="ap-org-builder" data-org-id="<?php echo esc_attr($org_post->ID); ?>">
    <header class="ap-org-builder__header">
        <h2><?php echo esc_html(get_the_title($org_post)); ?></h2>
        <nav class="ap-org-builder__nav" aria-label="<?php esc_attr_e('Organization builder steps', 'artpulse-management'); ?>">
            <?php
            $steps = [
                'profile' => __('Profile', 'artpulse-management'),
                'images'  => __('Images', 'artpulse-management'),
                'preview' => __('Preview', 'artpulse-management'),
                'publish' => __('Publish', 'artpulse-management'),
            ];
            foreach ($steps as $slug => $label) :
                $url = add_query_arg('step', $slug);
                $class = 'ap-org-builder__nav-link';
                if ($builder_step === $slug) {
                    $class .= ' is-active';
                }
                ?>
                <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

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
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-org-builder__form">
                <?php wp_nonce_field('ap-org-builder-' . $org_post->ID, 'ap_org_builder_nonce'); ?>
                <input type="hidden" name="action" value="ap_org_builder_save" />
                <input type="hidden" name="org_id" value="<?php echo esc_attr($org_post->ID); ?>" />
                <input type="hidden" name="builder_step" value="profile" />

                <p class="ap-org-builder__field">
                    <label for="ap_org_title"><?php esc_html_e('Organization Name', 'artpulse-management'); ?></label>
                    <input id="ap_org_title" type="text" value="<?php echo esc_attr(get_the_title($org_post)); ?>" disabled />
                </p>

                <p class="ap-org-builder__field">
                    <label for="ap_org_tagline"><?php esc_html_e('Tagline', 'artpulse-management'); ?></label>
                    <input id="ap_org_tagline" type="text" name="ap_tagline" value="<?php echo esc_attr($builder_meta['tagline']); ?>" />
                </p>

                <p class="ap-org-builder__field">
                    <label for="ap_org_about"><?php esc_html_e('About', 'artpulse-management'); ?></label>
                    <?php
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
                    ?>
                </p>

                <p class="ap-org-builder__field">
                    <label for="ap_org_website"><?php esc_html_e('Website', 'artpulse-management'); ?></label>
                    <input id="ap_org_website" type="url" name="ap_website" value="<?php echo esc_attr($builder_meta['website']); ?>" />
                </p>

                <p class="ap-org-builder__field">
                    <label for="ap_org_socials"><?php esc_html_e('Social Links', 'artpulse-management'); ?></label>
                    <textarea id="ap_org_socials" name="ap_socials" rows="3" placeholder="<?php esc_attr_e('One URL per line', 'artpulse-management'); ?>"><?php echo esc_textarea($builder_meta['socials']); ?></textarea>
                </p>

                <p class="ap-org-builder__field">
                    <label for="ap_org_phone"><?php esc_html_e('Phone', 'artpulse-management'); ?></label>
                    <input id="ap_org_phone" type="text" name="ap_phone" value="<?php echo esc_attr($builder_meta['phone']); ?>" />
                </p>

                <p class="ap-org-builder__field">
                    <label for="ap_org_email"><?php esc_html_e('Public Email', 'artpulse-management'); ?></label>
                    <input id="ap_org_email" type="email" name="ap_email" value="<?php echo esc_attr($builder_meta['email']); ?>" />
                </p>

                <p class="ap-org-builder__field">
                    <label for="ap_org_address"><?php esc_html_e('Address', 'artpulse-management'); ?></label>
                    <textarea id="ap_org_address" name="ap_address" rows="3"><?php echo esc_textarea($builder_meta['address']); ?></textarea>
                </p>

                <button type="submit" class="ap-org-builder__submit button button-primary"><?php esc_html_e('Save profile', 'artpulse-management'); ?></button>
            </form>
        <?php elseif ('images' === $builder_step) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="ap-org-builder__form">
                <?php wp_nonce_field('ap-org-builder-' . $org_post->ID, 'ap_org_builder_nonce'); ?>
                <input type="hidden" name="action" value="ap_org_builder_save" />
                <input type="hidden" name="org_id" value="<?php echo esc_attr($org_post->ID); ?>" />
                <input type="hidden" name="builder_step" value="images" />

                <fieldset class="ap-org-builder__field">
                    <legend><?php esc_html_e('Logo', 'artpulse-management'); ?></legend>
                    <?php if (!empty($builder_meta['logo_id'])) : ?>
                        <div class="ap-org-builder__image-preview" style="aspect-ratio: 1 / 1;">
                            <?php echo wp_get_attachment_image((int) $builder_meta['logo_id'], 'ap-grid', false, [
                                'class'     => 'ap-org-builder__image',
                                'loading'   => 'lazy',
                                'decoding'  => 'async',
                            ]); ?>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="ap_logo" accept="image/jpeg,image/png,image/webp" data-test="org-logo-input" />
                    <p class="description"><?php esc_html_e('JPG, PNG, or WebP. Max 10MB.', 'artpulse-management'); ?></p>
                </fieldset>

                <fieldset class="ap-org-builder__field">
                    <legend><?php esc_html_e('Cover Image', 'artpulse-management'); ?></legend>
                    <?php if (!empty($builder_meta['cover_id'])) : ?>
                        <div class="ap-org-builder__image-preview" style="aspect-ratio: 3 / 2;">
                            <?php echo wp_get_attachment_image((int) $builder_meta['cover_id'], 'ap-grid', false, [
                                'class'     => 'ap-org-builder__image',
                                'loading'   => 'lazy',
                                'decoding'  => 'async',
                            ]); ?>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="ap_cover" accept="image/jpeg,image/png,image/webp" />
                    <p class="description"><?php esc_html_e('JPG, PNG, or WebP. Max 10MB.', 'artpulse-management'); ?></p>
                    <?php if (!empty($builder_meta['cover_id'])) : ?>
                        <label class="ap-org-builder__radio">
                            <input type="radio" name="ap_featured_image" value="<?php echo esc_attr((int) $builder_meta['cover_id']); ?>" <?php checked((int) $builder_meta['featured_id'], (int) $builder_meta['cover_id']); ?> />
                            <span><?php esc_html_e('Use cover image as featured', 'artpulse-management'); ?></span>
                        </label>
                    <?php endif; ?>
                </fieldset>

                <fieldset class="ap-org-builder__field">
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
                                        <input type="number" name="gallery_order[<?php echo esc_attr((int) $gallery_id); ?>]" value="<?php echo esc_attr($index + 1); ?>" min="1" />
                                    </label>
                                    <label class="ap-org-builder__radio">
                                        <input type="radio" name="ap_featured_image" value="<?php echo esc_attr((int) $gallery_id); ?>" <?php checked((int) $builder_meta['featured_id'], (int) $gallery_id); ?> />
                                        <span><?php esc_html_e('Use as featured', 'artpulse-management'); ?></span>
                                    </label>
                                </div>
                                <input type="hidden" name="existing_gallery_ids[]" value="<?php echo esc_attr((int) $gallery_id); ?>" />
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="file" name="ap_gallery[]" multiple accept="image/jpeg,image/png,image/webp" />
                    <p class="description"><?php esc_html_e('Upload multiple JPG, PNG, or WebP images up to 10MB each. Use the order fields above to sort.', 'artpulse-management'); ?></p>
                </fieldset>

                <button type="submit" class="ap-org-builder__submit button button-primary" data-test="org-builder-save"><?php esc_html_e('Save images', 'artpulse-management'); ?></button>
            </form>
        <?php elseif ('preview' === $builder_step) : ?>
            <article class="ap-org-builder__preview">
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
                    <?php if ($builder_preview['tagline']) : ?>
                        <p class="ap-org-builder__preview-tagline"><?php echo esc_html($builder_preview['tagline']); ?></p>
                    <?php endif; ?>
                    <?php if ($builder_preview['about']) : ?>
                        <div class="ap-org-builder__preview-about"><?php echo wp_kses_post(wpautop($builder_preview['about'])); ?></div>
                    <?php endif; ?>
                    <p>
                        <a class="button button-secondary" href="<?php echo esc_url($builder_preview['permalink']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View live profile', 'artpulse-management'); ?></a>
                    </p>
                </div>
            </article>
        <?php elseif ('publish' === $builder_step) : ?>
            <section class="ap-org-builder__publish">
                <p><?php esc_html_e('Ready to share your organization with the community? Publish to make your profile publicly visible.', 'artpulse-management'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('ap-org-builder-' . $org_post->ID, 'ap_org_builder_nonce'); ?>
                    <input type="hidden" name="action" value="ap_org_builder_save" />
                    <input type="hidden" name="org_id" value="<?php echo esc_attr($org_post->ID); ?>" />
                    <input type="hidden" name="builder_step" value="publish" />
                    <button type="submit" class="button button-primary"><?php esc_html_e('Publish Organization', 'artpulse-management'); ?></button>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <footer class="ap-org-builder__footer">
        <a class="button button-secondary" href="<?php echo esc_url($builder_event_url); ?>" data-test="org-submit-event"><?php esc_html_e('Submit Event', 'artpulse-management'); ?></a>
    </footer>
</div>
