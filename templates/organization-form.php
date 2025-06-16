<div class="container">
<div class="row">
<div class="col">
    <div class="ead-dashboard-card ead-organization-form-wrapper">
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
                echo '<br>' . wp_get_attachment_image($logo_id, [120,120], false, ['class' => 'ead-org-logo-preview']);
                echo '<br><label><input type="checkbox" name="remove_logo" value="1"> Remove Logo</label>';
            }
            ?>
            <input type="file" name="organisation_logo_file" accept="image/*">
        </label><br>
        <?php echo self::render_honeypot( $atts ); ?>
        <button type="submit" class="nectar-button">
            <?php echo $org_id ? esc_html__('Update Organization', 'artpulse-management')
                               : esc_html__('Submit Organization', 'artpulse-management'); ?>
        </button>
    </form>
    </div>
</div>
</div>
</div>
