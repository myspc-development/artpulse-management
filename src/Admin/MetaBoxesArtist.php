public static function save_artist_meta($post_id, $post) {
    if (
        !isset($_POST['ead_artist_meta_nonce_field']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ead_artist_meta_nonce_field'])), 'ead_artist_meta_nonce')
    ) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_type !== 'ead_artist') return;

    $fields = self::get_registered_artist_meta_fields();
    foreach ($fields as $key => $args) {
        $type = $args[0];
        $value = isset($_POST[$key]) ? $_POST[$key] : ($type === 'boolean' ? '0' : '');
        if ($type === 'boolean') {
            update_post_meta($post_id, $key, $value === '1' ? '1' : '0');
        } elseif ($type === 'integer') {
            update_post_meta($post_id, $key, intval($value));
        } elseif ($type === 'array') {
            update_post_meta($post_id, $key, array_map('sanitize_text_field', explode(',', $value)));
        } else {
            update_post_meta($post_id, $key, sanitize_text_field($value));
        }
    }
}
