<?php
$user = wp_get_current_user();
echo '<ul class="ead-dashboard-nav">';
echo '<li><a href="/dashboard">Dashboard</a></li>';
if (in_array('member_pro', $user->roles)) echo '<li><a href="/artist-dashboard">Artist</a></li>';
if (in_array('member_org', $user->roles)) echo '<li><a href="/organization-dashboard">Organization</a></li>';
echo '<li><a href="/profile">Profile</a></li>';
echo '<li><a href="/settings">Settings</a></li>';
echo '</ul>';
