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
    },
    boot.i18n || {}
  );

  renderSpinner();

  if (boot.role !== 'member') {
    root.innerHTML = '';
    return;
  }

  const endpointRoot = boot?.endpoints?.root || '';
  if (!endpointRoot) {
    renderError();
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
