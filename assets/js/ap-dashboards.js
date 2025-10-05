(function () {
  const config = window.ArtPulseDashboards || {};
  const labels = config.labels || {};
  const strings = config.strings || {};

  function onReady(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  }

  function initAll() {
    document.querySelectorAll('.ap-role-dashboard[data-ap-dashboard-role]').forEach((container) => {
      initializeDashboard(container);
    });
  }

  function initializeDashboard(container) {
    if (!container || container.dataset.apDashboardBound === '1') {
      return;
    }

    container.dataset.apDashboardBound = '1';

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

  function init(target) {
    if (!target) {
      return;
    }

    if (target.nodeType === 1) {
      initializeDashboard(target);
      return;
    }

    if (typeof target.length === 'number') {
      Array.prototype.forEach.call(target, (item) => {
        init(item);
      });
    }
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

    if (role === 'member' && Array.isArray(data.upgrades) && data.upgrades.length) {
      grid.appendChild(buildUpgradeSection(data.upgrades, roleLabels.upgrades, data.upgrade_intro));
    }

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

  function buildUpgradeSection(upgrades = [], titleOverride, introText) {
    const section = createSection('upgrades', titleOverride || strings.upgrades || 'Membership Upgrades');

    if (!Array.isArray(upgrades) || upgrades.length === 0) {
      section.appendChild(createEmptyState());
      return section;
    }

    const introCopy = introText || strings.upgradeIntro;
    if (introCopy) {
      const intro = document.createElement('p');
      intro.className = 'ap-dashboard-upgrades__intro';
      intro.textContent = introCopy;
      section.appendChild(intro);
    }

    const list = document.createElement('div');
    list.className = 'ap-dashboard-upgrades';

    upgrades.forEach((upgrade) => {
      if (!upgrade || !upgrade.url) {
        return;
      }

      const card = document.createElement('article');
      card.className = 'ap-dashboard-card ap-dashboard-upgrade';

      const body = document.createElement('div');
      body.className = 'ap-dashboard-card__body ap-dashboard-upgrade__body';

      if (upgrade.title) {
        const title = document.createElement('h4');
        title.className = 'ap-dashboard-upgrade__title';
        title.textContent = upgrade.title;
        body.appendChild(title);
      }

      if (upgrade.description) {
        const desc = document.createElement('p');
        desc.className = 'ap-dashboard-upgrade__description';
        desc.textContent = upgrade.description;
        body.appendChild(desc);
      }

      card.appendChild(body);

      const actions = document.createElement('div');
      actions.className = 'ap-dashboard-card__actions';

      const link = document.createElement('a');
      link.className = 'ap-dashboard-button ap-dashboard-button--primary';
      link.href = upgrade.url;
      link.textContent = upgrade.cta || strings.upgradeCta || 'Upgrade now';

      actions.appendChild(link);
      card.appendChild(actions);

      list.appendChild(card);
    });

    if (!list.children.length) {
      section.appendChild(createEmptyState());
      return section;
    }

    section.appendChild(list);
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

        if (group.create_url) {
          const action = document.createElement('a');
          action.className = 'ap-dashboard-button ap-dashboard-button--primary';
          action.href = group.create_url;
          action.textContent = strings.createProfile || 'Create profile';
          wrapper.appendChild(action);
        }
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

    const actionNodes = Array.isArray(options.actions) ? options.actions.filter(Boolean) : [];

    if (item.edit_url) {
      const editLink = document.createElement('a');
      editLink.className = 'ap-dashboard-card__action ap-dashboard-card__action--edit';
      editLink.href = item.edit_url;
      editLink.textContent = strings.editProfile || 'Edit profile';
      actionNodes.push(editLink);
    }

    if (actionNodes.length) {
      const actions = document.createElement('div');
      actions.className = 'ap-dashboard-card__actions';
      actionNodes.forEach((action) => {
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
    btn.className = 'ap-favorite-btn is-active';
    btn.dataset.apFav = '1';
    btn.dataset.apObjectId = item.id;
    btn.dataset.apObjectType = item.object_type;
    btn.dataset.apActive = '1';
    btn.dataset.labelOn = strings.unfavorite || 'Unfavorite';
    btn.dataset.labelOff = strings.favorite || 'Favorite';
    btn.setAttribute('aria-pressed', 'true');
    btn.textContent = strings.unfavorite || 'Unfavorite';
    return btn;
  }

  function createFollowButton(item) {
    if (!item || !item.id || !item.post_type) {
      return null;
    }

    const isFollowing = !!item.following;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ap-follow-btn';
    btn.dataset.apFollow = '1';
    btn.dataset.apObjectId = item.id;
    btn.dataset.apObjectType = item.post_type;
    btn.dataset.apActive = isFollowing ? '1' : '0';
    btn.dataset.labelOn = strings.unfollow || 'Unfollow';
    btn.dataset.labelOff = strings.follow || 'Follow';
    btn.setAttribute('aria-pressed', isFollowing ? 'true' : 'false');
    btn.classList.toggle('is-following', isFollowing);
    btn.textContent = isFollowing ? (strings.unfollow || 'Unfollow') : (strings.follow || 'Follow');
    return btn;
  }

  function hydrateFavoriteButtons(scope) {
    if (bindSocialButtons(scope)) {
      return;
    }

    scope.querySelectorAll('[data-ap-fav]').forEach((btn) => {
      if (btn.dataset.apFavoriteBound === '1') {
        return;
      }
      btn.dataset.apFavoriteBound = '1';
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        const objectId = btn.dataset.apObjectId;
        const objectType = btn.dataset.apObjectType;
        if (!objectId || !objectType) {
          return;
        }
        const isActive = btn.dataset.apActive === '1' || btn.classList.contains('is-active');
        toggleFavorite(objectId, objectType, isActive)
          .then((response) => {
            if (response && response.status) {
              const nowActive = response.status === 'favorited';
              btn.dataset.apActive = nowActive ? '1' : '0';
              btn.classList.toggle('is-active', nowActive);
              btn.classList.toggle('active', nowActive);
              btn.textContent = nowActive ? (strings.unfavorite || 'Unfavorite') : (strings.favorite || 'Favorite');
            }
          })
          .catch(() => {});
      });
    });
  }

  function bindFollowButtons(scope) {
    if (bindSocialButtons(scope)) {
      return;
    }

    scope.querySelectorAll('[data-ap-follow]').forEach((btn) => {
      if (btn.dataset.apFollowBound === '1') {
        return;
      }
      btn.dataset.apFollowBound = '1';
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        const postId = btn.dataset.apObjectId;
        const postType = btn.dataset.apObjectType;
        if (!postId || !postType) {
          return;
        }
        const following = btn.dataset.apActive === '1';
        toggleFollow(postId, postType, following)
          .then((response) => {
            if (!response) {
              return;
            }
            const isFollowing = response.status === 'following';
            btn.dataset.apActive = isFollowing ? '1' : '0';
            btn.classList.toggle('is-following', isFollowing);
            btn.textContent = isFollowing ? (strings.unfollow || 'Unfollow') : (strings.follow || 'Follow');
          })
          .catch(() => {});
      });
    });
  }

  function bindSocialButtons(scope) {
    if (window.APSocial && typeof window.APSocial.bind === 'function') {
      window.APSocial.bind(scope);
      return true;
    }
    return false;
  }

  function toggleFavorite(objectId, objectType, isActive) {
    const payload = {
      object_id: parseInt(objectId, 10),
      object_type: objectType,
      action: isActive ? 'remove' : 'add',
    };

    const path = isActive ? 'artpulse/v1/favorites/remove' : 'artpulse/v1/favorites/add';

    return fetch(joinPath(apiRoot(), path), {
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

    return fetch(joinPath(apiRoot(), 'artpulse/v1/follows'), {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': apiNonce(),
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
    if (window.APSocial && window.APSocial.root) {
      return window.APSocial.root;
    }
    return (window.ArtPulseApi && ArtPulseApi.root) || config.root;
  }

  function apiNonce() {
    if (window.APSocial && window.APSocial.nonce) {
      return window.APSocial.nonce;
    }
    return (window.ArtPulseApi && ArtPulseApi.nonce) || config.nonce;
  }

  const app = {
    initAll,
    init,
  };

  onReady(initAll);

  window.ArtPulseDashboardsApp = Object.assign(window.ArtPulseDashboardsApp || {}, app);
})();
