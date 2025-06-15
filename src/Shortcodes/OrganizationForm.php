<?php
namespace EAD\Shortcodes;

use EAD\Shortcodes\HoneypotTrait;

class OrganizationForm {
    use HoneypotTrait;
    public static function register() {
        add_shortcode('ead_organization_form', [self::class, 'render']);
        add_action('wp_loaded', [self::class, 'handle_submit']);
    }

    public static function handle_submit() {
        if (empty($_POST['ead_organization_nonce']) || !is_user_logged_in()) return;
        if (!wp_verify_nonce($_POST['ead_organization_nonce'], 'ead_organization_submit')) return;

        if ( self::honeypot_triggered() ) {
            return;
        }

        $is_edit = !empty($_POST['organization_id']);
        $fields = self::fields();

        $meta = [];
        foreach ($fields as $key => $args) {
            $raw = $_POST[$key] ?? '';
            if (strpos($key, 'email') !== false) {
                $meta[$key] = sanitize_email($raw);
            } elseif (strpos($key, 'url') !== false) {
                $meta[$key] = esc_url_raw($raw);
            } elseif (strpos($key, 'description') !== false) {
                $meta[$key] = sanitize_textarea_field($raw);
            } else {
                $meta[$key] = sanitize_text_field($raw);
            }
        }

        if (empty($meta['ead_org_name'])) {
            wp_die(__('Organization name is required.', 'artpulse-management'));
        }

        // Logo upload handling
        if (!empty($_FILES['organisation_logo_file']['tmp_name'])) {
            $file     = $_FILES['organisation_logo_file'];
            $max_size = 2 * 1024 * 1024; // 2 MB
            if ($file['size'] > $max_size) {
                wp_die(__('Logo image must not exceed 2 MB.', 'artpulse-management'));
            }

            $logo_id = media_handle_upload('organisation_logo_file', 0);
            if (!is_wp_error($logo_id)) {
                $meta['ead_org_logo_id'] = $logo_id;
            }
        }

        // Remove logo
        if (!empty($_POST['remove_logo'])) {
            $meta['ead_org_logo_id'] = '';
        }

        if ($is_edit) {
            $org_id = intval($_POST['organization_id']);
            $org_post = get_post($org_id);
            if ($org_post && $org_post->post_type === 'ead_organization' && $org_post->post_author == get_current_user_id()) {
                wp_update_post([
                    'ID' => $org_id,
                    'post_title' => $meta['ead_org_name'],
                    'post_content' => (string) $meta['organisation_description'],
                    'post_status' => 'pending',
                ]);
                foreach ($meta as $k => $v) update_post_meta($org_id, $k, $v);
                wp_redirect(add_query_arg('ead_org_msg', 'updated', get_permalink()));
                exit;
            }
        } else {
            $org_id = wp_insert_post([
                'post_type' => 'ead_organization',
                'post_title' => $meta['ead_org_name'],
                'post_content' => (string) $meta['organisation_description'],
                'post_status' => 'pending',
                'post_author' => get_current_user_id(),
            ]);
            if (!is_wp_error($org_id)) {
                foreach ($meta as $k => $v) update_post_meta($org_id, $k, $v);
                wp_redirect(add_query_arg('ead_org_msg', 'submitted', get_permalink()));
                exit;
            }
        }
    }

