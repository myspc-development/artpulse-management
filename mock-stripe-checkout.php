<?php
/* Template Name: Mock Stripe Checkout */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

$current_user = wp_get_current_user();
$pending_level = get_user_meta($current_user->ID, 'pending_membership_level', true);

if (isset($_GET['simulate_success'])) {
    do_action('mock_stripe_webhook_success', $current_user->ID, $pending_level);
    echo "<p>✅ Payment successful for <strong>{$pending_level}</strong> membership. Redirecting...</p>";
    echo "<script>setTimeout(() => window.location.href = '/dashboard?joined=1', 2000);</script>";
    exit;
}
?>

<h2>🧾 Mock Stripe Checkout</h2>
<p>You're signing up for <strong><?php echo esc_html($pending_level); ?></strong> membership.</p>
<form method="get">
    <input type="hidden" name="simulate_success" value="1" />
    <button class="button button-primary">💳 Simulate Payment Success</button>
</form>
