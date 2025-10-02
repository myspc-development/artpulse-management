<?php

namespace ArtPulse\Frontend;

/**
 * Handles output of the front-end submission form and wiring up JS validation.
 */
class SubmissionForms
{
    /**
     * Register shortcode for submission form.
     */
    public static function register(): void
    {
        add_shortcode('ap_submission_form', [__CLASS__, 'render_form']);
    }

    /**
     * Render the submission form HTML.
     *
     * Usage: [ap_submission_form post_type="artpulse_event"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML form.
     */
    public static function render_form(array $atts): string
    {
        $atts = shortcode_atts(
            [
                'post_type'     => 'artpulse_event',
                'include_nonce' => false,
                'nonce_action'  => 'ap_submission',
                'nonce_field'   => 'ap_submission_nonce',
                'submit_label'  => __('Submit', 'artpulse'),
                'submit_name'   => '',
                'extra_classes' => '',
                'notices'       => [],
            ],
            $atts,
            'ap_submission_form'
        );

        $post_type = sanitize_key($atts['post_type']);
        if (empty($post_type)) {
            $post_type = 'artpulse_event';
        }

        $field_prefix = 'ap-' . str_replace('_', '-', $post_type);
        $form_classes = array_filter(array_map('sanitize_html_class', array_merge(
            ['ap-submission-form'],
            explode(' ', (string) $atts['extra_classes'])
        )));

        $organizations = self::get_user_organizations();
        $notices       = is_array($atts['notices']) ? $atts['notices'] : [];

        ob_start();
        ?>
        <div class="ap-form-messages" role="status" aria-live="polite">
            <?php foreach ($notices as $notice): ?>
                <div class="ap-notice ap-notice-<?php echo esc_attr($notice['type']); ?>"><?php echo esc_html($notice['message']); ?></div>
            <?php endforeach; ?>
        </div>
        <form
            class="<?php echo esc_attr(implode(' ', $form_classes)); ?>"
            data-post-type="<?php echo esc_attr($post_type); ?>"
            method="post"
            enctype="multipart/form-data"
        >
            <input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>" />
            <?php if (!empty($atts['include_nonce'])): ?>
                <?php wp_nonce_field($atts['nonce_action'], $atts['nonce_field']); ?>
            <?php endif; ?>

            <?php self::render_common_fields($field_prefix); ?>

            <?php
            switch ($post_type) {
                case 'artpulse_event':
                    self::render_event_fields($field_prefix, $organizations);
                    break;
                case 'artpulse_artist':
                    self::render_artist_fields($field_prefix, $organizations);
                    break;
                case 'artpulse_artwork':
                    self::render_artwork_fields($field_prefix);
                    break;
                case 'artpulse_org':
                    self::render_organization_fields($field_prefix);
                    break;
                default:
                    break;
            }
            ?>

            <p class="ap-field ap-field--file">
                <label for="<?php echo esc_attr($field_prefix . '-images'); ?>"><?php esc_html_e('Images (max 5)', 'artpulse'); ?></label><br>
                <input
                    id="<?php echo esc_attr($field_prefix . '-images'); ?>"
                    type="file"
                    name="images[]"
                    accept="image/*"
                    multiple
                />
            </p>

            <p class="ap-field ap-field--submit">
                <button type="submit"<?php echo !empty($atts['submit_name']) ? ' name="' . esc_attr($atts['submit_name']) . '"' : ''; ?>>
                    <?php echo esc_html($atts['submit_label']); ?>
                </button>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render fields common to every submission form.
     */
    private static function render_common_fields(string $field_prefix): void
    {
        ?>
        <p class="ap-field ap-field--title">
            <label for="<?php echo esc_attr($field_prefix . '-title'); ?>"><?php esc_html_e('Title*', 'artpulse'); ?></label><br>
            <input
                id="<?php echo esc_attr($field_prefix . '-title'); ?>"
                type="text"
                name="title"
                required
                data-required="<?php esc_attr_e('Title is required', 'artpulse'); ?>"
            />
        </p>
        <?php
    }

    /**
     * Render fields for event submissions.
     */
    private static function render_event_fields(string $field_prefix, array $organizations): void
    {
        ?>
        <p class="ap-field ap-field--content">
            <label for="<?php echo esc_attr($field_prefix . '-content'); ?>"><?php esc_html_e('Description*', 'artpulse'); ?></label><br>
            <textarea
                id="<?php echo esc_attr($field_prefix . '-content'); ?>"
                name="content"
                rows="5"
                required
                data-required="<?php esc_attr_e('Description is required', 'artpulse'); ?>"
            ></textarea>
        </p>
        <p class="ap-field ap-field--date">
            <label for="<?php echo esc_attr($field_prefix . '-date'); ?>"><?php esc_html_e('Date*', 'artpulse'); ?></label><br>
            <input
                id="<?php echo esc_attr($field_prefix . '-date'); ?>"
                type="date"
                name="event_date"
                required
                data-required="<?php esc_attr_e('Date is required', 'artpulse'); ?>"
            />
        </p>
        <p class="ap-field ap-field--location">
            <label for="<?php echo esc_attr($field_prefix . '-location'); ?>"><?php esc_html_e('Location*', 'artpulse'); ?></label><br>
            <input
                id="<?php echo esc_attr($field_prefix . '-location'); ?>"
                type="text"
                name="event_location"
                required
                data-required="<?php esc_attr_e('Location is required', 'artpulse'); ?>"
            />
        </p>
        <?php self::render_organization_field('event_organization', $organizations, __('Organization*', 'artpulse')); ?>
        <?php
    }

    /**
     * Render fields for artist submissions.
     */
    private static function render_artist_fields(string $field_prefix, array $organizations): void
    {
        ?>
        <p class="ap-field ap-field--content">
            <label for="<?php echo esc_attr($field_prefix . '-bio'); ?>"><?php esc_html_e('Biography*', 'artpulse'); ?></label><br>
            <textarea
                id="<?php echo esc_attr($field_prefix . '-bio'); ?>"
                name="artist_bio"
                rows="6"
                required
                data-required="<?php esc_attr_e('Biography is required', 'artpulse'); ?>"
            ></textarea>
        </p>
        <?php self::render_organization_field('artist_org', $organizations, __('Organization*', 'artpulse')); ?>
        <?php
    }

    /**
     * Render fields for artwork submissions.
     */
    private static function render_artwork_fields(string $field_prefix): void
    {
        ?>
        <p class="ap-field ap-field--content">
            <label for="<?php echo esc_attr($field_prefix . '-description'); ?>"><?php esc_html_e('Description', 'artpulse'); ?></label><br>
            <textarea
                id="<?php echo esc_attr($field_prefix . '-description'); ?>"
                name="content"
                rows="4"
            ></textarea>
        </p>
        <p class="ap-field ap-field--medium">
            <label for="<?php echo esc_attr($field_prefix . '-medium'); ?>"><?php esc_html_e('Medium*', 'artpulse'); ?></label><br>
            <input
                id="<?php echo esc_attr($field_prefix . '-medium'); ?>"
                type="text"
                name="artwork_medium"
                required
                data-required="<?php esc_attr_e('Medium is required', 'artpulse'); ?>"
            />
        </p>
        <p class="ap-field ap-field--dimensions">
            <label for="<?php echo esc_attr($field_prefix . '-dimensions'); ?>"><?php esc_html_e('Dimensions', 'artpulse'); ?></label><br>
            <input
                id="<?php echo esc_attr($field_prefix . '-dimensions'); ?>"
                type="text"
                name="artwork_dimensions"
            />
        </p>
        <p class="ap-field ap-field--materials">
            <label for="<?php echo esc_attr($field_prefix . '-materials'); ?>"><?php esc_html_e('Materials', 'artpulse'); ?></label><br>
            <input
                id="<?php echo esc_attr($field_prefix . '-materials'); ?>"
                type="text"
                name="artwork_materials"
            />
        </p>
        <?php
    }

    /**
     * Render fields specific to organization submissions.
     */
    private static function render_organization_fields(string $field_prefix): void
    {
        ?>
        <p class="ap-field ap-field--content">
            <label for="<?php echo esc_attr($field_prefix . '-about'); ?>"><?php esc_html_e('Organization Description', 'artpulse'); ?></label><br>
            <textarea
                id="<?php echo esc_attr($field_prefix . '-about'); ?>"
                name="content"
                rows="5"
            ></textarea>
        </p>
        <p class="ap-field ap-field--website">
            <label for="<?php echo esc_attr($field_prefix . '-website'); ?>"><?php esc_html_e('Website', 'artpulse'); ?></label><br>
            <input
                id="<?php echo esc_attr($field_prefix . '-website'); ?>"
                type="url"
                name="org_website"
                placeholder="https://"
            />
        </p>
        <p class="ap-field ap-field--email">
            <label for="<?php echo esc_attr($field_prefix . '-email'); ?>"><?php esc_html_e('Contact Email', 'artpulse'); ?></label><br>
            <input
                id="<?php echo esc_attr($field_prefix . '-email'); ?>"
                type="email"
                name="org_email"
            />
        </p>
        <?php
    }

    /**
     * Render an organization selector or a hidden field when only one organization exists.
     */
    private static function render_organization_field(string $field_name, array $organizations, string $label, bool $required = true): void
    {
        $count = count($organizations);

        if (0 === $count) {
            ?>
            <p class="ap-field ap-field--organization ap-field--empty">
                <span class="ap-field-label"><?php echo esc_html($label); ?></span><br>
                <em><?php esc_html_e('No organization is linked to your account yet. You can submit now and assign one later.', 'artpulse'); ?></em>
            </p>
            <?php
            return;
        }

        if (1 === $count) {
            $organization = reset($organizations);
            ?>
            <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($organization['id']); ?>" />
            <p class="ap-field ap-field--organization ap-field--readonly">
                <span class="ap-field-label"><?php echo esc_html($label); ?></span><br>
                <span class="ap-field-value"><?php echo esc_html($organization['name']); ?></span>
            </p>
            <?php
            return;
        }

        ?>
        <p class="ap-field ap-field--organization">
            <label for="<?php echo esc_attr('ap-organization-' . $field_name); ?>"><?php echo esc_html($label); ?></label><br>
            <select
                id="<?php echo esc_attr('ap-organization-' . $field_name); ?>"
                name="<?php echo esc_attr($field_name); ?>"
                <?php echo $required ? ' required' : ''; ?>
                <?php echo $required ? ' data-required="' . esc_attr__('Organization is required', 'artpulse') . '"' : ''; ?>
            >
                <option value=""><?php esc_html_e('Select an organization', 'artpulse'); ?></option>
                <?php foreach ($organizations as $organization): ?>
                    <option value="<?php echo esc_attr($organization['id']); ?>"><?php echo esc_html($organization['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    /**
     * Return a list of organizations connected to the current user.
     *
     * @return array<int, array{id:int, name:string}>
     */
    private static function get_user_organizations(): array
    {
        if (!is_user_logged_in()) {
            return [];
        }

        $user_id   = get_current_user_id();
        $collected = [];

        $assigned_id = (int) get_user_meta($user_id, 'ap_organization_id', true);
        if ($assigned_id > 0) {
            $post = get_post($assigned_id);
            if ($post && 'artpulse_org' === $post->post_type) {
                $collected[$assigned_id] = [
                    'id'   => $assigned_id,
                    'name' => $post->post_title,
                ];
            }
        }

        $authored_orgs = get_posts([
            'post_type'      => 'artpulse_org',
            'post_status'    => ['publish', 'pending', 'draft'],
            'author'         => $user_id,
            'numberposts'    => -1,
            'suppress_filters' => false,
        ]);

        foreach ($authored_orgs as $organization) {
            $collected[$organization->ID] = [
                'id'   => (int) $organization->ID,
                'name' => $organization->post_title,
            ];
        }

        return array_values($collected);
    }
}
