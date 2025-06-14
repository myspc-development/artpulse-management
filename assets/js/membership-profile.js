// membership-profile.js

async function updateUserProfile(data) {
  try {
    const response = await fetch('/wp-json/artpulse/v1/user-profile', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': artpulse_vars.rest_nonce,
      },
      body: JSON.stringify({
        name: data.name,
        bio: data.bio,
        badge_label: data.badge_label,
        membership_level: data.membership_level,
      }),
    });

    const result = await response.json();

    if (response.ok && result.success) {
      showMessage('✅ Profile updated successfully!', 'success');
    } else {
      showMessage('⚠️ Failed to update profile.', 'error');
      console.warn(result);
    }
  } catch (error) {
    showMessage('❌ Error contacting server.', 'error');
    console.error(error);
  }
}

function showMessage(msg, type) {
  const el = document.getElementById('membership-message');
  if (!el) return;
  el.textContent = msg;
  el.className = `message ${type}`;
  el.style.display = 'block';
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('membership-form');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const data = {
      name: form.querySelector('[name="name"]').value,
      bio: form.querySelector('[name="bio"]').value,
      badge_label: form.querySelector('[name="badge_label"]')?.value || '',
      membership_level: form.querySelector('[name="membership_level"]').value,
    };

    updateUserProfile(data);
  });
});
