<?php
namespace EAD\Admin;

class CommentsAdmin {
    public static function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('ArtPulse Comments', 'artpulse-management'); ?></h1>
            <p><?php esc_html_e('Manage comments related to events, organizations, and other content here.', 'artpulse-management'); ?></p>

            <?php
            // Example: Display a list of recent comments (you'll need to adapt this)
            $comments = get_comments([
                'number' => 20, // Number of comments to retrieve
                'status' => 'all', // Show approved, pending, etc.
            ]);

            if ($comments) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>' . esc_html__('Author', 'artpulse-management') . '</th><th>' . esc_html__('Comment', 'artpulse-management') . '</th><th>' . esc_html__('In Response To', 'artpulse-management') . '</th><th>' . esc_html__('Date', 'artpulse-management') . '</th><th></th></tr></thead>';
                echo '<tbody>';

                foreach ($comments as $comment) {
                    $post_title = get_the_title($comment->comment_post_ID);
                    $post_edit_link = get_edit_post_link($comment->comment_post_ID);
                    $comment_edit_link = get_edit_comment_link($comment->comment_ID);

                    echo '<tr>';
                    echo '<td>' . esc_html($comment->comment_author) . '<br><a href="mailto:' . esc_attr($comment->comment_author_email) . '">' . esc_html($comment->comment_author_email) . '</a></td>';
                    echo '<td>' . wp_trim_words($comment->comment_content, 20, '...') . '</td>';
                    echo '<td><a href="' . esc_url($post_edit_link) . '">' . esc_html($post_title) . '</a></td>';
                    echo '<td>' . esc_html(get_comment_date('', $comment->comment_ID)) . '</td>';
                    echo '<td><a href="' . esc_url($comment_edit_link) . '" class="button button-secondary">' . esc_html__('Edit', 'artpulse-management') . '</a></td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            } else {
                echo '<p>' . esc_html__('No comments found.', 'artpulse-management') . '</p>';
            }
            ?>

        </div>
        <?php
    }
}