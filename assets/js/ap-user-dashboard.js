document.addEventListener('DOMContentLoaded', () => {
  const dash = document.querySelector('.ap-user-dashboard');
  if (!dash) return;

  fetch(`${ArtPulseDashboardApi.root}artpulse/v1/user/dashboard`, {
    headers: { 'X-WP-Nonce': ArtPulseDashboardApi.nonce }
  })
  .then(res => res.json())
  .then(data => {
    // Membership
    const info = document.getElementById('ap-membership-info');
    info.innerHTML = `<p>${apL10n.membership_level}: ${data.membership_level}</p>
                      <p>${apL10n.expires}: ${data.membership_expires ? new Date(data.membership_expires * 1000).toLocaleDateString() : apL10n.never}</p>`;

    // Content
    const content = document.getElementById('ap-user-content');
    ['events','artists','artworks'].forEach(type => {
      if (data[type].length) {
        const ul = document.createElement('ul');
        data[type].forEach(item => {
          const li = document.createElement('li');
          li.innerHTML = `<a href="${item.link}">${item.title}</a>`;
          ul.appendChild(li);
        });
        content.appendChild(document.createElement('h3')).textContent = apL10n[type];
        content.appendChild(ul);
      }
    });
  });
});
