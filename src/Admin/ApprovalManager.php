<?php

namespace ArtPulse\Admin;

/**
 * Handles admin approval workflow for pending submissions.
 */
class ApprovalManager
{
    /**
     * Register approval hooks and meta boxes.
     */
    public static function register()
    {
        // Add meta box to relevant CPTs
        add_action('add_meta_boxes', [ __CLASS__, 'addApprovalMetabox' ]);
        // Handle approval action
        add_action('admin_post_ap_approve_submission', [ __CLASS__, 'handleApproval' ]);
    }

    /**
     * Add an "Approval" meta box on pending posts of our CPTs.
     */
    public static function addApprovalMetabox()
    {
        $post_types = ['artpulse_event', 'artpulse_artist', 'artpulse_artwork', 'artpulse_org'];
        foreach ($post_types as $pt) {
            add_meta_box(
                'ap-approval',
                __('Submission Approval', 'artpulse'),
                [ __CLASS__, 'renderMetabox' ],
                $pt,
                'side',
                'high'
            );
        }
    }

    /**
     * Render the Approval meta box.
     */
    public static function renderMetabox($post)
    {
        if ('pending' !== $post->post_status) {
            echo '<p>' . __('This submission is already reviewed.', 'artpulse') . '</p>';
            return;
        }
        $approve_url = admin_url('admin-post.php');
        $nonce = wp_create_nonce('ap_approve_' . $post->ID);
        ?>
        <form method="post" action="<?php echo esc_url($approve_url); ?>">
            <input type="hidden" name="action" value="ap_approve_submission" />
            <input type="hidden" name="post_id" value="<?php echo esc_attr($post->ID); ?>" />
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />
            <?php submit_button(__('Approve Submission', 'artpulse'), 'primary', 'submit', false); ?>
        </form>
        <?php
    }

    /**
     * Handle the approval request.
     */
    public static function handleApproval()
    {
        if ( ! current_user_can('publish_posts') ) {
            wp_die(__('Insufficient permissions', 'artpulse'));        }
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $nonce   = $_POST['nonce'] ?? '';
        if ( ! wp_verify_nonce($nonce, 'ap_approve_' . $post_id) ) {
            wp_die(__('Security check failed', 'artpulse'));        }
        // Update post status to 'publish'
        wp_update_post([
            'ID'          => $post_id,
            'post_status' => 'publish',
        ]);
        // Redirect back to edit screen
        wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }
}
