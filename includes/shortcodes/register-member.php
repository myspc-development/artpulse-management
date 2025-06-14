<?php
// Shortcode handler for [register_member]
function artpulse_register_member_shortcode() {
    ob_start();
    ?>
    <form id="register-member-form" method="post" class="max-w-md mx-auto p-6 border rounded shadow space-y-4">
        <h2 class="text-xl font-semibold">Register New Member</h2>
        <input type="text" name="username" placeholder="Username" required class="w-full p-2 border rounded" />
        <input type="email" name="email" placeholder="Email" required class="w-full p-2 border rounded" />
        <input type="password" name="password" placeholder="Password" required class="w-full p-2 border rounded" />
        <label class="block font-medium">Membership Level:</label>
        <select name="membership_level" required class="w-full p-2 border rounded">
            <option value="basic">Basic</option>
            <option value="pro">Pro</option>
        </select>
        <?php wp_nonce_field('register_member_action', 'register_member_nonce'); ?>
        <button type="submit" name="register_member_submit" class="bg-blue-600 text-white px-4 py-2 rounded">Register</button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('register_member', 'artpulse_register_member_shortcode');
