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
        bindUpgradeRequestButtons(container);
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

    const roleSwitcher = buildRoleSwitcher(role, data.available_roles);
    if (roleSwitcher) {
      header.appendChild(roleSwitcher);
      header.classList.add('ap-dashboard-header--has-switcher');
    }

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

  function buildRoleSwitcher(currentRole, availableRoles = []) {
    if (!Array.isArray(availableRoles)) {
      return null;
    }

    const roles = availableRoles.filter((item) => item && item.role);

    if (roles.length <= 1) {
      return null;
    }

    const nav = document.createElement('nav');
    nav.className = 'ap-dashboard-role-switcher';
    nav.setAttribute('aria-label', strings.roleSwitcherLabel || 'Switch dashboards');

    const list = document.createElement('ul');
    list.className = 'ap-dashboard-role-switcher__list';

    roles.forEach((role) => {
      const item = document.createElement('li');
      item.className = 'ap-dashboard-role-switcher__item';

      const label = role.label || roleTitlesFallback(role.role);
      const isCurrent = Boolean(role.current) || role.role === currentRole;

      if (isCurrent) {
        item.classList.add('is-active');
        const span = document.createElement('span');
        span.className = 'ap-dashboard-role-switcher__link is-current';
        span.textContent = label;
        span.setAttribute('aria-current', 'page');

        if (strings.currentRoleLabel) {
          span.setAttribute('title', strings.currentRoleLabel);
        }

        item.appendChild(span);
      } else {
        const link = document.createElement('a');
        link.className = 'ap-dashboard-role-switcher__link';
        link.href = role.url || `?role=${encodeURIComponent(role.role)}`;
        link.textContent = label;
        link.setAttribute('data-role', role.role);
        item.appendChild(link);
      }

      list.appendChild(item);
    });

    nav.appendChild(list);

    return nav;
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
      intro.className = 'ap-upgrade-widget__intro';
      intro.textContent = introCopy;
      section.appendChild(intro);
    }

    const list = document.createElement('div');
    list.className = 'ap-upgrade-widget__list';

    upgrades.forEach((upgrade) => {
      if (!upgrade || !upgrade.url) {
        return;
      }

      const card = document.createElement('article');
      card.className = 'ap-dashboard-card ap-upgrade-widget__card';

      const body = document.createElement('div');
      body.className = 'ap-dashboard-card__body ap-upgrade-widget__card-body';

      if (upgrade.title) {
        const title = document.createElement('h4');
        title.className = 'ap-upgrade-widget__card-title';
        title.textContent = upgrade.title;
        body.appendChild(title);
      }

      if (upgrade.description) {
        const desc = document.createElement('p');
        desc.className = 'ap-upgrade-widget__card-description';
        desc.textContent = upgrade.description;
        body.appendChild(desc);
      }

      card.appendChild(body);

      const actions = document.createElement('div');
      actions.className = 'ap-dashboard-card__actions ap-upgrade-widget__card-actions';

      const link = document.createElement('a');
      link.className = 'ap-dashboard-button ap-dashboard-button--primary ap-upgrade-widget__cta';
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
    let headingClass = '';

    if (slug === 'upgrades') {
      section.classList.add('ap-upgrade-widget', 'ap-upgrade-widget--inline');
      headingClass = ' class="ap-upgrade-widget__heading"';
    }

    section.innerHTML = `<h3${headingClass}>${escapeHtml(title || '')}</h3>`;
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

  function bindUpgradeRequestButtons(scope) {
    if (!scope) {
      return;
    }

    const root = scope.querySelectorAll ? scope : document;

    root.querySelectorAll('[data-ap-upgrade]').forEach((btn) => {
      if (!btn || btn.dataset.apUpgradeBound === '1') {
        return;
      }

      btn.dataset.apUpgradeBound = '1';

      btn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        const type = (btn.dataset.apUpgrade || '').toLowerCase();
        if (!type || !['artist', 'organization'].includes(type)) {
          return;
        }

        if (btn.dataset.apUpgradeBusy === '1') {
          return;
        }

        clearUpgradeError(btn);
        disableUpgradeButton(btn);

        submitUpgradeRequest(type, btn)
          .then((response) => {
            renderUpgradePendingState(btn, response, type);
            dispatchUpgradeRequested(btn, response, type);
          })
          .catch((error) => {
            enableUpgradeButton(btn);
            renderUpgradeError(btn, error);
          });
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

  function submitUpgradeRequest(type, button) {
    const payload = buildUpgradePayload(type, button);

    if (window.wp && window.wp.apiFetch) {
      return window.wp.apiFetch({
        path: '/artpulse/v1/reviews',
        method: 'POST',
        headers: {
          'X-WP-Nonce': apiNonce(),
        },
        data: payload,
      });
    }

    return fetch(joinPath(apiRoot(), 'artpulse/v1/reviews'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': apiNonce(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    }).then((response) => {
      if (!response.ok) {
        return response
          .json()
          .catch(() => ({}))
          .then((error) => Promise.reject(error));
      }
      return response.json();
    });
  }

  function buildUpgradePayload(type, button) {
    const payload = { type };

    if (button && button.dataset && button.dataset.apUpgradePostId) {
      const parsed = parseInt(button.dataset.apUpgradePostId, 10);
      if (!Number.isNaN(parsed) && parsed > 0) {
        payload.postId = parsed;
      }
    }

    return payload;
  }

  function disableUpgradeButton(button) {
    button.dataset.apUpgradeBusy = '1';
    button.classList.add('is-disabled');
    button.setAttribute('aria-disabled', 'true');

    if (button.tagName === 'BUTTON') {
      button.disabled = true;
    }
  }

  function enableUpgradeButton(button) {
    delete button.dataset.apUpgradeBusy;
    button.classList.remove('is-disabled');
    button.removeAttribute('aria-disabled');

    if (button.tagName === 'BUTTON') {
      button.disabled = false;
    }
  }

  function renderUpgradePendingState(button, response, requestedType) {
    const card = findUpgradeCard(button);
    const existing = findUpgradeStatus(card);
    const statusElements = existing || createUpgradeStatusElements();
    const texts = getPendingTexts(button, card, response, requestedType);

    if (statusElements.badge) {
      statusElements.badge.textContent = texts.badge;
    }

    if (statusElements.message) {
      statusElements.message.textContent = texts.message;
    }

    statusElements.container.dataset.apUpgradeStatus = (response && response.status) || 'pending';
    statusElements.container.dataset.apUpgradeType = requestedType;

    clearUpgradeError(button);

    const form = button.closest('form');
    const actions = findUpgradeActionsContainer(button);

    if (!existing) {
      if (form && form.parentNode) {
        form.parentNode.replaceChild(statusElements.container, form);
      } else if (actions) {
        actions.innerHTML = '';
        actions.appendChild(statusElements.container);
      } else {
        button.replaceWith(statusElements.container);
      }
    } else {
      if (form && form.parentNode) {
        form.parentNode.removeChild(form);
      } else if (actions && actions.contains(button)) {
        actions.removeChild(button);
      }
    }
  }

  function renderUpgradeError(button, error) {
    const card = findUpgradeCard(button);
    const message = normalizeErrorMessage(error);

    let container = card ? card.querySelector('[data-ap-upgrade-error]') : null;

    if (!container) {
      container = document.createElement('div');
      container.className = 'ap-dashboard-error';
      container.setAttribute('data-ap-upgrade-error', '1');
      container.setAttribute('role', 'status');
      container.setAttribute('aria-live', 'polite');

      const actions = findUpgradeActionsContainer(button);
      if (actions && actions.parentNode) {
        actions.parentNode.insertBefore(container, actions.nextSibling);
      } else if (card) {
        card.appendChild(container);
      } else {
        button.insertAdjacentElement('afterend', container);
      }
    }

    container.textContent = message;
  }

  function clearUpgradeError(button) {
    const card = findUpgradeCard(button);
    const errors = card ? card.querySelectorAll('[data-ap-upgrade-error]') : [];

    if (errors && typeof errors.forEach === 'function') {
      errors.forEach((node) => {
        if (node && node.parentNode) {
          node.parentNode.removeChild(node);
        }
      });
    }
  }

  function dispatchUpgradeRequested(button, response, requestedType) {
    const card = findUpgradeCard(button);
    const target = card || button;

    const detail = {
      type: (response && response.type) || requestedType,
      status: (response && response.status) || 'pending',
    };

    if (response && typeof response.id !== 'undefined') {
      detail.id = response.id;
    }

    if (response && typeof response.postId !== 'undefined') {
      detail.postId = response.postId;
    }

    detail.response = response || null;

    const event = new CustomEvent('ap:upgrade:requested', {
      bubbles: true,
      detail,
    });

    target.dispatchEvent(event);
  }

  function findUpgradeCard(button) {
    if (!button || !button.closest) {
      return null;
    }

    return (
      button.closest('[data-ap-upgrade-card]') ||
      button.closest('.ap-dashboard-journey') ||
      button.closest('.ap-dashboard-card') ||
      null
    );
  }

  function findUpgradeActionsContainer(button) {
    if (!button || !button.closest) {
      return null;
    }

    return (
      button.closest('[data-ap-upgrade-actions]') ||
      button.closest('.ap-dashboard-journey__actions') ||
      button.closest('.ap-dashboard-card__actions') ||
      null
    );
  }

  function findUpgradeStatus(card) {
    if (!card || !card.querySelector) {
      return null;
    }

    const container = card.querySelector('[data-ap-upgrade-status]');

    if (!container) {
      return null;
    }

    return {
      container,
      badge:
        container.querySelector('[data-ap-upgrade-badge]') ||
        container.querySelector('.ap-dashboard-badge'),
      message:
        container.querySelector('[data-ap-upgrade-message]') ||
        container.querySelector('p') ||
        null,
    };
  }

  function createUpgradeStatusElements() {
    const container = document.createElement('div');
    container.className = 'ap-upgrade-status';
    container.setAttribute('data-ap-upgrade-status', 'pending');
    container.setAttribute('aria-live', 'polite');

    const badge = document.createElement('span');
    badge.className = 'ap-dashboard-badge ap-dashboard-badge--info';
    badge.setAttribute('data-ap-upgrade-badge', '1');
    badge.textContent = strings.upgradePendingBadge || 'Pending';

    const message = document.createElement('p');
    message.className = 'ap-upgrade-status__message';
    message.setAttribute('data-ap-upgrade-message', '1');
    message.textContent = strings.upgradePendingMessage ||
      'Your request is pending review. We will email you when a moderator responds.';

    container.appendChild(badge);
    container.appendChild(message);

    return { container, badge, message };
  }

  function getPendingTexts(button, card, response, requestedType) {
    const badge =
      (card && card.dataset && card.dataset.apUpgradePendingLabel) ||
      (button && button.dataset && button.dataset.apUpgradePendingLabel) ||
      (response && response.badge) ||
      strings.upgradePendingBadge ||
      'Pending';

    const message =
      (card && card.dataset && card.dataset.apUpgradePendingMessage) ||
      (button && button.dataset && button.dataset.apUpgradePendingMessage) ||
      (response && response.message) ||
      strings.upgradePendingMessage ||
      'Your request is pending review. We will email you when a moderator responds.';

    return {
      badge,
      message,
      type: requestedType,
    };
  }

  function normalizeErrorMessage(error) {
    if (error) {
      if (typeof error.message === 'string' && error.message.trim() !== '') {
        return error.message.trim();
      }

      if (typeof error === 'string' && error.trim() !== '') {
        return error.trim();
      }

      if (error.data && typeof error.data.message === 'string' && error.data.message.trim() !== '') {
        return error.data.message.trim();
      }
    }

    return strings.upgradeError || 'Unable to submit your request. Please try again.';
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
