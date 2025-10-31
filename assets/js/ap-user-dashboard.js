(function () {
  const dashboardConfig = window.ArtPulseDashboards || {};
  const STRINGS = dashboardConfig.strings || {};

  const API_SETTINGS = {
    root: resolveApiRoot(),
    nonce: resolveNonce(),
  };

  const TEXT = {
    loading: STRINGS.loading || 'Loading…',
    error: STRINGS.error || 'Unable to load dashboard data.',
    retry: STRINGS.retry || 'Retry',
    empty: STRINGS.empty || 'Nothing to display yet.',
    profileSectionTitle: STRINGS.profileSectionTitle || STRINGS.profile || 'Profiles',
    profileArtistTitle: STRINGS.profileArtistTitle || 'Artist profile',
    profileOrgTitle: STRINGS.profileOrgTitle || 'Organization profile',
    profileCreate: STRINGS.createProfile || 'Create profile',
    profileFinish: STRINGS.finishProfile || STRINGS.editProfile || 'Finish profile',
    profileEdit: STRINGS.editProfile || 'Edit profile',
    profileNotStarted: STRINGS.profileNotStarted || 'Not started',
    profileDraft: STRINGS.profileDraft || 'Draft',
    profilePending: STRINGS.profilePending || STRINGS.upgradePendingBadge || 'Pending review',
    profilePublished: STRINGS.profilePublished || 'Published',
    upgradeSectionTitle: STRINGS.upgradeSectionTitle || STRINGS.upgrades || 'Membership upgrades',
    upgradeCardTitle: STRINGS.upgradeCardTitle || 'Choose your upgrade path',
    upgradeDescription:
      STRINGS.upgradeDescription || STRINGS.upgradeIntro || 'Upgrade to unlock additional features and visibility.',
    upgradeArtistCta: STRINGS.upgradeArtistCta || STRINGS.becomeArtist || 'Become an Artist',
    upgradeOrgCta:
      STRINGS.upgradeOrgCta || STRINGS.becomeOrganization || STRINGS.upgradeBecomeOrganization || 'Become an Organization',
    upgradePending: STRINGS.upgradePending || STRINGS.upgradePendingBadge || 'Pending review',
    upgradeApproved: STRINGS.upgradeApproved || STRINGS.upgradeApprovedBadge || 'Approved',
    upgradeDenied: STRINGS.upgradeDenied || STRINGS.upgradeDeniedBadge || 'Denied',
    upgradeUnavailable: STRINGS.upgradeUnavailable || 'Unavailable',
    upgradeError: STRINGS.upgradeError || 'Unable to submit upgrade request.',
  };

  const PROFILE_DESCRIPTIONS = {
    artist: {
      none:
        STRINGS.artistProfileEmpty ||
        'Create your artist profile to showcase your work and unlock creator tools.',
      draft:
        STRINGS.artistProfileDraft || 'Keep building your artist profile to get it ready for review.',
      pending:
        STRINGS.artistProfilePending || 'Your artist profile is under review. We will email you once it is approved.',
      publish:
        STRINGS.artistProfilePublished || 'Your artist profile is live. Update it anytime to keep it fresh.',
    },
    org: {
      none:
        STRINGS.orgProfileEmpty ||
        'Create an organization profile to highlight your collective and promote events.',
      draft:
        STRINGS.orgProfileDraft || 'Finish your organization profile to share it with the community.',
      pending:
        STRINGS.orgProfilePending || 'Your organization profile is pending review.',
      publish:
        STRINGS.orgProfilePublished || 'Your organization profile is live and ready to share.',
    },
  };

  function onReady(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  }

  function resolveApiRoot() {
    const root = dashboardConfig.root || (window.wpApiSettings && window.wpApiSettings.root) || '';

    if (!root) {
      return '/wp-json/';
    }

    return root.endsWith('/') ? root : `${root}/`;
  }

  function resolveNonce() {
    const settings = window.wpApiSettings || {};

    if (dashboardConfig.nonce) {
      return dashboardConfig.nonce;
    }

    if (settings.nonce) {
      return settings.nonce;
    }

    return '';
  }

  function buildQueryString(params) {
    if (!params) {
      return '';
    }

    const search = new URLSearchParams();

    Object.keys(params).forEach((key) => {
      const value = params[key];

      if (value === undefined || value === null || value === '') {
        return;
      }

      search.append(key, value);
    });

    const query = search.toString();

    return query ? `?${query}` : '';
  }

  function buildApiUrl(path, params) {
    const normalizedPath = String(path || '').replace(/^\/+/g, '');
    let url = API_SETTINGS.root + normalizedPath;

    if (params) {
      const query = buildQueryString(params);
      if (query) {
        url += query;
      }
    }

    return url;
  }

  function parseJsonResponse(response) {
    if (!response || typeof response.json !== 'function') {
      return Promise.reject(new Error('Invalid response object.'));
    }

    if (!response.ok) {
      return response
        .json()
        .catch(() => ({}))
        .then((error) => {
          const message = error && error.message ? error.message : 'Request failed';
          const output = new Error(message);
          output.data = error;
          throw output;
        });
    }

    return response
      .json()
      .catch(() => ({}));
  }

  function fetchFromApi(path, options = {}) {
    const method = options.method || 'GET';
    const data = options.data;
    const headers = Object.assign({ 'X-WP-Nonce': API_SETTINGS.nonce }, options.headers || {});

    if (window.wp && window.wp.apiFetch) {
      const apiOptions = {
        path,
        method,
        headers,
      };

      if (method === 'GET' && data && Object.keys(data).length) {
        apiOptions.path = `${path}${buildQueryString(data)}`;
      } else if (method !== 'GET' && typeof data !== 'undefined') {
        apiOptions.data = data;
      }

      if (options.body) {
        apiOptions.body = options.body;
      }

      return window.wp.apiFetch(apiOptions);
    }

    const fetchOptions = {
      method,
      headers,
      credentials: 'same-origin',
    };

    if (method === 'GET') {
      const url = buildApiUrl(path, data);
      return fetch(url, fetchOptions).then(parseJsonResponse);
    }

    if (options.body) {
      fetchOptions.body = options.body;
    } else if (typeof data !== 'undefined') {
      fetchOptions.body = JSON.stringify(data);
      if (!fetchOptions.headers['Content-Type']) {
        fetchOptions.headers['Content-Type'] = 'application/json';
      }
    }

    const url = buildApiUrl(path);
    return fetch(url, fetchOptions).then(parseJsonResponse);
  }

  function getDashboardData(role) {
    const params = role ? { role } : undefined;
    return fetchFromApi('/artpulse/v1/user/dashboard', { method: 'GET', data: params });
  }

  function postUpgradeReview(type) {
    return fetchFromApi('/artpulse/v1/upgrade-reviews', {
      method: 'POST',
      data: { type },
    });
  }

  function bindUserDashboard(container) {
    if (!container) {
      return;
    }

    const state = {
      container,
      role: container.dataset.apDashboardRole || '',
      notice: null,
      data: null,
      optimisticUpgrades: {},
      loadingPromise: null,
    };

    container.__apUserDashboardState = state;

    loadUserDashboard(state, { initial: true });
  }

  function loadUserDashboard(state, options = {}) {
    if (!state) {
      return Promise.resolve();
    }

    if (state.loadingPromise) {
      return state.loadingPromise;
    }

    const initial = Boolean(options.initial);

    if (initial) {
      renderUserDashboardLoading(state);
    } else {
      state.container.setAttribute('aria-busy', 'true');
    }

    clearUserDashboardNotice(state);

    state.loadingPromise = getDashboardData(state.role || undefined)
      .then((data) => {
        state.loadingPromise = null;
        state.container.classList.remove('is-loading');
        state.container.removeAttribute('aria-busy');
        state.data = data;
        state.optimisticUpgrades = {};
        renderUserDashboard(state, data);
        return data;
      })
      .catch((error) => {
        state.loadingPromise = null;
        state.container.classList.remove('is-loading');
        state.container.removeAttribute('aria-busy');
        const message = extractErrorMessage(error) || TEXT.error;

        if (initial) {
          renderUserDashboardError(state, message);
        } else {
          showUserDashboardNotice(state, 'warning', message);
        }

        throw error;
      });

    return state.loadingPromise;
  }

  function renderUserDashboardLoading(state) {
    const container = state && state.container;
    if (!container) {
      return;
    }

    container.classList.add('is-loading');
    container.setAttribute('aria-busy', 'true');
    clearContainer(container);

    const loading = document.createElement('div');
    loading.className = 'ap-dashboard-loading';
    loading.textContent = TEXT.loading;

    container.appendChild(loading);
  }

  function renderUserDashboard(state, data) {
    const container = state && state.container;
    if (!container) {
      return;
    }

    clearContainer(container);

    const notice = ensureNotice(state);
    if (notice.parentNode !== container) {
      container.insertBefore(notice, container.firstChild || null);
    }

    clearUserDashboardNotice(state);

    const sections = [];
    const profiles = buildUserDashboardProfileSection(state, data && data.profile);
    if (profiles) {
      sections.push(profiles);
    }

    const upgrades = buildUserDashboardUpgradeSection(state, data && data.upgrade);
    if (upgrades) {
      sections.push(upgrades);
    }

    if (sections.length === 0) {
      container.appendChild(createUserDashboardEmptyState());
    } else {
      const grid = document.createElement('div');
      grid.className = 'ap-dashboard-grid ap-user-dashboard__sections';
      sections.forEach((section) => {
        grid.appendChild(section);
      });
      container.appendChild(grid);
    }

    initProfileForms(container);
  }

  function renderUserDashboardError(state, message) {
    const container = state && state.container;
    if (!container) {
      return;
    }

    state.notice = null;
    state.data = null;

    clearContainer(container);

    const error = document.createElement('div');
    error.className = 'ap-dashboard-error';

    const text = document.createElement('p');
    text.textContent = message || TEXT.error;
    error.appendChild(text);

    const retry = document.createElement('button');
    retry.type = 'button';
    retry.className = 'ap-dashboard-button ap-dashboard-button--primary';
    retry.textContent = TEXT.retry;
    retry.addEventListener('click', () => {
      loadUserDashboard(state, { initial: true });
    });

    error.appendChild(retry);

    container.appendChild(error);
  }

  function createUserDashboardEmptyState(message) {
    const empty = document.createElement('div');
    empty.className = 'ap-dashboard-empty';
    empty.textContent = message || TEXT.empty;
    return empty;
  }

  function buildUserDashboardProfileSection(state, profileData) {
    const section = document.createElement('section');
    section.className = 'ap-dashboard-section ap-dashboard-section--profiles';

    const header = document.createElement('header');
    header.className = 'ap-dashboard-section__header';

    const title = document.createElement('h3');
    title.textContent = TEXT.profileSectionTitle;
    header.appendChild(title);

    section.appendChild(header);

    const artistCard = createUserProfileCard('artist', profileData && profileData.artist);
    const orgCard = createUserProfileCard('org', profileData && profileData.org);

    section.appendChild(artistCard);
    section.appendChild(orgCard);

    return section;
  }

  function createUserProfileCard(type, profileState) {
    const normalizedType = type === 'org' ? 'org' : 'artist';
    const card = document.createElement('article');
    card.className = 'ap-dashboard-card ap-user-dashboard__profile-card';
    card.dataset.apProfileType = normalizedType;

    const body = document.createElement('div');
    body.className = 'ap-dashboard-card__body';

    const heading = document.createElement('h4');
    heading.textContent = normalizedType === 'artist' ? TEXT.profileArtistTitle : TEXT.profileOrgTitle;
    body.appendChild(heading);

    const statusChip = resolveProfileStatusChip(normalizedType, profileState);
    if (statusChip) {
      body.appendChild(statusChip);
    }

    const description = document.createElement('p');
    description.className = 'ap-user-dashboard__profile-description';
    description.textContent = resolveProfileDescription(normalizedType, profileState);
    body.appendChild(description);

    card.appendChild(body);

    const actions = document.createElement('div');
    actions.className = 'ap-dashboard-card__actions';

    const cta = resolveProfileCta(normalizedType, profileState);
    const button = document.createElement('a');
    button.className = 'ap-dashboard-button ap-dashboard-button--primary';
    button.textContent = cta.label;
    button.href = cta.url || '#';

    if (cta.disabled) {
      disableLinkButton(button);
    }

    actions.appendChild(button);
    card.appendChild(actions);

    return card;
  }

  function resolveProfileStatusChip(type, profileState) {
    const exists = profileState && profileState.exists;
    const status = profileState && profileState.status ? String(profileState.status).toLowerCase() : '';

    if (!exists && !status) {
      const chip = document.createElement('span');
      chip.className = 'ap-dashboard-badge ap-dashboard-badge--muted';
      chip.textContent = TEXT.profileNotStarted;
      return chip;
    }

    const chip = document.createElement('span');
    chip.className = 'ap-dashboard-badge';

    let label = TEXT.profileDraft;
    let variant = 'warning';

    switch (status) {
      case 'publish':
      case 'published':
        label = TEXT.profilePublished;
        variant = 'success';
        break;
      case 'pending':
        label = TEXT.profilePending;
        variant = 'info';
        break;
      case 'draft':
      case 'auto-draft':
        label = TEXT.profileDraft;
        variant = 'warning';
        break;
      default:
        if (!exists) {
          label = TEXT.profileNotStarted;
          variant = 'muted';
        }
        break;
    }

    chip.classList.add(`ap-dashboard-badge--${variant}`);
    chip.textContent = label;

    return chip;
  }

  function resolveProfileDescription(type, profileState) {
    const copy = PROFILE_DESCRIPTIONS[type] || PROFILE_DESCRIPTIONS.artist;
    const exists = profileState && profileState.exists;
    const status = profileState && profileState.status ? String(profileState.status).toLowerCase() : '';

    if (!exists) {
      return copy.none;
    }

    if (status === 'publish' || status === 'published') {
      return copy.publish;
    }

    if (status === 'pending') {
      return copy.pending;
    }

    return copy.draft;
  }

  function resolveProfileCta(type, profileState) {
    const exists = profileState && profileState.exists;
    const status = profileState && profileState.status ? String(profileState.status).toLowerCase() : '';
    const builderUrl = profileState && profileState.builder_url ? String(profileState.builder_url) : '';

    let label = TEXT.profileEdit;

    if (!exists) {
      label = TEXT.profileCreate;
    } else if (status === 'publish' || status === 'published') {
      label = TEXT.profileEdit;
    } else {
      label = TEXT.profileFinish;
    }

    return {
      label,
      url: builderUrl || '#',
      disabled: builderUrl === '',
    };
  }

  function buildUserDashboardUpgradeSection(state, upgradeData) {
    const section = document.createElement('section');
    section.className = 'ap-dashboard-section ap-dashboard-section--upgrades';

    const header = document.createElement('header');
    header.className = 'ap-dashboard-section__header';

    const title = document.createElement('h3');
    title.textContent = TEXT.upgradeSectionTitle;
    header.appendChild(title);

    if (TEXT.upgradeDescription) {
      const intro = document.createElement('p');
      intro.textContent = TEXT.upgradeDescription;
      header.appendChild(intro);
    }

    section.appendChild(header);

    const card = document.createElement('article');
    card.className = 'ap-dashboard-card ap-user-dashboard__upgrade-card';

    const body = document.createElement('div');
    body.className = 'ap-dashboard-card__body';

    const heading = document.createElement('h4');
    heading.textContent = TEXT.upgradeCardTitle;
    body.appendChild(heading);

    card.appendChild(body);

    const actions = document.createElement('div');
    actions.className = 'ap-dashboard-card__actions ap-user-dashboard__upgrade-actions';

    const artistAction = createUpgradeAction(state, 'artist', upgradeData);
    const orgAction = createUpgradeAction(state, 'org', upgradeData);

    actions.appendChild(artistAction.wrapper);
    actions.appendChild(orgAction.wrapper);

    card.appendChild(actions);

    section.appendChild(card);

    return section;
  }

  function createUpgradeAction(state, type, upgradeData) {
    const wrapper = document.createElement('div');
    wrapper.className = 'ap-user-dashboard__upgrade-option';
    wrapper.dataset.apUpgradeType = type;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ap-dashboard-button ap-dashboard-button--primary';
    button.textContent = type === 'artist' ? TEXT.upgradeArtistCta : TEXT.upgradeOrgCta;

    const chip = document.createElement('span');
    chip.className = 'ap-dashboard-badge';
    chip.hidden = true;

    const status = determineUpgradeStatus(state, type, upgradeData);
    const canRequest = !upgradeData || !upgradeData.can_request || upgradeData.can_request[type] !== false;
    const disabled = !canRequest || Boolean(status);

    applyUpgradeStatusChip(chip, status || (canRequest ? '' : 'unavailable'));

    if (disabled) {
      disableButton(button);
    }

    button.addEventListener('click', (event) => {
      event.preventDefault();
      handleUpgradeClick(state, {
        type,
        button,
        chip,
        canRequest,
        previousStatus: status || '',
      });
    });

    wrapper.appendChild(button);
    wrapper.appendChild(chip);

    return { wrapper, button, chip };
  }

  function determineUpgradeStatus(state, type, upgradeData) {
    if (state && state.optimisticUpgrades && state.optimisticUpgrades[type]) {
      return state.optimisticUpgrades[type];
    }

    const requests = upgradeData && Array.isArray(upgradeData.requests) ? upgradeData.requests : [];
    let latest = null;

    requests.forEach((request) => {
      if (!request || request.type !== type) {
        return;
      }

      const status = request.status ? String(request.status).toLowerCase() : '';
      const timestamp = request.created_at ? Date.parse(request.created_at) : 0;

      if (!latest || timestamp > latest.timestamp) {
        latest = {
          status,
          timestamp: Number.isNaN(timestamp) ? 0 : timestamp,
        };
      }
    });

    return latest ? latest.status : '';
  }

  function applyUpgradeStatusChip(chip, status) {
    if (!chip) {
      return;
    }

    chip.className = 'ap-dashboard-badge';

    if (!status) {
      chip.hidden = true;
      chip.textContent = '';
      return;
    }

    let label = status;
    let variant = 'muted';

    switch (status) {
      case 'pending':
        label = TEXT.upgradePending;
        variant = 'info';
        break;
      case 'approved':
        label = TEXT.upgradeApproved;
        variant = 'success';
        break;
      case 'denied':
      case 'rejected':
        label = TEXT.upgradeDenied;
        variant = 'danger';
        break;
      case 'unavailable':
        label = TEXT.upgradeUnavailable;
        variant = 'muted';
        break;
      default:
        label = status;
        variant = 'muted';
        break;
    }

    chip.classList.add(`ap-dashboard-badge--${variant}`);
    chip.textContent = label;
    chip.hidden = false;
  }

  function disableButton(button) {
    if (!button) {
      return;
    }

    button.disabled = true;
    button.classList.add('is-disabled');
    button.setAttribute('aria-disabled', 'true');
  }

  function enableButton(button) {
    if (!button) {
      return;
    }

    button.disabled = false;
    button.classList.remove('is-disabled');
    button.removeAttribute('aria-disabled');
  }

  function disableLinkButton(link) {
    if (!link) {
      return;
    }

    link.classList.add('is-disabled');
    link.setAttribute('aria-disabled', 'true');
    link.addEventListener('click', (event) => {
      event.preventDefault();
    });
  }

  function handleUpgradeClick(state, context) {
    if (!context || !context.button || !context.canRequest) {
      return;
    }

    if (context.button.dataset.apBusy === '1' || context.button.disabled) {
      return;
    }

    context.button.dataset.apBusy = '1';
    disableButton(context.button);
    clearUserDashboardNotice(state);

    state.optimisticUpgrades = state.optimisticUpgrades || {};
    state.optimisticUpgrades[context.type] = 'pending';
    applyUpgradeStatusChip(context.chip, 'pending');

    postUpgradeReview(context.type)
      .then(() => {
        delete state.optimisticUpgrades[context.type];
        loadUserDashboard(state, { initial: false });
      })
      .catch((error) => {
        delete state.optimisticUpgrades[context.type];
        applyUpgradeStatusChip(
          context.chip,
          context.previousStatus || (context.canRequest ? '' : 'unavailable')
        );

        if (context.canRequest && !context.previousStatus) {
          enableButton(context.button);
        }

        showUserDashboardNotice(state, 'warning', extractErrorMessage(error) || TEXT.upgradeError);
      })
      .finally(() => {
        delete context.button.dataset.apBusy;
      });
  }

  function ensureNotice(state) {
    if (state && state.notice && state.notice.parentNode === state.container) {
      return state.notice;
    }

    const notice = state && state.notice ? state.notice : createNoticeElement();
    state.notice = notice;

    if (state && state.container && notice.parentNode !== state.container) {
      state.container.insertBefore(notice, state.container.firstChild || null);
    }

    return notice;
  }

  function createNoticeElement() {
    const notice = document.createElement('div');
    notice.className = 'ap-dashboard-notice';
    notice.setAttribute('role', 'status');
    notice.setAttribute('aria-live', 'polite');
    notice.hidden = true;
    return notice;
  }

  function showUserDashboardNotice(state, variant, message) {
    if (!message) {
      clearUserDashboardNotice(state);
      return;
    }

    const notice = ensureNotice(state);
    if (!notice) {
      return;
    }

    notice.className = 'ap-dashboard-notice';

    if (variant) {
      notice.classList.add(`ap-dashboard-notice--${variant}`);
    }

    notice.innerHTML = `<p>${escapeHtml(message)}</p>`;
    notice.hidden = false;
  }

  function clearUserDashboardNotice(state) {
    if (!state || !state.notice) {
      return;
    }

    state.notice.className = 'ap-dashboard-notice';
    state.notice.innerHTML = '';
    state.notice.hidden = true;
  }

  function clearContainer(node) {
    if (!node) {
      return;
    }

    while (node.firstChild) {
      node.removeChild(node.firstChild);
    }
  }

  function init() {
    const scope = document;
    initDashboards(scope);
    initProfileForms(scope);
  }

  function initDashboards(scope) {
    const containers = scope.querySelectorAll('.ap-user-dashboard[data-ap-dashboard-role], .ap-dashboard-widget[data-ap-dashboard-role]');

    containers.forEach((container) => {
      if (!container) {
        return;
      }

      if (!container.classList.contains('ap-role-dashboard')) {
        container.classList.add('ap-role-dashboard');
      }

      if (container.dataset.apUserDashboardBound === '1') {
        initProfileForms(container);
        return;
      }

      container.dataset.apUserDashboardBound = '1';

      if (container.classList.contains('ap-user-dashboard')) {
        bindUserDashboard(container);
      } else if (window.ArtPulseDashboardsApp && typeof window.ArtPulseDashboardsApp.init === 'function') {
        window.ArtPulseDashboardsApp.init(container);
      }

      initProfileForms(container);
    });
  }

  function initProfileForms(scope) {
    const forms = scope.querySelectorAll('form[data-ap-dashboard-profile-form], form.ap-dashboard-profile-form');

    forms.forEach((form) => {
      bindProfileForm(form);
    });
  }

  function bindProfileForm(form) {
    if (!form || form.dataset.apDashboardProfileBound === '1') {
      return;
    }

    form.dataset.apDashboardProfileBound = '1';

    form.addEventListener('submit', (event) => {
      event.preventDefault();

      const payload = collectPayload(form);

      if (!payload.profile && !payload.membership) {
        showNotice(form, 'error', STRINGS.emptyProfile || 'Please provide profile details to update.');
        return;
      }

      setFormLoading(form, true);
      showNotice(form, null, '');

      submitProfile(payload)
        .then((response) => {
          setFormLoading(form, false);

          if (!response || response.success !== true) {
            const message = response && response.message ? response.message : 'Unable to update profile.';
            showNotice(form, 'error', message);
            return;
          }

          const container = form.closest('[data-ap-dashboard-role]') || document;

          if (response.profile) {
            applyProfile(container, response.profile);
          }

          updateFormFields(form, response);

          showNotice(
            form,
            'success',
            response.message || STRINGS.profileUpdated || 'Profile updated successfully.'
          );
        })
        .catch((error) => {
          setFormLoading(form, false);
          showNotice(form, 'error', extractErrorMessage(error));
        });
    });
  }

  function collectPayload(form) {
    const data = new FormData(form);
    const profile = {};
    const membership = {};
    let hasProfile = false;

    const displayName = data.has('display_name') ? data.get('display_name') : null;
    if (displayName !== null) {
      profile.display_name = String(displayName).trim();
      hasProfile = true;
    }

    const firstName = data.has('first_name') ? data.get('first_name') : null;
    if (firstName !== null) {
      profile.first_name = String(firstName).trim();
      hasProfile = true;
    }

    const lastName = data.has('last_name') ? data.get('last_name') : null;
    if (lastName !== null) {
      profile.last_name = String(lastName).trim();
      hasProfile = true;
    }

    const bioField = data.has('biography')
      ? 'biography'
      : data.has('bio')
        ? 'bio'
        : data.has('description')
          ? 'description'
          : null;

    if (bioField) {
      const bioValue = data.get(bioField);
      profile.biography = bioValue === null ? '' : String(bioValue);
      hasProfile = true;
    }

    const websiteField = data.has('website')
      ? 'website'
      : data.has('user_url')
        ? 'user_url'
        : data.has('ap_social_website')
          ? 'ap_social_website'
          : null;

    if (websiteField) {
      const websiteValue = data.get(websiteField);
      profile.website = websiteValue === null ? '' : String(websiteValue).trim();
      hasProfile = true;
    }

    const social = {};
    const socialFieldMap = {
      ap_social_twitter: 'twitter',
      ap_social_instagram: 'instagram',
      ap_social_website: 'website',
      'social[twitter]': 'twitter',
      'social[instagram]': 'instagram',
      'social[website]': 'website',
    };

    Object.keys(socialFieldMap).forEach((field) => {
      if (!data.has(field)) {
        return;
      }

      const value = data.get(field);
      social[socialFieldMap[field]] = value === null ? '' : String(value).trim();
    });

    if (Object.keys(social).length) {
      profile.social = social;
      hasProfile = true;
    }

    const membershipLevel = data.has('membership_level')
      ? data.get('membership_level')
      : data.has('ap_membership_level')
        ? data.get('ap_membership_level')
        : data.get('membership[level]');

    if (membershipLevel !== null && membershipLevel !== undefined) {
      membership.level = String(membershipLevel).trim();
    }

    const membershipExpires = data.has('membership_expires')
      ? data.get('membership_expires')
      : data.has('ap_membership_expires')
        ? data.get('ap_membership_expires')
        : data.get('membership[expires]');

    if (membershipExpires !== null && membershipExpires !== undefined) {
      membership.expires = String(membershipExpires).trim();
    }

    const payload = {};

    if (hasProfile) {
      payload.profile = profile;
    }

    if (Object.keys(membership).length) {
      payload.membership = membership;
    }

    const container = form.closest('[data-ap-dashboard-role]');
    if (container && container.dataset && container.dataset.apDashboardRole) {
      payload.role = container.dataset.apDashboardRole;
    }

    return payload;
  }

  function submitProfile(payload) {
    if (window.wp && window.wp.apiFetch) {
      return window.wp.apiFetch({
        path: '/artpulse/v1/user/profile',
        method: 'POST',
        data: payload,
        headers: { 'X-WP-Nonce': API_SETTINGS.nonce },
      });
    }

    const settings = window.wpApiSettings || {};
    const root = API_SETTINGS.root || settings.root || '';
    const nonce = API_SETTINGS.nonce || settings.nonce || '';
    const url = root.replace(/\/?$/, '/') + 'artpulse/v1/user/profile';

    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
      },
      body: JSON.stringify(payload),
    }).then((response) => {
      if (response.ok) {
        return response.json();
      }

      return response
        .json()
        .catch(() => ({}))
        .then((data) => {
          const error = new Error(data && data.message ? data.message : 'Request failed');
          error.data = data;
          throw error;
        });
    });
  }

  function showNotice(form, type, message) {
    let container = form.querySelector('[data-ap-dashboard-profile-status]');

    if (!container) {
      container = document.createElement('div');
      container.setAttribute('data-ap-dashboard-profile-status', '1');
      container.className = 'ap-dashboard-notice';
      container.hidden = true;
      form.insertBefore(container, form.firstChild);
    }

    container.className = 'ap-dashboard-notice';

    if (type) {
      container.classList.add(`ap-dashboard-notice--${type}`);
    }

    if (message) {
      container.innerHTML = `<p>${escapeHtml(message)}</p>`;
      container.hidden = false;
      container.style.display = '';
    } else {
      container.innerHTML = '';
      container.hidden = true;
      container.style.display = 'none';
    }
  }

  function setFormLoading(form, isLoading) {
    if (!form) {
      return;
    }

    const loading = Boolean(isLoading);
    form.classList.toggle('is-loading', loading);
    form.setAttribute('aria-busy', loading ? 'true' : 'false');

    const submit = form.querySelector('[type="submit"]');
    if (submit) {
      submit.disabled = loading;
    }
  }

  function extractErrorMessage(error) {
    if (!error) {
      return STRINGS.profileError || 'Unable to update profile.';
    }

    if (typeof error === 'string') {
      return error;
    }

    if (error.message) {
      return error.message;
    }

    if (error.data && error.data.message) {
      return error.data.message;
    }

    return STRINGS.profileError || 'Unable to update profile.';
  }

  function applyProfile(scope, profile) {
    if (!scope || !profile) {
      return;
    }

    const heroes = scope.querySelectorAll('.ap-dashboard-hero');
    heroes.forEach((hero) => {
      updateHero(hero, profile);
    });

    const cards = scope.querySelectorAll('.ap-dashboard-card.ap-dashboard-profile');
    cards.forEach((card) => {
      updateProfileCard(card, profile);
    });
  }

  function updateHero(hero, profile) {
    if (!hero) {
      return;
    }

    const nameEl = hero.querySelector('.ap-dashboard-hero__name');
    if (nameEl && Object.prototype.hasOwnProperty.call(profile, 'display_name')) {
      nameEl.textContent = profile.display_name || '';
    }

    const emailWrap = hero.querySelector('.ap-dashboard-hero__email');
    if (emailWrap) {
      const email = profile.email || '';

      if (email) {
        let link = emailWrap.querySelector('a');

        if (!link) {
          link = document.createElement('a');
          emailWrap.innerHTML = '';
          emailWrap.appendChild(link);
        }

        link.href = `mailto:${email}`;
        link.textContent = email;
        emailWrap.hidden = false;
        emailWrap.style.display = '';
      } else {
        emailWrap.innerHTML = '';
        emailWrap.hidden = true;
        emailWrap.style.display = 'none';
      }
    }

    const membershipEl = hero.querySelector('.ap-dashboard-hero__membership');
    if (membershipEl) {
      membershipEl.innerHTML = '';

      const membership = profile.membership || {};
      const level = membership.level || '';
      const renewalText = membership.renewal_label || (membership.expires_display ? `Renews ${membership.expires_display}` : '');

      if (level) {
        const badge = document.createElement('span');
        badge.className = 'ap-dashboard-badge ap-dashboard-badge--tier';
        badge.textContent = level;
        membershipEl.appendChild(badge);
      }

      if (renewalText) {
        const meta = document.createElement('span');
        meta.className = 'ap-dashboard-hero__meta';
        meta.textContent = renewalText;
        membershipEl.appendChild(meta);
      }

      membershipEl.hidden = membershipEl.childNodes.length === 0;
      membershipEl.style.display = membershipEl.hidden ? 'none' : '';
    }

    const bioEl = hero.querySelector('.ap-dashboard-hero__bio');
    if (bioEl) {
      const rawBio = profile.bio || profile.biography || '';
      const truncated = truncateWords(stripTags(rawBio), 40);

      if (truncated) {
        bioEl.textContent = truncated;
        bioEl.hidden = false;
        bioEl.style.display = '';
      } else {
        bioEl.textContent = '';
        bioEl.hidden = true;
        bioEl.style.display = 'none';
      }
    }

    const media = hero.querySelector('.ap-dashboard-hero__media');
    if (media) {
      const avatarUrl = profile.avatar || '';
      let image = media.querySelector('img.ap-dashboard-hero__avatar');
      let placeholder = media.querySelector('.ap-dashboard-hero__avatar--placeholder');

      if (avatarUrl) {
        if (!image) {
          image = document.createElement('img');
          image.className = 'ap-dashboard-hero__avatar';
          image.alt = '';
          image.loading = 'lazy';
          image.decoding = 'async';
          media.appendChild(image);
        }

        image.src = avatarUrl;
        image.hidden = false;
        image.style.display = '';

        if (placeholder) {
          placeholder.remove();
        }
      } else {
        if (image) {
          image.remove();
        }

        if (!placeholder) {
          placeholder = document.createElement('div');
          placeholder.className = 'ap-dashboard-hero__avatar ap-dashboard-hero__avatar--placeholder';
          placeholder.setAttribute('aria-hidden', 'true');
          media.appendChild(placeholder);
        }
      }
    }
  }

  function updateProfileCard(card, profile) {
    if (!card) {
      return;
    }

    const membership = profile.membership || {};
    const levelMarkup = membership.level
      ? `<p class="ap-dashboard-profile__membership"><strong>${escapeHtml(membership.level)}</strong></p>`
      : '';
    const renewal = membership.renewal_label || (membership.expires_display ? `${STRINGS.updated || 'Updated'}: ${membership.expires_display}` : '');
    const renewalMarkup = renewal
      ? `<p class="ap-dashboard-profile__expires">${escapeHtml(renewal)}</p>`
      : '';
    const profileLink = profile.profile_url
      ? `<p><a class="ap-dashboard-profile__link" href="${escapeAttribute(profile.profile_url)}">${escapeHtml(STRINGS.viewProfile || 'View profile')}</a></p>`
      : '';
    const emailMarkup = profile.email
      ? `<p class="ap-dashboard-profile__email">${escapeHtml(profile.email)}</p>`
      : '';
    const avatarMarkup = profile.avatar
      ? `<img class="ap-dashboard-profile__avatar" src="${escapeAttribute(profile.avatar)}" alt="${escapeAttribute(profile.display_name || '')}">`
      : '';
    const bioMarkup = profile.bio
      ? `<p class="ap-dashboard-profile__bio">${escapeHtml(profile.bio)}</p>`
      : '';

    card.innerHTML = `
      <div class="ap-dashboard-profile__header">
        ${avatarMarkup}
        <div class="ap-dashboard-profile__content">
          <h3 class="ap-dashboard-profile__name">${escapeHtml(profile.display_name || '')}</h3>
          ${emailMarkup}
          ${levelMarkup}
          ${renewalMarkup}
          ${profileLink}
        </div>
      </div>
      ${bioMarkup}
    `;
  }

  function updateFormFields(form, response) {
    if (!form || !response) {
      return;
    }

    const fields = response.fields || {};
    const membershipFields = response.membership_fields || {};

    if (Object.prototype.hasOwnProperty.call(fields, 'display_name')) {
      const input = form.querySelector('[name="display_name"]');
      if (input) {
        input.value = fields.display_name || '';
      }
    }

    if (Object.prototype.hasOwnProperty.call(fields, 'first_name')) {
      const input = form.querySelector('[name="first_name"]');
      if (input) {
        input.value = fields.first_name || '';
      }
    }

    if (Object.prototype.hasOwnProperty.call(fields, 'last_name')) {
      const input = form.querySelector('[name="last_name"]');
      if (input) {
        input.value = fields.last_name || '';
      }
    }

    if (Object.prototype.hasOwnProperty.call(fields, 'biography')) {
      const textarea = form.querySelector('[name="biography"], [name="bio"], [name="description"]');
      if (textarea) {
        textarea.value = fields.biography || '';
      }
    }

    if (Object.prototype.hasOwnProperty.call(fields, 'website')) {
      const input = form.querySelector('[name="website"], [name="user_url"], [name="ap_social_website"]');
      if (input) {
        input.value = fields.website || '';
      }
    }

    if (fields.social) {
      const socialSelectors = {
        twitter: '[name="ap_social_twitter"], [name="social[twitter]"]',
        instagram: '[name="ap_social_instagram"], [name="social[instagram]"]',
        website: '[name="ap_social_website"], [name="social[website]"]',
      };

      Object.keys(fields.social).forEach((key) => {
        const selector = socialSelectors[key];
        if (!selector) {
          return;
        }

        const input = form.querySelector(selector);
        if (input) {
          input.value = fields.social[key] || '';
        }
      });
    }

    if (Object.prototype.hasOwnProperty.call(membershipFields, 'level')) {
      const input = form.querySelector('[name="membership_level"], [name="ap_membership_level"], [name="membership[level]"]');
      if (input) {
        input.value = membershipFields.level || '';
      }
    }

    if (Object.prototype.hasOwnProperty.call(membershipFields, 'expires')) {
      const input = form.querySelector('[name="membership_expires"], [name="ap_membership_expires"], [name="membership[expires]"]');
      if (input) {
        const expires = membershipFields.expires;
        if (!expires) {
          input.value = '';
        } else if (input.type === 'date') {
          const date = new Date(Number(expires) * 1000);
          if (!Number.isNaN(date.getTime())) {
            input.value = date.toISOString().slice(0, 10);
          }
        } else {
          input.value = expires;
        }
      }
    }
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) {
      return '';
    }

    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, '&#x60;');
  }

  function stripTags(value) {
    if (!value) {
      return '';
    }

    const div = document.createElement('div');
    div.innerHTML = value;
    return div.textContent || div.innerText || '';
  }

  function truncateWords(value, limit) {
    if (!value) {
      return '';
    }

    const words = value.trim().split(/\s+/);

    if (words.length <= limit) {
      return words.join(' ');
    }

    return `${words.slice(0, limit).join(' ')}…`;
  }

  onReady(init);
})();
