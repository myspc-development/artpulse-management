(function(){
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.ap-my-follows').forEach(initFollows);
  });

  function initFollows(container) {
    const results = container.querySelector('.ap-directory-results');
    const filterType = container.querySelector('#ap-my-follows-type');

    function loadFollows() {
      results.innerHTML = '<div class="ap-loading">Loading…</div>';
      const params = new URLSearchParams();
      if (filterType && filterType.value) params.append('object_type', filterType.value);

      wp.apiFetch({
        path: '/artpulse/v1/follows?' + params.toString(),
        headers: { 'X-WP-Nonce': ArtPulseFollowsApi.nonce }
      }).then(follows => {
        results.innerHTML = '';
        if (!follows.length) {
          results.innerHTML = '<div class="ap-empty">You are not following anything yet.</div>';
          return;
        }
        follows.forEach(follow => {
          const div = document.createElement('div');
          div.className = 'portfolio-item';
          div.innerHTML = `
            <a href="${follow.permalink}">
              ${follow.featured_media_url ? `<img src="${follow.featured_media_url}" alt="${follow.title}" />` : ''}
              <h3>${follow.title}</h3>
            </a>
            <div>
              <small>Type: ${follow.object_type} &mdash; Followed: ${follow.followed_on}</small>
              <button class="ap-unfollow-btn" data-oid="${follow.object_id}" data-otype="${follow.object_type}">Unfollow</button>
            </div>
          `;
          results.appendChild(div);
        });
      }).catch(() => {
        results.innerHTML = '<div class="ap-error">Failed to load your follows.</div>';
      });
    }

    // Bind filter dropdown
    if (filterType) {
      filterType.addEventListener('change', loadFollows);
    }

    // Delegate unfollow buttons
    results.addEventListener('click', function(e){
      if (e.target.classList.contains('ap-unfollow-btn')) {
        const oid = e.target.dataset.oid;
        const otype = e.target.dataset.otype;
        wp.apiFetch({
          path: '/artpulse/v1/follow',
          method: 'POST',
          data: {
            object_id: oid,
            object_type: otype,
            action: 'unfollow'
          },
          headers: { 'X-WP-Nonce': ArtPulseFollowsApi.nonce }
        }).then(() => {
          e.target.closest('.portfolio-item').remove();
        });
      }
    });

    loadFollows();
  }
})();
