<?php
/**
 * Shared wrapper for the unified profile builder.
 *
 * @var string $builder_type
 * @var array  $builder_payload
 * @var array  $builder_config
 * @var array  $builder_progress
 * @var array  $profile_state
 */
?>
<div class="ap-profile-builder" data-ap-autosave-root>
    <header class="ap-profile-builder__header">
        <div id="ap-save-status" role="status" aria-live="polite">
            <?php esc_html_e('Saved just now', 'artpulse-management'); ?>
        </div>
        <nav class="ap-profile-builder__steps" aria-label="<?php esc_attr_e('Profile steps', 'artpulse-management'); ?>">
            <?php foreach ((array) ($builder_progress['steps'] ?? []) as $step) :
                $step_slug     = (string) ($step['slug'] ?? '');
                $is_complete   = !empty($step['complete']);
                $step_classes  = $is_complete ? 'ap-profile-builder__step is-complete' : 'ap-profile-builder__step';
                ?>
                <button type="button" class="<?php echo esc_attr($step_classes); ?>" data-step="<?php echo esc_attr($step_slug); ?>">
                    <span class="ap-profile-builder__step-label"><?php echo esc_html((string) ($step['label'] ?? '')); ?></span>
                </button>
            <?php endforeach; ?>
        </nav>
        <div class="ap-profile-builder__progress" aria-hidden="true">
            <div class="ap-profile-builder__progress-bar" style="width: <?php echo (int) ($builder_progress['percent'] ?? 0); ?>%"></div>
        </div>
    </header>

    <form id="ap-profile-form" novalidate>
        <section class="ap-profile-builder__section" data-step-target="basics">
            <h2><?php esc_html_e('Basics', 'artpulse-management'); ?></h2>

            <label for="ap-profile-title">
                <?php esc_html_e('Profile title', 'artpulse-management'); ?>
                <input
                    id="ap-profile-title"
                    type="text"
                    name="title"
                    value="<?php echo esc_attr((string) ($builder_payload['title'] ?? '')); ?>"
                    maxlength="200"
                    required
                    data-field="title"
                    aria-describedby="ap-error-title"
                />
            </label>
            <p class="ap-profile-builder__error" id="ap-error-title" data-error="title"></p>

            <label for="ap-profile-tagline">
                <?php esc_html_e('Tagline', 'artpulse-management'); ?>
                <input
                    id="ap-profile-tagline"
                    type="text"
                    name="tagline"
                    value="<?php echo esc_attr((string) ($builder_payload['tagline'] ?? '')); ?>"
                    maxlength="160"
                    data-field="tagline"
                    aria-describedby="ap-error-tagline"
                />
            </label>
            <p class="ap-profile-builder__error" id="ap-error-tagline" data-error="tagline"></p>

            <label for="ap-profile-bio">
                <?php esc_html_e('Bio', 'artpulse-management'); ?>
                <textarea
                    id="ap-profile-bio"
                    name="bio"
                    rows="6"
                    data-field="bio"
                    aria-describedby="ap-error-bio"
                ><?php echo esc_textarea((string) ($builder_payload['bio'] ?? '')); ?></textarea>
            </label>
            <p class="ap-profile-builder__error" id="ap-error-bio" data-error="bio"></p>
        </section>

        <section class="ap-profile-builder__section" data-step-target="media">
            <h2><?php esc_html_e('Media', 'artpulse-management'); ?></h2>

            <div class="ap-profile-builder__media">
                <button type="button" class="button" data-media-select="featured_media">
                    <?php esc_html_e('Choose Featured Image', 'artpulse-management'); ?>
                </button>
                <input type="hidden" name="featured_media" value="<?php echo (int) ($builder_payload['featured_media'] ?? 0); ?>" data-field="featured_media" />
                <p class="ap-profile-builder__error" data-error="featured_media"></p>
            </div>

            <div class="ap-profile-builder__gallery" data-gallery>
                <h3><?php esc_html_e('Gallery', 'artpulse-management'); ?></h3>
                <div class="ap-profile-builder__gallery-items" data-gallery-items>
                    <?php foreach ((array) ($builder_payload['gallery'] ?? []) as $gallery_id) : ?>
                        <div class="ap-profile-builder__gallery-item" data-gallery-item="<?php echo (int) $gallery_id; ?>">
                            <span class="ap-profile-builder__gallery-label"><?php echo esc_html(sprintf(__('Media #%d', 'artpulse-management'), (int) $gallery_id)); ?></span>
                            <input type="hidden" name="gallery[]" value="<?php echo (int) $gallery_id; ?>" data-field="gallery" />
                            <button type="button" class="button-link" data-gallery-remove="<?php echo (int) $gallery_id; ?>">
                                <?php esc_html_e('Remove', 'artpulse-management'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" data-gallery-add>
                    <?php esc_html_e('Add gallery media', 'artpulse-management'); ?>
                </button>
                <p class="ap-profile-builder__error" data-error="gallery"></p>
            </div>
        </section>

        <section class="ap-profile-builder__section" data-step-target="links">
            <h2><?php esc_html_e('Links', 'artpulse-management'); ?></h2>

            <label for="ap-profile-website">
                <?php esc_html_e('Website URL', 'artpulse-management'); ?>
                <input
                    id="ap-profile-website"
                    type="url"
                    name="website_url"
                    value="<?php echo esc_attr((string) ($builder_payload['website_url'] ?? '')); ?>"
                    data-field="website_url"
                    aria-describedby="ap-error-website"
                />
            </label>
            <p class="ap-profile-builder__error" id="ap-error-website" data-error="website_url"></p>

            <div class="ap-profile-builder__socials" data-socials>
                <label><?php esc_html_e('Social profiles', 'artpulse-management'); ?></label>
                <div class="ap-profile-builder__social-items" data-social-items>
                    <?php
                    $socials = (array) ($builder_payload['socials'] ?? []);
                    if (empty($socials)) {
                        $socials = [''];
                    }
                    foreach ($socials as $index => $url) :
                        $field_id = 'ap-social-' . $index;
                        ?>
                        <div class="ap-profile-builder__social-item">
                            <input
                                id="<?php echo esc_attr($field_id); ?>"
                                type="url"
                                name="socials[]"
                                value="<?php echo esc_attr((string) $url); ?>"
                                placeholder="https://"
                                data-field="socials"
                                aria-describedby="ap-error-socials"
                            />
                            <button type="button" class="button-link" data-social-remove>
                                <?php esc_html_e('Remove', 'artpulse-management'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" data-social-add>
                    <?php esc_html_e('Add social link', 'artpulse-management'); ?>
                </button>
                <p class="ap-profile-builder__error" id="ap-error-socials" data-error="socials"></p>
            </div>
        </section>

        <section class="ap-profile-builder__section" data-step-target="publish">
            <h2><?php esc_html_e('Publish settings', 'artpulse-management'); ?></h2>

            <label for="ap-profile-visibility">
                <?php esc_html_e('Visibility', 'artpulse-management'); ?>
                <select id="ap-profile-visibility" name="visibility" data-field="visibility" aria-describedby="ap-error-visibility">
                    <option value="public" <?php selected('public', (string) ($builder_payload['visibility'] ?? '')); ?>><?php esc_html_e('Public', 'artpulse-management'); ?></option>
                    <option value="private" <?php selected('private', (string) ($builder_payload['visibility'] ?? '')); ?>><?php esc_html_e('Private', 'artpulse-management'); ?></option>
                </select>
            </label>
            <p class="ap-profile-builder__error" id="ap-error-visibility" data-error="visibility"></p>

            <label for="ap-profile-status">
                <?php esc_html_e('Status', 'artpulse-management'); ?>
                <select id="ap-profile-status" name="status" data-field="status" aria-describedby="ap-error-status">
                    <option value="draft" <?php selected('draft', (string) ($builder_payload['status'] ?? 'draft')); ?>><?php esc_html_e('Draft', 'artpulse-management'); ?></option>
                    <option value="pending" <?php selected('pending', (string) ($builder_payload['status'] ?? 'draft')); ?>><?php esc_html_e('Submit for review', 'artpulse-management'); ?></option>
                    <option value="publish" <?php selected('publish', (string) ($builder_payload['status'] ?? 'draft')); ?>><?php esc_html_e('Publish', 'artpulse-management'); ?></option>
                </select>
            </label>
            <p class="ap-profile-builder__error" id="ap-error-status" data-error="status"></p>

            <div class="ap-profile-builder__actions">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Save profile', 'artpulse-management'); ?>
                </button>
                <?php if (!empty($profile_state['public_url'])) : ?>
                    <a class="button" href="<?php echo esc_url((string) $profile_state['public_url']); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('View profile', 'artpulse-management'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </form>
</div>
