(function () {
  const config = window.ArtPulseDashboards || {};
  const labels = config.labels || {};
  const strings = config.strings || {};
  const supportUrl = config.supportUrl || '';
  const ORG_REQUEST_STATE_KEY = 'apOrgRequestStatus';
  const ORG_REQUEST_DISMISSED_KEY = 'apOrgRequestStatusDismissed';
  let orgRequestHistoryCache = null;
  let orgRequestHistoryPromise = null;
  let orgRequestModal = null;
  let orgRequestModalContent = null;
  let orgRequestModalClose = null;
  let orgRequestLastFocused = null;
  let sessionStorageSupported = null;

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
      card.setAttribute('data-ap-upgrade-card', '1');

      const review = upgrade && typeof upgrade.review === 'object' ? upgrade.review || {} : {};
      const reviewId = parseInt(
        review.id || review.request_id || upgrade.review_id || upgrade.request_id || 0,
        10
      );
      if (reviewId) {
        card.dataset.apUpgradeReview = String(reviewId);
      }

      const stateValue = String(review.status || upgrade.status || '').toLowerCase();
      const state = ['pending', 'approved', 'denied'].includes(stateValue) ? stateValue : '';
      if (state) {
        card.dataset.apUpgradeStatus = state;
      }

      const roleLabel = resolveUpgradeRoleLabel(upgrade);
      if (roleLabel) {
        card.dataset.apUpgradeRole = roleLabel;
      }

      const reason = state === 'denied' ? String(review.reason || upgrade.denial_reason || upgrade.reason || '').trim() : '';
      const statusLabel = state ? getStatusLabel(state) : '';
      const statusMessage = state ? getStatusMessage(state, roleLabel) : '';

      if (statusLabel) {
        card.dataset.apUpgradeStatusLabel = statusLabel;
      }
      if (statusMessage) {
        card.dataset.apUpgradeStatusMessage = statusMessage;
      }

      const body = document.createElement('div');
      body.className = 'ap-dashboard-card__body ap-upgrade-widget__card-body';

      const statusRegion = document.createElement('div');
      statusRegion.className = 'ap-upgrade-status';
      statusRegion.setAttribute('data-ap-upgrade-status', '');
      statusRegion.setAttribute('aria-live', 'polite');
      statusRegion.setAttribute('role', 'status');
      statusRegion.setAttribute('aria-atomic', 'true');
      statusRegion.setAttribute('tabindex', '-1');
      body.appendChild(statusRegion);

      if (state) {
        renderStatus(
          statusRegion,
          ensureStatusDetails(
            {
              state,
              reason,
              reviewId: reviewId || undefined,
              label: statusLabel,
              message: statusMessage,
              role: roleLabel,
            },
            card
          )
        );
      }

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
      actions.setAttribute('data-ap-upgrade-actions', '1');

      const link = document.createElement('a');
      link.className = 'ap-dashboard-button ap-dashboard-button--primary ap-upgrade-widget__cta';
      link.href = upgrade.url;
      link.textContent = upgrade.cta || strings.upgradeCta || 'Upgrade now';
      const primaryAria = buildPrimaryAriaLabel(roleLabel, link.textContent);
      if (primaryAria) {
        link.setAttribute('aria-label', primaryAria);
      }
      actions.appendChild(link);

      if (state === 'denied' && reviewId) {
        const reopenButton = document.createElement('button');
        reopenButton.type = 'button';
        reopenButton.className = 'button button-secondary ap-upgrade-widget__reopen-button';
        reopenButton.setAttribute('data-ap-upgrade-reopen', '1');
        reopenButton.setAttribute('data-id', String(reviewId));
        reopenButton.textContent = strings.upgradeReopen || 'Re-request review';
        const reopenAria = buildReopenAriaLabel(roleLabel, reopenButton.textContent);
        if (reopenAria) {
          reopenButton.setAttribute('aria-label', reopenAria);
        }
        actions.appendChild(reopenButton);
      }

      if (Array.isArray(upgrade.secondary_actions)) {
        upgrade.secondary_actions.forEach((secondary) => {
          if (!secondary || !secondary.url) {
            return;
          }

          const wrapper = document.createElement('div');
          wrapper.className = 'ap-upgrade-widget__secondary-action';

          if (secondary.title) {
            const heading = document.createElement('h5');
            heading.className = 'ap-upgrade-widget__secondary-title';
            heading.textContent = secondary.title;
            wrapper.appendChild(heading);
          }

          if (secondary.description) {
            const text = document.createElement('p');
            text.className = 'ap-upgrade-widget__secondary-description';
            text.textContent = secondary.description;
            wrapper.appendChild(text);
          }

          const secondaryLink = document.createElement('a');
          secondaryLink.className = 'ap-dashboard-button ap-dashboard-button--secondary ap-upgrade-widget__cta ap-upgrade-widget__cta--secondary';
          secondaryLink.href = secondary.url;
          secondaryLink.textContent = secondary.label || strings.upgradeLearnMore || 'Learn more';
          const secondaryAria = buildSecondaryAriaLabel(roleLabel, secondaryLink.textContent);
          if (secondaryAria) {
            secondaryLink.setAttribute('aria-label', secondaryAria);
          }
          wrapper.appendChild(secondaryLink);

          actions.appendChild(wrapper);
        });
      }

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

  function resolveUpgradeRoleLabel(upgrade = {}) {
    if (!upgrade || typeof upgrade !== 'object') {
      return '';
    }

    if (upgrade.role_label) {
      return String(upgrade.role_label);
    }

    if (upgrade.role) {
      return formatRoleLabel(upgrade.role);
    }

    if (upgrade.slug) {
      return formatRoleLabel(upgrade.slug);
    }

    if (upgrade.type) {
      return formatRoleLabel(upgrade.type);
    }

    return '';
  }

  function formatRoleLabel(value) {
    const normalized = String(value || '').toLowerCase();

    if (!normalized) {
      return '';
    }

    if (normalized === 'artist') {
      return strings.artistRoleLabel || 'Artist';
    }

    if (normalized === 'organization' || normalized === 'organisation') {
      return strings.organizationRoleLabel || 'Organization';
    }

    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
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
        renderStatus(btn, { state: 'loading' });

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

    root.querySelectorAll('[data-ap-upgrade-reopen][data-id]').forEach((btn) => {
      if (!btn || btn.dataset.apUpgradeReopenBound === '1') {
        return;
      }

      btn.dataset.apUpgradeReopenBound = '1';

      btn.addEventListener('click', (event) => {
        event.preventDefault();

        if (btn.dataset.apBusy === '1') {
          return;
        }

        const reviewId = parseInt(btn.dataset.id, 10);
        if (!reviewId) {
          return;
        }

        const card = findUpgradeCard(btn);
        const existingReason = getCurrentReason(card);

        setBusy(btn, true);

        reopen(reviewId)
          .then((response) => {
            const target = card || btn;
            const statusEl = renderStatus(
              target,
              ensureStatusDetails(
                {
                  state: (response && response.status) || 'pending',
                  reason:
                    response && typeof response.reason === 'string' ? response.reason : '',
                  label: response && response.badge,
                  message: response && response.message,
                  reviewId: response && response.id ? response.id : reviewId,
                  role: getUpgradeRole(target),
                },
                target
              )
            );

            setBusy(btn, false);

            if (btn.parentNode) {
              btn.parentNode.removeChild(btn);
            }

            if (statusEl) {
              focusStatusRegion(statusEl);
            }
          })
          .catch((error) => {
            setBusy(btn, false);
            const target = card || btn;
            const statusEl = renderStatus(
              target,
              ensureStatusDetails(
                {
                  state: 'error',
                  message: formatError(error),
                  reason: existingReason,
                  role: getUpgradeRole(target),
                },
                target
              )
            );

            if (statusEl) {
              focusStatusRegion(statusEl);
            }
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
    return request(payload);
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

  function request(payload) {
    const data =
      typeof payload === 'string'
        ? { type: payload }
        : Object.assign({}, payload || {});

    if (!data.type) {
      return Promise.reject(new Error('Upgrade type is required.'));
    }

    return api('/artpulse/v1/reviews', {
      method: 'POST',
      data,
    });
  }

  function reopen(review) {
    const value =
      review && typeof review === 'object' && review !== null ? review.id : review;
    const parsed = parseInt(value, 10);

    if (!parsed) {
      return Promise.reject(new Error('Invalid review identifier.'));
    }

    return api(`/artpulse/v1/reviews/${parsed}/reopen`, {
      method: 'POST',
    });
  }

  function disableUpgradeButton(button) {
    button.dataset.apUpgradeBusy = '1';
    button.classList.add('is-loading');
    button.setAttribute('aria-disabled', 'true');
    button.setAttribute('aria-busy', 'true');

    if (button.tagName === 'BUTTON') {
      button.disabled = true;
    }
  }

  function enableUpgradeButton(button) {
    delete button.dataset.apUpgradeBusy;
    button.classList.remove('is-loading');
    button.removeAttribute('aria-disabled');
    button.removeAttribute('aria-busy');

    if (button.tagName === 'BUTTON') {
      button.disabled = false;
    }
  }

  function renderUpgradePendingState(button, response, requestedType) {
    const card = findUpgradeCard(button) || button;
    const roleLabel = getUpgradeRole(card);
    const statusEl = renderStatus(
      card,
      ensureStatusDetails(
        {
          state: (response && response.status) || 'pending',
          reason: response && typeof response.reason === 'string' ? response.reason : '',
          label: response && response.badge,
          message: response && response.message,
          reviewId: response && response.id,
          role: roleLabel,
        },
        card
      )
    );

    if (card && card.dataset) {
      card.dataset.apUpgradeType = requestedType;
    }

    clearUpgradeError(button);

    const form = button.closest('form');
    if (form && form.parentNode) {
      form.parentNode.removeChild(form);
    }

    const actions = findUpgradeActionsContainer(button);
    if (actions && actions.contains(button)) {
      actions.removeChild(button);
    }

    if (statusEl) {
      focusStatusRegion(statusEl);
    }
  }

  function renderUpgradeError(button, error) {
    const card = findUpgradeCard(button);
    const target = card || button;
    const statusEl = renderStatus(
      target,
      ensureStatusDetails(
        {
          state: 'error',
          message: formatError(error),
          reason: '',
          role: getUpgradeRole(target),
        },
        target
      )
    );

    if (statusEl) {
      focusStatusRegion(statusEl);
    }
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

    const status = card ? card.querySelector('[data-ap-upgrade-status]') : null;
    if (status) {
      status.classList.remove('is-error');
      if (status.dataset) {
        delete status.dataset.apUpgradeStatus;
      }
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

  function getUpgradeRole(target) {
    const card =
      target && target.nodeType === 1 && target.matches && target.matches('[data-ap-upgrade-card]')
        ? target
        : findUpgradeCard(target);

    if (card && card.dataset && card.dataset.apUpgradeRole) {
      return card.dataset.apUpgradeRole;
    }

    return '';
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

  function renderStatus(target, details = {}) {
    let card = null;
    let container = null;

    if (target && target.nodeType === 1 && target.hasAttribute && target.hasAttribute('data-ap-upgrade-status')) {
      container = target;
      card = findUpgradeCard(target);
    } else {
      card =
        target && target.nodeType === 1 && target.matches && target.matches('[data-ap-upgrade-card]')
          ? target
          : findUpgradeCard(target);
      container = card && card.querySelector ? card.querySelector('[data-ap-upgrade-status]') : null;
    }

    if (!container) {
      container = document.createElement('div');
      container.className = 'ap-upgrade-status';
      container.setAttribute('data-ap-upgrade-status', '');
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('role', 'status');
      container.setAttribute('aria-atomic', 'true');
      container.setAttribute('tabindex', '-1');

      const body = card && card.querySelector ? card.querySelector('.ap-dashboard-card__body') : null;
      if (body && body.firstChild) {
        body.insertBefore(container, body.firstChild);
      } else if (body) {
        body.appendChild(container);
      } else if (card) {
        card.insertBefore(container, card.firstChild || null);
      }
    }

    const normalized = ensureStatusDetails(
      Object.assign({}, details, {
        state: details.state || container.dataset.apUpgradeStatus || '',
      }),
      card
    );

    container.dataset.apUpgradeStatus = normalized.state;
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('role', 'status');
    container.setAttribute('aria-atomic', 'true');

    if (card && card.dataset) {
      card.dataset.apUpgradeStatus = normalized.state;
      if (normalized.label) {
        card.dataset.apUpgradeStatusLabel = normalized.label;
      } else {
        delete card.dataset.apUpgradeStatusLabel;
      }
      if (normalized.message) {
        card.dataset.apUpgradeStatusMessage = normalized.message;
      } else {
        delete card.dataset.apUpgradeStatusMessage;
      }
      if (normalized.reviewId) {
        card.dataset.apUpgradeReview = String(normalized.reviewId);
      }
      if (normalized.role) {
        card.dataset.apUpgradeRole = normalized.role;
      }
    }

    let badge = container.querySelector('[data-ap-upgrade-badge]');
    if (!badge) {
      badge = document.createElement('strong');
      badge.setAttribute('data-ap-upgrade-badge', '1');
      container.insertBefore(badge, container.firstChild);
    }
    badge.className = getBadgeClass(normalized.state, normalized.badgeClass);
    if (normalized.label) {
      badge.textContent = normalized.label;
      badge.hidden = false;
    } else {
      badge.textContent = '';
      badge.hidden = true;
    }

    let message = container.querySelector('[data-ap-upgrade-message]');
    if (!message) {
      message = document.createElement('p');
      message.className = 'ap-upgrade-status__message';
      message.setAttribute('data-ap-upgrade-message', '1');
      container.appendChild(message);
    }
    if (normalized.message) {
      message.textContent = normalized.message;
      message.hidden = false;
    } else {
      message.textContent = '';
      message.hidden = true;
    }

    let reason = container.querySelector('[data-ap-upgrade-reason]');
    if (normalized.state === 'denied' && normalized.reason) {
      if (!reason) {
        reason = document.createElement('p');
        reason.className = 'ap-upgrade-status__reason';
        reason.setAttribute('data-ap-upgrade-reason', '1');
        const text = document.createElement('span');
        text.setAttribute('data-ap-upgrade-reason-text', '1');
        reason.appendChild(text);

        container.appendChild(reason);
      }

      const textNode = reason.querySelector('[data-ap-upgrade-reason-text]');
      if (textNode) {
        textNode.textContent = normalized.reason;
      }
      reason.hidden = false;
    } else if (reason) {
      reason.remove();
    }

    container.classList.toggle('is-error', normalized.state === 'error');
    container.classList.toggle('is-loading', normalized.state === 'loading');

    return container;
  }

  function ensureStatusDetails(details = {}, card) {
    const node = card && card.nodeType === 1 ? card : findUpgradeCard(card);
    const dataset = (node && node.dataset) || {};
    const normalized = {};

    const stateInput = details.state || dataset.apUpgradeStatus || '';
    normalized.state = String(stateInput || '').toLowerCase();

    const capitalized = normalized.state
      ? normalized.state.charAt(0).toUpperCase() + normalized.state.slice(1)
      : '';

    normalized.role = details.role || dataset.apUpgradeRole || '';

    normalized.label =
      (typeof details.label === 'string' && details.label) ||
      (typeof details.badge === 'string' && details.badge) ||
      dataset.apUpgradeStatusLabel ||
      strings[`upgrade${capitalized}Badge`] ||
      getStatusLabel(normalized.state);

    const rawMessage =
      (typeof details.message === 'string' && details.message) ||
      dataset.apUpgradeStatusMessage ||
      strings[`upgrade${capitalized}Message`] ||
      '';

    normalized.message = replaceRolePlaceholder(
      rawMessage || getStatusMessage(normalized.state, normalized.role),
      normalized.role
    );

    if (normalized.state === 'denied') {
      if (typeof details.reason === 'string' && details.reason.trim() !== '') {
        normalized.reason = details.reason.trim();
      } else {
        normalized.reason = getCurrentReason(node);
      }
    } else {
      normalized.reason = '';
    }

    if (typeof details.reviewId !== 'undefined' && details.reviewId !== null) {
      normalized.reviewId = details.reviewId;
    } else if (dataset.apUpgradeReview) {
      normalized.reviewId = dataset.apUpgradeReview;
    }

    normalized.badgeClass = details.badgeClass || '';

    return normalized;
  }

  function getStatusLabel(state) {
    switch (state) {
      case 'approved':
        return strings.upgradeApprovedBadge || 'Approved';
      case 'denied':
        return strings.upgradeDeniedBadge || 'Denied';
      case 'pending':
        return strings.upgradePendingBadge || 'Pending';
      case 'error':
        return strings.upgradeError || 'Request error';
      default:
        return state ? state.charAt(0).toUpperCase() + state.slice(1) : '';
    }
  }

  function getStatusMessage(state, roleLabel) {
    switch (state) {
      case 'approved': {
        const template = strings.upgradeApprovedMessage || 'Approved — you now have the {role} role.';
        return replaceRolePlaceholder(template, roleLabel);
      }
      case 'denied':
        return strings.upgradeDeniedMessage || 'Denied.';
      case 'pending':
        return strings.upgradePendingMessage || 'Your upgrade request is pending review.';
      case 'error':
        return strings.upgradeError || 'Unable to submit your request. Please try again.';
      default:
        return '';
    }
  }

  function replaceRolePlaceholder(text, roleLabel) {
    if (typeof text !== 'string') {
      return '';
    }

    const role = roleLabel || '';

    if (text.includes('{role}')) {
      const replacement = role || strings.upgradeApprovedGeneric || 'upgraded';
      return text.replace('{role}', replacement);
    }

    return text;
  }

  function replaceLabelPlaceholder(text, label) {
    if (typeof text !== 'string') {
      return '';
    }

    if (text.includes('{label}')) {
      return text.replace('{label}', label || '');
    }

    return text;
  }

  function buildPrimaryAriaLabel(roleLabel, fallback) {
    if (roleLabel) {
      const template = replaceRolePlaceholder(strings.upgradePrimaryAria || '', roleLabel);
      if (template) {
        return template;
      }

      return `View ${roleLabel} upgrade details`;
    }

    return strings.upgradePrimaryAriaGeneric || fallback || strings.upgradeCta || 'Upgrade now';
  }

  function buildReopenAriaLabel(roleLabel, fallback) {
    if (roleLabel) {
      const template = replaceRolePlaceholder(strings.upgradeReopenAria || '', roleLabel);
      if (template) {
        return template;
      }
    }

    return strings.upgradeReopenAriaGeneric || fallback || strings.upgradeReopen || 'Re-request review';
  }

  function buildSecondaryAriaLabel(roleLabel, label) {
    if (roleLabel) {
      const template = replaceRolePlaceholder(strings.upgradeSecondaryAria || '', roleLabel);
      if (template) {
        return template;
      }
    }

    const generic = replaceLabelPlaceholder(strings.upgradeSecondaryAriaGeneric || '', label);
    if (generic) {
      return generic;
    }

    const learnMore = strings.upgradeLearnMore || 'Learn more';
    return label ? `${learnMore}: ${label}` : learnMore;
  }

  function getBadgeClass(state, customClass) {
    const classes = ['ap-badge'];

    if (typeof customClass === 'string' && customClass.trim() !== '') {
      classes.push(customClass.trim());
    }

    if (state) {
      classes.push(`ap-badge--${state}`);
    }

    return classes.join(' ');
  }

  function getCurrentReason(card) {
    const node = card && card.querySelector ? card.querySelector('[data-ap-upgrade-reason-text]') : null;
    return node ? node.textContent.trim() : '';
  }

  function focusStatusRegion(element) {
    if (!element || typeof element.focus !== 'function') {
      return;
    }

    const hadTabindex = element.hasAttribute('tabindex');
    if (!hadTabindex) {
      element.setAttribute('tabindex', '-1');
      element.dataset.apUpgradeFocusTemp = '1';
    }

    try {
      element.focus();
    } catch (e) {
      /* focus optional */
    }

    if (!hadTabindex) {
      element.addEventListener(
        'blur',
        () => {
          element.removeAttribute('tabindex');
          delete element.dataset.apUpgradeFocusTemp;
        },
        { once: true }
      );
    }
  }

  function setBusy(element, busy) {
    if (!element) {
      return;
    }

    const isBusy = Boolean(busy);
    if (isBusy) {
      element.dataset.apBusy = '1';
      element.setAttribute('aria-busy', 'true');
      element.setAttribute('aria-disabled', 'true');
      element.classList.add('is-loading');

      if (element.tagName && element.tagName.toLowerCase() === 'button') {
        element.disabled = true;
      }
    } else {
      element.removeAttribute('aria-busy');
      element.removeAttribute('aria-disabled');
      element.classList.remove('is-loading');

      if (element.tagName && element.tagName.toLowerCase() === 'button') {
        element.disabled = false;
      }

      delete element.dataset.apBusy;
    }
  }

  function formatError(error) {
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

  function initOrgBuilderNotice() {
    const builder = document.querySelector('.ap-org-builder');
    if (!builder || isOrgRequestDismissed()) {
      return;
    }

    const state = readOrgRequestState();
    if (state === 'pending') {
      renderOrgRequestNotice(builder);
      return;
    }

    if (state === 'none') {
      return;
    }

    fetchOrgPendingReviews()
      .then((pending) => {
        if (pending.length > 0) {
          writeOrgRequestState('pending');
          renderOrgRequestNotice(builder);
        } else {
          writeOrgRequestState('none');
        }
      })
      .catch(() => {
        // Leave state unset so a future navigation can retry.
      });
  }

  function renderOrgRequestNotice(builder) {
    if (!builder || builder.querySelector('[data-ap-org-request-notice]')) {
      return;
    }

    const notice = document.createElement('div');
    notice.className = 'ap-notice ap-notice--info';
    notice.setAttribute('role', 'status');
    notice.setAttribute('aria-live', 'polite');
    notice.setAttribute('data-ap-org-request-notice', '1');

    const message = document.createElement('p');
    message.className = 'ap-notice__message';
    message.textContent = strings.orgRequestNotice || 'Your organization upgrade request is pending review.';
    notice.appendChild(message);

    const actions = document.createElement('div');
    actions.className = 'ap-notice__actions';

    const links = document.createElement('div');
    links.className = 'ap-notice__links';

    const historyLink = document.createElement('a');
    historyLink.href = '#';
    historyLink.className = 'ap-notice__link';
    historyLink.textContent = strings.orgRequestHistoryCta || 'View request history';
    historyLink.addEventListener('click', (event) => {
      event.preventDefault();
      openOrgRequestHistory();
    });
    links.appendChild(historyLink);

    const supportHref = typeof supportUrl === 'string' ? supportUrl.trim() : '';
    if (supportHref) {
      const supportLink = document.createElement('a');
      supportLink.className = 'ap-notice__link';
      supportLink.href = supportHref;
      supportLink.textContent = strings.orgRequestSupportCta || 'Contact support';
      const lowerHref = supportHref.toLowerCase();
      const isMailto = lowerHref.startsWith('mailto:');
      const isHttp = lowerHref.startsWith('http://') || lowerHref.startsWith('https://');
      if (!isMailto && isHttp) {
        supportLink.target = '_blank';
        supportLink.rel = 'noopener noreferrer';
      }
      links.appendChild(supportLink);
    }

    actions.appendChild(links);

    const dismissButton = document.createElement('button');
    dismissButton.type = 'button';
    dismissButton.className = 'ap-notice__dismiss';
    dismissButton.setAttribute('aria-label', strings.orgRequestDismiss || 'Dismiss');
    dismissButton.textContent = strings.orgRequestDismiss || 'Dismiss';
    dismissButton.addEventListener('click', () => {
      if (notice.parentNode) {
        notice.parentNode.removeChild(notice);
      }
      markOrgRequestDismissed();
    });

    actions.appendChild(dismissButton);
    notice.appendChild(actions);

    if (typeof builder.prepend === 'function') {
      builder.prepend(notice);
    } else {
      builder.insertBefore(notice, builder.firstChild || null);
    }
  }

  function openOrgRequestHistory() {
    showOrgRequestModal();
    setOrgRequestModalState('loading');

    if (Array.isArray(orgRequestHistoryCache)) {
      setOrgRequestModalState('list', orgRequestHistoryCache);
      return;
    }

    if (orgRequestHistoryPromise) {
      return;
    }

    orgRequestHistoryPromise = fetchOrgReviews()
      .then((items) => {
        orgRequestHistoryCache = items;
        setOrgRequestModalState('list', items);
      })
      .catch(() => {
        setOrgRequestModalState('error');
      })
      .finally(() => {
        orgRequestHistoryPromise = null;
      });
  }

  function ensureOrgRequestModal() {
    if (orgRequestModal && document.body.contains(orgRequestModal)) {
      return orgRequestModal;
    }

    const overlay = document.createElement('div');
    overlay.className = 'ap-org-request-modal';
    overlay.setAttribute('data-ap-org-request-modal', '1');
    overlay.setAttribute('hidden', '');
    overlay.setAttribute('aria-hidden', 'true');

    const dialog = document.createElement('div');
    dialog.className = 'ap-org-request-modal__dialog';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-labelledby', 'ap-org-request-modal-title');
    dialog.setAttribute('tabindex', '-1');

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'ap-org-request-modal__close';
    closeButton.textContent = strings.orgRequestModalClose || 'Close';
    closeButton.addEventListener('click', closeOrgRequestModal);

    const title = document.createElement('h2');
    title.id = 'ap-org-request-modal-title';
    title.className = 'ap-org-request-modal__title';
    title.textContent = strings.orgRequestModalTitle || 'Organization request history';

    const description = document.createElement('p');
    description.className = 'ap-org-request-modal__description';
    description.textContent = strings.orgRequestModalDescription || 'Review the status of your organization upgrade requests.';

    const content = document.createElement('div');
    content.className = 'ap-org-request-modal__content';
    content.setAttribute('data-ap-org-request-content', '1');

    dialog.appendChild(closeButton);
    dialog.appendChild(title);
    dialog.appendChild(description);
    dialog.appendChild(content);
    overlay.appendChild(dialog);

    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        closeOrgRequestModal();
      }
    });

    overlay.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeOrgRequestModal();
      }
    });

    document.body.appendChild(overlay);

    orgRequestModal = overlay;
    orgRequestModalContent = content;
    orgRequestModalClose = closeButton;

    return overlay;
  }

  function showOrgRequestModal() {
    const modal = ensureOrgRequestModal();
    if (!modal) {
      return;
    }

    orgRequestLastFocused = document.activeElement && typeof document.activeElement.focus === 'function'
      ? document.activeElement
      : null;

    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');

    if (orgRequestModalClose && typeof orgRequestModalClose.focus === 'function') {
      try {
        orgRequestModalClose.focus();
      } catch (e) {
        /* focus optional */
      }
    }
  }

  function closeOrgRequestModal() {
    if (!orgRequestModal) {
      return;
    }

    orgRequestModal.setAttribute('hidden', '');
    orgRequestModal.setAttribute('aria-hidden', 'true');

    if (orgRequestModalContent) {
      orgRequestModalContent.innerHTML = '';
    }

    if (orgRequestLastFocused && typeof orgRequestLastFocused.focus === 'function') {
      try {
        orgRequestLastFocused.focus();
      } catch (e) {
        /* noop */
      }
    }
  }

  function setOrgRequestModalState(state, items = []) {
    if (!orgRequestModalContent) {
      ensureOrgRequestModal();
    }

    if (!orgRequestModalContent) {
      return;
    }

    orgRequestModalContent.innerHTML = '';

    if (state === 'loading') {
      orgRequestModalContent.textContent = strings.loading || 'Loading…';
      return;
    }

    if (state === 'error') {
      const error = document.createElement('p');
      error.className = 'ap-org-request-modal__error';
      error.textContent = strings.orgRequestHistoryError || (strings.error || 'Unable to load request history.');
      orgRequestModalContent.appendChild(error);
      return;
    }

    const list = renderOrgRequestHistoryList(items);
    orgRequestModalContent.appendChild(list);
  }

  function renderOrgRequestHistoryList(items = []) {
    if (!Array.isArray(items) || items.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'ap-org-request-modal__empty';
      empty.textContent = strings.orgRequestModalEmpty || 'No organization upgrade requests yet.';
      return empty;
    }

    const list = document.createElement('ul');
    list.className = 'ap-org-request-modal__list';

    items.forEach((item) => {
      const entry = document.createElement('li');
      entry.className = 'ap-org-request-modal__item';

      const status = document.createElement('div');
      status.className = 'ap-org-request-modal__status';
      status.textContent = getOrgRequestStatusLabel(item && item.status);
      entry.appendChild(status);

      if (item && item.createdAt) {
        const date = document.createElement('div');
        date.className = 'ap-org-request-modal__date';
        date.textContent = formatOrgRequestSubmittedOn(item.createdAt);
        entry.appendChild(date);
      }

      if (item && item.reason) {
        const reason = document.createElement('div');
        reason.className = 'ap-org-request-modal__reason';
        const label = document.createElement('strong');
        label.textContent = strings.orgRequestReasonLabel || 'Reason';
        reason.appendChild(label);
        reason.appendChild(document.createTextNode(`: ${item.reason}`));
        entry.appendChild(reason);
      }

      list.appendChild(entry);
    });

    return list;
  }

  function getOrgRequestStatusLabel(status) {
    const key = typeof status === 'string' ? status.toLowerCase() : '';
    if (key === 'pending') {
      return strings.orgRequestStatusPending || 'Pending';
    }
    if (key === 'approved') {
      return strings.orgRequestStatusApproved || 'Approved';
    }
    if (key === 'denied') {
      return strings.orgRequestStatusDenied || 'Denied';
    }
    return key ? key.charAt(0).toUpperCase() + key.slice(1) : '';
  }

  function formatOrgRequestSubmittedOn(value) {
    const formatted = formatDate(value);
    if (typeof strings.orgRequestSubmittedOn === 'string' && strings.orgRequestSubmittedOn.includes('%s')) {
      return strings.orgRequestSubmittedOn.replace('%s', formatted);
    }
    return `Submitted on ${formatted}`;
  }

  function fetchOrgReviews(query = {}) {
    const data = Object.assign({ type: 'organization' }, query || {});
    return api('/reviews/me', { data }).then((response) => {
      if (!Array.isArray(response)) {
        return [];
      }
      return response.filter((item) => item && item.type === 'organization');
    });
  }

  function fetchOrgPendingReviews() {
    return fetchOrgReviews({ status: 'pending' }).then((items) =>
      items.filter((item) => item && item.status === 'pending')
    );
  }

  function hasSessionStorage() {
    if (sessionStorageSupported !== null) {
      return sessionStorageSupported;
    }

    sessionStorageSupported = false;

    try {
      const testKey = '__ap_org_request__';
      window.sessionStorage.setItem(testKey, '1');
      window.sessionStorage.removeItem(testKey);
      sessionStorageSupported = true;
    } catch (error) {
      sessionStorageSupported = false;
    }

    return sessionStorageSupported;
  }

  function readOrgRequestState() {
    if (!hasSessionStorage()) {
      return null;
    }

    return window.sessionStorage.getItem(ORG_REQUEST_STATE_KEY);
  }

  function writeOrgRequestState(value) {
    if (!hasSessionStorage()) {
      return;
    }

    try {
      window.sessionStorage.setItem(ORG_REQUEST_STATE_KEY, String(value));
    } catch (error) {
      /* storage optional */
    }
  }

  function isOrgRequestDismissed() {
    if (!hasSessionStorage()) {
      return false;
    }

    return window.sessionStorage.getItem(ORG_REQUEST_DISMISSED_KEY) === '1';
  }

  function markOrgRequestDismissed() {
    if (!hasSessionStorage()) {
      return;
    }

    try {
      window.sessionStorage.setItem(ORG_REQUEST_DISMISSED_KEY, '1');
    } catch (error) {
      /* storage optional */
    }
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

  function api(path, options = {}) {
    const normalizedPath = path && path.charAt(0) === '/' ? path : `/${path || ''}`;
    const method = (options.method || 'GET').toUpperCase();
    const headers = Object.assign({}, options.headers || {}, {
      'X-WP-Nonce': apiNonce(),
    });

    if (window.wp && window.wp.apiFetch) {
      const apiOptions = Object.assign({}, options, {
        path: normalizedPath,
        method,
        headers,
      });

      if (options.data) {
        apiOptions.data = options.data;
      }

      return window.wp.apiFetch(apiOptions);
    }

    const fetchOptions = {
      method,
      headers,
      credentials: 'same-origin',
    };

    let url = joinPath(apiRoot(), normalizedPath);

    if (options.data && method === 'GET') {
      const params = new URLSearchParams(options.data);
      url += (url.indexOf('?') === -1 ? '?' : '&') + params.toString();
    } else if (options.data) {
      fetchOptions.body = JSON.stringify(options.data);
      if (!fetchOptions.headers['Content-Type']) {
        fetchOptions.headers['Content-Type'] = 'application/json';
      }
    } else if (typeof options.body !== 'undefined') {
      fetchOptions.body = options.body;
    }

    return fetch(url, fetchOptions).then((response) => {
      if (!response.ok) {
        return response
          .json()
          .catch(() => ({}))
          .then((error) => Promise.reject(error));
      }

      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('application/json')) {
        return response.json();
      }

      return response.text();
    });
  }

  const app = {
    initAll,
    init,
    initOrgBuilderNotice,
  };

  onReady(() => {
    initAll();
    initOrgBuilderNotice();
  });

  window.ArtPulseDashboardsApp = Object.assign(window.ArtPulseDashboardsApp || {}, app);
  window.ArtPulseDash = Object.assign(window.ArtPulseDash || {}, {
    request,
    reopen,
  });
})();
