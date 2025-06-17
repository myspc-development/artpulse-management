<?php
namespace ArtPulse\Admin;

class ProfileLinkRequestAdmin {
    public static function register() {
        add_submenu_page(
            'ap-org-dashboard',
            'Profile Link Requests',
            'Profile Link Requests',
            'manage_options',
            'ap-link-requests',
            [self::class, 'render']
        );
    }

    public static function render() {
        // Get pending requests
        $args = [
            'post_type'   => 'ap_profile_link_request',
            'post_status' => 'publish',
            'meta_query'  => [
                ['key' => 'status', 'value' => 'pending'],
            ],
            'numberposts' => 100,
        ];
        $requests = get_posts($args);

        $nonce = wp_create_nonce('ap_link_request_admin');

        echo '<div class="wrap"><h1>Profile Link Requests</h1>';
        echo '<form id="ap-link-requests-form">';
        echo '<table class="widefat"><thead><tr>
            <th><input type="checkbox" id="ap-lr-select-all" /></th>
            <th>ID</th><th>Artist</th><th>Org</th><th>Message</th><th>Date</th><th>Status</th>
        </tr></thead><tbody>';
        foreach ($requests as $req) {
            $artist = esc_html(get_post_meta($req->ID, 'artist_user_id', true));
            $org = esc_html(get_post_meta($req->ID, 'org_id', true));
            $msg = esc_html(get_post_meta($req->ID, 'message', true));
            $date = esc_html(get_post_meta($req->ID, 'requested_on', true));
            $status = esc_html(get_post_meta($req->ID, 'status', true));
            echo "<tr>
                <td><input type='checkbox' name='request_ids[]' value='".esc_attr($req->ID)."' /></td>
                <td>".esc_html($req->ID)."</td>
                <td>{$artist}</td>
                <td>{$org}</td>
                <td>{$msg}</td>
                <td>{$date}</td>
                <td>{$status}</td>
            </tr>";
        }
        echo '</tbody></table>';
        echo '<button type="button" class="button" id="ap-lr-approve">Approve Selected</button> ';
        echo '<button type="button" class="button" id="ap-lr-deny">Deny Selected</button>';
        echo '</form></div>';

        // Output the JavaScript for AJAX handling
        self::print_js($nonce);
    }

    private static function print_js($nonce) {
        ?>
        <script>
        jQuery(document).ready(function($){
            // "Select all" checkbox logic
            $('#ap-lr-select-all').on('change', function(){
                $('input[name="request_ids[]"]').prop('checked', this.checked);
            });

            function bulkAction(action) {
                var ids = $('input[name="request_ids[]"]:checked').map(function(){ return this.value; }).get();
                if(ids.length === 0) {
                    alert('Select at least one request.');
                    return;
                }
                var data = {
                    action: action,
                    request_ids: ids,
                    _ajax_nonce: '<?php echo esc_js($nonce); ?>'
                };
                $.post(ajaxurl, data, function(resp){
                    if(resp.success){
                        alert('Done! Reloading...');
                        location.reload();
                    } else {
                        alert('Error: ' + (resp.data || 'Unknown error.'));
                    }
                });
            }

            $('#ap-lr-approve').on('click', function(e){
                e.preventDefault();
                bulkAction('ap_approve_link_requests');
            });
            $('#ap-lr-deny').on('click', function(e){
                e.preventDefault();
                bulkAction('ap_deny_link_requests');
            });
        });
        </script>
        <?php
    }
}

add_action('admin_menu', ['\\ArtPulse\\Admin\\ProfileLinkRequestAdmin', 'register']);
