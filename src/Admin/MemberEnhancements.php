<?php

// Enhancements Part 1: Admin Notes, Badges, Heatmap (scaffolded)
namespace ArtPulse\Admin;

class MemberEnhancements
{
    public static function register()
    {
        add_action('show_user_profile', [self::class, 'renderNotesField']);
        add_action('edit_user_profile', [self::class, 'renderNotesField']);
        add_action('personal_options_update', [self::class, 'saveNotes']);
        add_action('edit_user_profile_update', [self::class, 'saveNotes']);
    }

    // 1. Admin-only Notes field
    public static function renderNotesField($user)
    {
        if (!current_user_can('manage_options')) return;

        $note = get_user_meta($user->ID, 'ap_admin_note', true);

        echo '<h2>Admin Notes</h2>';
        echo '<textarea name="ap_admin_note" rows="5" cols="70">' . esc_textarea($note) . '</textarea>';
        echo '<p class="description">Visible only to site administrators.</p>';
    }

    public static function saveNotes($user_id)
    {
        if (!current_user_can('manage_options')) return;

        update_user_meta($user_id, 'ap_admin_note', sanitize_textarea_field($_POST['ap_admin_note'] ?? ''));
    }
}