    public static function render($atts = []) {
        if (!is_user_logged_in()) {
            return '<div class="ead-dashboard-card"><p>Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to submit or edit an organization.</p></div>';
        }

        $org_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $org = $org_id ? get_post($org_id) : null;
        if ($org && ($org->post_type !== 'ead_organization' || $org->post_author != get_current_user_id())) {
            return '<div class="ead-dashboard-card"><p>You cannot edit this organization.</p></div>';
        }

        $fields = self::fields();
        $msg = '';
        if (!empty($_GET['ead_org_msg'])) {
            if ($_GET['ead_org_msg'] === 'updated') $msg = '<div style="background:#eaffea;color:#308000;border-radius:8px;padding:10px 14px;margin-bottom:18px;">Organization updated and pending review!</div>';
            if ($_GET['ead_org_msg'] === 'submitted') $msg = '<div style="background:#eaffea;color:#308000;border-radius:8px;padding:10px 14px;margin-bottom:18px;">Organization submitted for review!</div>';
        }

        $meta = [];
        foreach ($fields as $k => $args) {
            $meta[$k] = $org_id ? get_post_meta($org_id, $k, true) : '';
        }
        $meta['ead_org_name'] = $org_id ? get_post_meta($org_id, 'ead_org_name', true) : $meta['ead_org_name'];
        $meta['organisation_description'] = $org_id ? $org->post_content : $meta['organisation_description'];

        ob_start(); ?>
        <div class="ead-dashboard-card">
            <?php echo $msg; ?>
            <h2><?php echo $org_id ? 'Edit' : 'Add'; ?> Organization</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('ead_organization_submit', 'ead_organization_nonce'); ?>
                <?php if ($org_id): ?>
                    <input type="hidden" name="organization_id" value="<?php echo esc_attr($org_id); ?>">
                <?php endif; ?>
                <h4>Organization Info</h4>
                <?php foreach ($fields as $field_key => $args): ?>
                    <?php
                    $label = ucwords(str_replace('_', ' ', $field_key));
                    $value = $meta[$field_key];
                    $type  = 'text';
                    if (strpos($field_key, 'description') !== false) {
                        $type = 'textarea';
                    } elseif (strpos($field_key, 'email') !== false) {
                        $type = 'email';
                    } elseif (strpos($field_key, 'url') !== false) {
                        $type = 'url';
                    } elseif (strpos($field_key, 'phone') !== false) {
                        $type = 'tel';
                    } elseif (strpos($field_key, 'start_time') !== false || strpos($field_key, 'end_time') !== false) {
                        $type = 'time';
                    }
                    ?>
                    <label>
                    <?php echo esc_html($label); ?><?php echo $field_key === 'ead_org_name' ? '*' : ''; ?>
                        <?php if ($type === 'textarea'): ?>
                            <textarea name="<?php echo esc_attr($field_key); ?>" rows="3" <?php echo $field_key === 'ead_org_name' ? 'required' : ''; ?>><?php echo esc_textarea($value); ?></textarea>
                        <?php else: ?>
                            <input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr($value); ?>" <?php echo $field_key === 'ead_org_name' ? 'required' : ''; ?> />
                        <?php endif; ?>
                    </label><br>
                <?php endforeach; ?>

                <label>Logo (Image)
                    <?php
                    $logo_id = isset($meta['ead_org_logo_id']) ? intval($meta['ead_org_logo_id']) : 0;
                    if ($logo_id) {
                        echo '<br>' . wp_get_attachment_image($logo_id, [120,120], false, ['style' => 'border-radius:8px;']);
                        echo '<br><label><input type="checkbox" name="remove_logo" value="1"> Remove Logo</label>';
                    }
                    ?>
                    <input type="file" name="organisation_logo_file" accept="image/*">
                </label><br>
                <?php echo self::render_honeypot( $atts ); ?>
                <button type="submit" class="button button-primary">
                    <?php echo $org_id ? esc_html__('Update Organization', 'artpulse-management')
                                       : esc_html__('Submit Organization', 'artpulse-management'); ?>
                </button>
            </form>
        </div>
        <style>
        .ead-dashboard-card {background:#fff;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,0.09);padding:2rem;max-width:650px;margin:2rem auto;}
        label {display:block;margin:8px 0 4px 0;}
        input[type=text],input[type=email],input[type=url],textarea {width:100%;max-width:440px;}
        hr {margin:18px 0;}
        </style>
        <?php
        return ob_get_clean();
    }

    private static function fields() {
        return [
            'ead_org_name' => ['string'],
            'organisation_description' => ['string'],
            'organisation_email' => ['string'],
            'organisation_phone' => ['string'],
            'organisation_website_url' => ['string'],
            'organisation_facebook_url' => ['string'],
            'organisation_twitter_url' => ['string'],
            'organisation_instagram_url' => ['string'],
            'organisation_artsy_url' => ['string'],
            'organisation_pinterest_url' => ['string'],
            'organisation_youtube_url' => ['string'],
            'organisation_street_address' => ['string'],
            'organisation_postal_address' => ['string'],
            'venue_address' => ['string'],
            'venue_email' => ['string'],
            'venue_phone' => ['string'],
            'venue_monday_start_time'=>['string'], 'venue_monday_end_time'=>['string'],
            'venue_tuesday_start_time'=>['string'], 'venue_tuesday_end_time'=>['string'],
            'venue_wednesday_start_time'=>['string'], 'venue_wednesday_end_time'=>['string'],
            'venue_thursday_start_time'=>['string'], 'venue_thursday_end_time'=>['string'],
            'venue_friday_start_time'=>['string'], 'venue_friday_end_time'=>['string'],
            'venue_saturday_start_time'=>['string'], 'venue_saturday_end_time'=>['string'],
            'venue_sunday_start_time'=>['string'], 'venue_sunday_end_time'=>['string'],
        ];
    }
}
