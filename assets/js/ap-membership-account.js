(function(){
  document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.ap-membership-account');
    if (!container) return;

    const infoDiv    = container.querySelector('#ap-account-info');
    const actionsDiv = container.querySelector('#ap-account-actions');

    // Fetch membership data
    wp.apiFetch({
      path: '/artpulse/v1/member/account',
      headers: { 'X-WP-Nonce': ArtPulseApi.nonce }
    })
    .then(data => {
      infoDiv.innerHTML = `
        <p><strong>${ArtPulseApi.i18n.levelLabel}:</strong> ${data.level}</p>
        <p><strong>${ArtPulseApi.i18n.expiresLabel}:</strong> ${data.expires}</p>
      `;
      const levels = ['Basic','Pro','Org'];
      levels.forEach(lvl => {
        if ( lvl !== data.level ) {
          const wrapper = document.createElement('div');
          wrapper.innerHTML = data.purchase_links[lvl];
          actionsDiv.appendChild(wrapper);
        }
      });
    })
    .catch(() => {
      infoDiv.textContent = ArtPulseApi.i18n.errorFetching;
    });

    // Notification logic
    const listEl = document.querySelector('#ap-notification-list');
    const countEl = document.querySelector('#ap-notification-count');
    const markAllBtn = document.querySelector('#ap-notification-mark-all');

    if (listEl) {
      function renderNotifications(data) {
        listEl.innerHTML = '';
        let unreadCount = 0;

        if (!data || !data.length) {
          listEl.innerHTML = '<li>No notifications.</li>';
          countEl && (countEl.textContent = '');
          return;
        }

        data.forEach(notif => {
          const li = document.createElement('li');
          li.textContent = notif.content || notif.type;
          if (notif.status !== 'read') {
            unreadCount++;
            const btn = document.createElement('button');
            btn.textContent = 'Mark as read';
            btn.onclick = () => markAsRead(notif.id, li);
            li.append(' ', btn);
          }
          listEl.appendChild(li);
        });

        if (countEl) {
          countEl.textContent = unreadCount > 0 ? `(${unreadCount})` : '';
        }
      }

      function fetchNotifications() {
        wp.apiFetch({
          path: '/artpulse/v1/notifications',
          headers: { 'X-WP-Nonce': ArtPulseApi.nonce }
        }).then(renderNotifications);
      }

      function markAsRead(id, el) {
        wp.apiFetch({
          path: `/artpulse/v1/notifications/${id}/read`,
          method: 'POST',
          headers: { 'X-WP-Nonce': ArtPulseApi.nonce }
        }).then(() => {
          el.remove();
          fetchNotifications();
        });
      }

      if (markAllBtn) {
        markAllBtn.addEventListener('click', () => {
          wp.apiFetch({
            path: `/artpulse/v1/notifications/mark-all-read`,
            method: 'POST',
            headers: { 'X-WP-Nonce': ArtPulseApi.nonce }
          }).then(() => {
            fetchNotifications();
          });
        });
      }

      fetchNotifications();
    }
  });
})();
