(function () {
  const config = window.ArtPulseDashboards || {};
  const labels = config.labels || {};
  const strings = config.strings || {};

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ap-role-dashboard[data-ap-dashboard-role]').forEach((container) => {
      initializeDashboard(container);
    });
  });

  function initializeDashboard(container) {
    const role = container.dataset.apDashboardRole;
    if (!role) {
      return;
    }

    container.classList.add('is-loading');
    container.innerHTML = `<div class="ap-dashboard-loading">${escapeHtml(strings.loading || 'Loading…')}</div>`;

    fetchDashboard(role)
      .then((data) => {
        renderDashboard(container, role, data);
        hydrateFavoriteButtons(container);
        bindFollowButtons(container);
      })
      .catch(() => {
        container.innerHTML = `<div class="ap-dashboard-error">${escapeHtml(strings.error || 'Unable to load dashboard data.')}</div>`;
      })
      .finally(() => {
        container.classList.remove('is-loading');
      });
  }

  function fetchDashboard(role) {
    const path = `/artpulse/v1/dashboard?role=${encodeURIComponent(role)}`;

    if (window.wp && window.wp.apiFetch) {
      return window.wp.apiFetch({
        path,
        headers: { 'X-WP-Nonce': config.nonce },
      });
    }

    return fetch(joinPath(config.root, path), {
      headers: { 'X-WP-Nonce': config.nonce },
      credentials: 'same-origin',
    }).then((response) => {
      if (!response.ok) {
        throw new Error('Failed request');
      }
      return response.json();
    });
  }

  function renderDashboard(container, role, data) {
    container.innerHTML = '';
    container.classList.add('ap-role-dashboard--ready');

    const roleLabels = labels[role] || {};
    const headingText = roleLabels.title || roleTitlesFallback(role, data);

    const header = document.createElement('header');
    header.className = 'ap-dashboard-header';
    header.innerHTML = `<h2>${escapeHtml(headingText)}</h2>`;
    container.appendChild(header);

    const grid = document.createElement('div');
    grid.className = 'ap-dashboard-grid';

    grid.appendChild(buildProfileSection(data.profile, roleLabels.profile));
    grid.appendChild(buildMetricsSection(data.metrics, roleLabels.metrics));
    grid.appendChild(buildSubmissionsSection(data.submissions, roleLabels.submissions));
    grid.appendChild(buildFavoritesSection(data.favorites, roleLabels.favorites));
    grid.appendChild(buildFollowsSection(data.follows, roleLabels.follows));

    container.appendChild(grid);
  }

  function buildProfileSection(profile = {}, titleOverride) {
    const section = createSection('profile', titleOverride || strings.profile || 'Profile Summary');

    if (!profile || !profile.id) {
      section.appendChild(createEmptyState());
      return section;
    }

    const card = document.createElement('div');
    card.className = 'ap-dashboard-card ap-dashboard-profile';

    card.innerHTML = `
      <div class="ap-dashboard-profile__header">
        ${profile.avatar ? `<img class="ap-dashboard-profile__avatar" src="${escapeAttribute(profile.avatar)}" alt="${escapeAttribute(profile.display_name || '')}">` : ''}
        <div>
          <h3>${escapeHtml(profile.display_name || '')}</h3>
          ${profile.email ? `<p class="ap-dashboard-profile__email">${escapeHtml(profile.email)}</p>` : ''}
          ${profile.membership && profile.membership.level ? `<p class="ap-dashboard-profile__membership"><strong>${escapeHtml(profile.membership.level)}</strong></p>` : ''}
          ${profile.membership && profile.membership.expires_display ? `<p class="ap-dashboard-profile__expires">${escapeHtml(strings.updated || 'Updated')}: ${escapeHtml(profile.membership.expires_display)}</p>` : ''}
          ${profile.profile_url ? `<p><a class="ap-dashboard-profile__link" href="${escapeAttribute(profile.profile_url)}">${escapeHtml(strings.viewProfile || 'View profile')}</a></p>` : ''}
        </div>
      </div>
      ${profile.bio ? `<p class="ap-dashboard-profile__bio">${escapeHtml(profile.bio)}</p>` : ''}
    `;

    section.appendChild(card);
    return section;
  }

  function buildMetricsSection(metrics = {}, titleOverride) {
    const section = createSection('metrics', titleOverride || strings.metrics || 'Metrics');

    const entries = [
      { key: 'favorites', label: strings.favoritesMetric || 'Favorites' },
      { key: 'follows', label: strings.followsMetric || 'Follows' },
      { key: 'submissions', label: strings.submissionsMetric || 'Submissions' },
      { key: 'pending_submissions', label: strings.pendingMetric || 'Pending' },
      { key: 'published_submissions', label: strings.publishedMetric || 'Published' },
    ];

    const list = document.createElement('ul');
    list.className = 'ap-dashboard-metrics';

    let hasValue = false;
    entries.forEach((entry) => {
      const value = typeof metrics[entry.key] === 'number' ? metrics[entry.key] : 0;
      if (value > 0) {
        hasValue = true;
      }
      const li = document.createElement('li');
      li.innerHTML = `<strong>${escapeHtml(String(value))}</strong><span>${escapeHtml(entry.label)}</span>`;
      list.appendChild(li);
    });

    if (!hasValue) {
      section.appendChild(createEmptyState());
    } else {
      section.appendChild(list);
    }

    return section;
  }

  function buildSubmissionsSection(submissions = {}, titleOverride) {
    const section = createSection('submissions', titleOverride || strings.submissions || 'Submissions');
    const types = Object.values(submissions || {});

    if (!types.length) {
      section.appendChild(createEmptyState());
      return section;
    }

    types.forEach((group) => {
      const wrapper = document.createElement('div');
      wrapper.className = 'ap-dashboard-submission-group';
      const items = Array.isArray(group.items) ? group.items : [];
      const title = `${group.label || strings.submissions || 'Submissions'} (${items.length})`;
      wrapper.innerHTML = `<h4>${escapeHtml(title)}</h4>`;

      if (!items.length) {
        wrapper.appendChild(createEmptyState());
      } else {
        const list = document.createElement('ul');
        list.className = 'ap-dashboard-list';
        items.forEach((item) => {
          list.appendChild(buildListItem(item));
        });
        wrapper.appendChild(list);
      }

      section.appendChild(wrapper);
    });

    return section;
  }

  function buildFavoritesSection(favorites = [], titleOverride) {
    const section = createSection('favorites', titleOverride || strings.favorites || 'Favorites');

    if (!favorites.length) {
      section.appendChild(createEmptyState());
      return section;
    }

    const list = document.createElement('ul');
    list.className = 'ap-dashboard-list';

    favorites.forEach((favorite) => {
      const item = buildListItem(favorite, {
        meta: favorite.favorited_on ? formatDate(favorite.favorited_on) : '',
        actions: [createFavoriteButton(favorite)],
      });
      list.appendChild(item);
    });

    section.appendChild(list);
    return section;
  }

  function buildFollowsSection(follows = [], titleOverride) {
    const section = createSection('follows', titleOverride || strings.follows || 'Follows');

    if (!follows.length) {
      section.appendChild(createEmptyState());
      return section;
    }

    const list = document.createElement('ul');
    list.className = 'ap-dashboard-list';

    follows.forEach((follow) => {
      const item = buildListItem(follow, {
        meta: follow.followed_on ? formatDate(follow.followed_on) : '',
        actions: [createFollowButton(follow)],
      });
      list.appendChild(item);
    });

    section.appendChild(list);
    return section;
  }

  function createSection(slug, title) {
    const section = document.createElement('section');
    section.className = `ap-dashboard-section ap-dashboard-section--${slug}`;
    section.innerHTML = `<h3>${escapeHtml(title || '')}</h3>`;
    return section;
  }

  function buildListItem(item = {}, options = {}) {
    const li = document.createElement('li');
    li.className = 'ap-dashboard-card ap-dashboard-card--list';

    const title = item.title || '';
    const typeLabel = item.type_label || '';
    const statusText = item.status ? ` • ${escapeHtml(item.status)}` : '';
    const meta = options.meta ? `<div class="ap-dashboard-card__meta">${escapeHtml(options.meta)}</div>` : '';
    const thumbnail = item.thumbnail ? `<img class="ap-dashboard-card__thumb" src="${escapeAttribute(item.thumbnail)}" alt="${escapeAttribute(title)}">` : '';

    li.innerHTML = `
      <div class="ap-dashboard-card__body">
        ${thumbnail}
        <div>
          <h4>${item.permalink ? `<a href="${escapeAttribute(item.permalink)}">${escapeHtml(title)}</a>` : escapeHtml(title)}</h4>
          ${typeLabel ? `<div class="ap-dashboard-card__type">${escapeHtml(typeLabel)}${statusText}</div>` : ''}
          ${meta}
        </div>
      </div>
    `;

    if (options.actions && options.actions.length) {
      const actions = document.createElement('div');
      actions.className = 'ap-dashboard-card__actions';
      options.actions.forEach((action) => {
        if (action) {
          actions.appendChild(action);
        }
      });
      li.appendChild(actions);
    }

    return li;
  }

  function createFavoriteButton(item) {
    if (!item || !item.id || !item.object_type) {
      return null;
    }

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ap-favorite-btn active';
    btn.dataset.objectId = item.id;
    btn.dataset.objectType = item.object_type;
    btn.textContent = strings.unfavorite || 'Unfavorite';
    return btn;
  }

  function createFollowButton(item) {
    if (!item || !item.id || !item.post_type) {
      return null;
    }

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ap-follow-btn';
    btn.dataset.postId = item.id;
    btn.dataset.postType = item.post_type;
    btn.dataset.following = item.following ? '1' : '0';
    btn.textContent = item.following ? (strings.unfollow || 'Unfollow') : (strings.follow || 'Follow');
    return btn;
  }

  function hydrateFavoriteButtons(scope) {
    scope.querySelectorAll('.ap-favorite-btn').forEach((btn) => {
      if (btn.dataset.apFavoriteBound === '1') {
        return;
      }
      btn.dataset.apFavoriteBound = '1';
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        const objectId = btn.dataset.objectId;
        const objectType = btn.dataset.objectType;
        if (!objectId || !objectType) {
          return;
        }
        const isActive = btn.classList.contains('active');
        toggleFavorite(objectId, objectType, isActive)
          .then((response) => {
            if (response && response.success) {
              btn.classList.toggle('active');
              const nowActive = btn.classList.contains('active');
              btn.textContent = nowActive ? (strings.unfavorite || 'Unfavorite') : (strings.favorite || 'Favorite');
            }
          })
          .catch(() => {});
      });
    });
  }

  function bindFollowButtons(scope) {
    scope.querySelectorAll('.ap-follow-btn').forEach((btn) => {
      if (btn.dataset.apFollowBound === '1') {
        return;
      }
      btn.dataset.apFollowBound = '1';
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        const postId = btn.dataset.postId;
        const postType = btn.dataset.postType;
        if (!postId || !postType) {
          return;
        }
        const following = btn.dataset.following === '1';
        toggleFollow(postId, postType, following)
          .then((response) => {
            if (!response) {
              return;
            }
            const isFollowing = response.status === 'following';
            btn.dataset.following = isFollowing ? '1' : '0';
            btn.textContent = isFollowing ? (strings.unfollow || 'Unfollow') : (strings.follow || 'Follow');
          })
          .catch(() => {});
      });
    });
  }

  function toggleFavorite(objectId, objectType, isActive) {
    const payload = {
      object_id: parseInt(objectId, 10),
      object_type: objectType,
      action: isActive ? 'remove' : 'add',
    };

    return fetch(joinPath(apiRoot(), 'artpulse/v1/favorite'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': apiNonce(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    }).then((response) => response.json());
  }

  function toggleFollow(postId, postType, isFollowing) {
    const payload = {
      post_id: parseInt(postId, 10),
      post_type: postType,
    };

    const method = isFollowing ? 'DELETE' : 'POST';

    return fetch(joinPath(config.root, 'artpulse/v1/follows'), {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce,
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    }).then((response) => response.json());
  }

  function roleTitlesFallback(role, data) {
    if (data && data.profile && data.profile.display_name) {
      return `${data.profile.display_name}`;
    }
    return role.charAt(0).toUpperCase() + role.slice(1);
  }

  function createEmptyState() {
    const div = document.createElement('div');
    div.className = 'ap-dashboard-empty';
    div.textContent = strings.empty || 'Nothing to display yet.';
    return div;
  }

  function formatDate(value) {
    try {
      const date = new Date(value);
      if (!Number.isNaN(date.getTime())) {
        return date.toLocaleDateString();
      }
    } catch (e) {
      /* noop */
    }
    return value;
  }

  function joinPath(root, path) {
    if (!root) {
      return path;
    }
    return `${root.replace(/\/$/, '')}/${path.replace(/^\//, '')}`;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeAttribute(value) {
    return escapeHtml(value);
  }

  function apiRoot() {
    return (window.ArtPulseApi && ArtPulseApi.root) || config.root;
  }

  function apiNonce() {
    return (window.ArtPulseApi && ArtPulseApi.nonce) || config.nonce;
  }
})();
