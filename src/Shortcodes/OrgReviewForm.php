<?php
namespace EAD\Shortcodes;

use EAD\Shortcodes\HoneypotTrait;

class OrgReviewForm {
    use HoneypotTrait;
    public static function register() {
        add_shortcode('ead_org_review_form', [self::class, 'render']);
    }

    public static function render($atts) {
        $atts = shortcode_atts([
            'org_id' => 0
        ], $atts);

        $org_id = intval($atts['org_id']);

        ob_start();
        ?>
        <div class="ead-org-review-form">
            <div id="ead-org-review-msg"></div>
            <form id="ead-org-review-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('ead_org_review_submit', 'ead_org_review_nonce'); ?>
                <input type="hidden" name="organization_id" value="<?php echo esc_attr($org_id); ?>">
                <label>Name <input type="text" name="reviewer_name" required></label><br>
                <label>Rating
                    <select name="review_rating" required>
                        <option value="">Rate…</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>"><?php echo str_repeat('★', $i) . str_repeat('☆', 5 - $i); ?></option>
                        <?php endfor; ?>
                    </select>
                </label><br>
                <label>Comment<br><textarea name="review_comment" rows="3" required></textarea></label><br>
                <?php echo self::render_honeypot( $atts ); ?>

                <!-- Google reCAPTCHA -->
                <div class="g-recaptcha" data-sitekey="YOUR_PUBLIC_SITE_KEY"></div>
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>

                <button type="submit">Submit Review</button>
            </form>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById("ead-org-review-form");
            if (!form) return;
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                const msg = document.getElementById("ead-org-review-msg");
                msg.innerHTML = "<span>Submitting…</span>";

                const fd = new FormData(form);
                fd.append('action', 'ead_submit_org_review'); // for AJAX

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        msg.innerHTML = '<div style="background:#eaffea;color:#308000;border-radius:8px;padding:10px 14px;margin-bottom:18px;">' + data.message + '</div>';
                        form.reset();
                    } else {
                        msg.innerHTML = '<div style="background:#ffeaea;color:#a00;border-radius:8px;padding:10px 14px;margin-bottom:18px;">' + (data.message || 'Submission failed.') + '</div>';
                    }
                }).catch(() => {
                    msg.innerHTML = '<div style="background:#ffeaea;color:#a00;border-radius:8px;padding:10px 14px;margin-bottom:18px;">AJAX error.</div>';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
