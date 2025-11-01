(function () {
  const boot = window.AP_BOOT || {};
  const root = document.getElementById('ap-dashboard');
  if (!root) {
    return;
  }

  const i18n = Object.assign(
    {
      loading: 'Loading…',
      dashboardError: 'We could not load your dashboard. Please try again.',
      favorites: 'Favorites',
      follows: 'Follows',
      upcoming: 'Upcoming',
      notifications: 'Notifications',
      upcomingEvents: 'Upcoming Events',
      noUpcoming: 'No upcoming events yet.',
      artworks: 'Artworks',
      events: 'Events',
      recentArtworks: 'Recent Artworks',
      recentEvents: 'Recent Events',
      noArtworks: 'No artworks yet.',
      noEvents: 'No events yet.',
      edit: 'Edit',
      view: 'View',
      untitled: '(No title)',
    },
    boot.i18n || {}
  );

  renderSpinner();

  const endpointRoot = boot?.endpoints?.root || '';
  if (!endpointRoot) {
    renderError();
    return;
  }

  if (boot.role === 'artist') {
    fetchJSON(endpointRoot + 'artist/overview')
      .then(renderArtistOverview)
      .catch(renderError);
    return;
  }

  if (boot.role !== 'member') {
    root.innerHTML = '';
    return;
  }

  fetchJSON(endpointRoot + 'me/overview')
    .then(renderMemberOverview)
    .catch(renderError);

  function renderSpinner() {
    root.innerHTML = '';
    const spinner = document.createElement('div');
    spinner.className = 'ap-spinner';
    spinner.textContent = i18n.loading;
    root.appendChild(spinner);
  }

  function renderError() {
    root.innerHTML = '';
    const message = document.createElement('p');
    message.className = 'ap-dashboard__error';
    message.textContent = i18n.dashboardError;
    root.appendChild(message);
  }

  function renderMemberOverview(data) {
    root.innerHTML = '';

    const counts = data && typeof data === 'object' ? data.counts || {} : {};
    const events = Array.isArray(data?.nextEvents) ? data.nextEvents : [];

    const statsWrapper = document.createElement('div');
    statsWrapper.className = 'ap-dashboard__stats';

    const statMap = [
      { key: 'favorites', label: i18n.favorites },
      { key: 'follows', label: i18n.follows },
      { key: 'upcoming', label: i18n.upcoming },
      { key: 'notifications', label: i18n.notifications },
    ];

    statMap.forEach((item) => {
      const rawValue = counts[item.key];
      const numeric = typeof rawValue === 'number' ? rawValue : parseInt(rawValue, 10);
      const value = Number.isFinite(numeric) ? numeric : 0;
      const card = document.createElement('div');
      card.className = 'ap-dashboard__stat';

      const valueEl = document.createElement('span');
      valueEl.className = 'ap-dashboard__stat-value';
      valueEl.textContent = String(value);

      const labelEl = document.createElement('span');
      labelEl.className = 'ap-dashboard__stat-label';
      labelEl.textContent = item.label;

      card.appendChild(valueEl);
      card.appendChild(labelEl);
      statsWrapper.appendChild(card);
    });

    const upcomingWrapper = document.createElement('div');
    upcomingWrapper.className = 'ap-dashboard__upcoming';

    const heading = document.createElement('h2');
    heading.className = 'ap-dashboard__section-title';
    heading.textContent = i18n.upcomingEvents;
    upcomingWrapper.appendChild(heading);

    const list = document.createElement('ul');
    list.className = 'ap-dashboard__event-list';

    if (events.length === 0) {
      const empty = document.createElement('li');
      empty.className = 'ap-dashboard__event ap-dashboard__event--empty';
      empty.textContent = i18n.noUpcoming;
      list.appendChild(empty);
    } else {
      events.forEach((event) => {
        const li = document.createElement('li');
        li.className = 'ap-dashboard__event';

        const link = document.createElement('a');
        link.className = 'ap-dashboard__event-link';
        link.textContent = event?.title || '';
        if (event?.permalink) {
          link.href = event.permalink;
        } else {
          link.href = '#';
        }

        const dateText = formatDate(event?.date);
        const meta = document.createElement('span');
        meta.className = 'ap-dashboard__event-date';
        meta.textContent = dateText;

        li.appendChild(link);
        if (dateText) {
          li.appendChild(meta);
        }
        list.appendChild(li);
      });
    }

    upcomingWrapper.appendChild(list);

    root.appendChild(statsWrapper);
    root.appendChild(upcomingWrapper);
  }

  function renderArtistOverview(data) {
    root.innerHTML = '';

    const counts = data && typeof data === 'object' ? data.counts || {} : {};
    const recent = data && typeof data === 'object' ? data.recent || {} : {};

    const statsWrapper = document.createElement('div');
    statsWrapper.className = 'ap-dashboard__stats';

    const statMap = [
      { key: 'artworks', label: i18n.artworks },
      { key: 'events', label: i18n.events },
      { key: 'favorites', label: i18n.favorites },
      { key: 'follows', label: i18n.follows },
    ];

    statMap.forEach((item) => {
      const rawValue = counts[item.key];
      const numeric = typeof rawValue === 'number' ? rawValue : parseInt(rawValue, 10);
      const value = Number.isFinite(numeric) ? numeric : 0;
      const card = document.createElement('div');
      card.className = 'ap-dashboard__stat';

      const valueEl = document.createElement('span');
      valueEl.className = 'ap-dashboard__stat-value';
      valueEl.textContent = String(value);

      const labelEl = document.createElement('span');
      labelEl.className = 'ap-dashboard__stat-label';
      labelEl.textContent = item.label;

      card.appendChild(valueEl);
      card.appendChild(labelEl);
      statsWrapper.appendChild(card);
    });

    const listsWrapper = document.createElement('div');
    listsWrapper.className = 'ap-dashboard__lists';

    const artworks = Array.isArray(recent?.artworks) ? recent.artworks : [];
    const events = Array.isArray(recent?.events) ? recent.events : [];

    listsWrapper.appendChild(
      buildRecentList(i18n.recentArtworks, artworks, i18n.noArtworks)
    );
    listsWrapper.appendChild(
      buildRecentList(i18n.recentEvents, events, i18n.noEvents)
    );

    root.appendChild(statsWrapper);
    root.appendChild(listsWrapper);
  }

  function buildRecentList(title, items, emptyText) {
    const section = document.createElement('div');
    section.className = 'ap-dashboard__panel';

    const heading = document.createElement('h2');
    heading.className = 'ap-dashboard__section-title';
    heading.textContent = title;
    section.appendChild(heading);

    const list = document.createElement('ul');
    list.className = 'ap-dashboard__item-list';

    if (!Array.isArray(items) || items.length === 0) {
      const empty = document.createElement('li');
      empty.className = 'ap-dashboard__item ap-dashboard__item--empty';
      empty.textContent = emptyText;
      list.appendChild(empty);
    } else {
      items.forEach((item) => {
        const li = document.createElement('li');
        li.className = 'ap-dashboard__item';

        const link = document.createElement('a');
        link.className = 'ap-dashboard__item-link';
        link.textContent = item?.title || i18n.untitled;
        if (item?.edit_link) {
          link.href = item.edit_link;
        } else if (item?.permalink) {
          link.href = item.permalink;
        } else {
          link.href = '#';
        }

        li.appendChild(link);

        const actions = document.createElement('div');
        actions.className = 'ap-dashboard__item-actions';

        if (item?.edit_link) {
          const editLink = document.createElement('a');
          editLink.className = 'ap-dashboard__item-action';
          editLink.textContent = i18n.edit;
          editLink.href = item.edit_link;
          actions.appendChild(editLink);
        }

        if (item?.permalink) {
          const viewLink = document.createElement('a');
          viewLink.className = 'ap-dashboard__item-action';
          viewLink.textContent = i18n.view;
          viewLink.href = item.permalink;
          viewLink.target = '_blank';
          viewLink.rel = 'noopener noreferrer';
          actions.appendChild(viewLink);
        }

        if (actions.childNodes.length > 0) {
          li.appendChild(actions);
        }

        list.appendChild(li);
      });
    }

    section.appendChild(list);
    return section;
  }

  function formatDate(value) {
    if (!value) {
      return '';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
      return '';
    }

    try {
      return parsed.toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
      });
    } catch (e) {
      return parsed.toISOString();
    }
  }

  function fetchJSON(url) {
    const headers = { Accept: 'application/json' };
    if (boot?.nonces?.rest) {
      headers['X-WP-Nonce'] = boot.nonces.rest;
    }

    return fetch(url, {
      headers,
      credentials: 'same-origin',
    }).then((response) => {
      if (!response.ok) {
        throw new Error('Network error');
      }
      return response.json();
    });
  }
})();
