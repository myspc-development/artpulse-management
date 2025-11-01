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
      artists: 'Artists',
      events: 'Events',
      recentArtworks: 'Recent Artworks',
      recentEvents: 'Recent Events',
      noArtworks: 'No artworks yet.',
      noEvents: 'No events yet.',
      submissions: 'Submissions',
      recentSubmissions: 'Recent Submissions',
      noSubmissions: 'No submissions yet.',
      rosterPreview: 'Team Members',
      noRoster: 'No members yet.',
      edit: 'Edit',
      view: 'View',
      untitled: '(No title)',
      analytics: 'Analytics',
      views: 'Views',
      rsvps: 'RSVPs',
      range7: '7 days',
      range30: '30 days',
      range90: '90 days',
      chartEmpty: 'No analytics available yet.',
      analyticsPrimary: 'Views over time',
    },
    boot.i18n || {}
  );

  const ANALYTICS_METRICS = [
    { key: 'views', label: i18n.views },
    { key: 'favorites', label: i18n.favorites },
    { key: 'follows', label: i18n.follows },
    { key: 'rsvps', label: i18n.rsvps },
  ];
  const ANALYTICS_RANGES = [7, 30, 90];

  renderSpinner();

  const endpointRoot = boot?.endpoints?.root || '';
  if (!endpointRoot) {
    renderError();
    return;
  }

  if (boot.role === 'org') {
    fetchJSON(endpointRoot + 'org/overview')
      .then((data) => {
        renderOrgOverview(data);
        return fetchJSON(endpointRoot + 'org/roster?per_page=5')
          .then((roster) => {
            renderRosterPreview(roster);
          })
          .catch(() => {
            renderRosterPreview(null);
          });
      })
      .catch(renderError);
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
    const memberAnalytics = buildAnalyticsPanel('member');
    if (memberAnalytics) {
      root.appendChild(memberAnalytics);
    }
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
    const artistAnalytics = buildAnalyticsPanel('artist');
    if (artistAnalytics) {
      root.appendChild(artistAnalytics);
    }
    root.appendChild(listsWrapper);
  }

  function renderOrgOverview(data) {
    root.innerHTML = '';

    const counts = data && typeof data === 'object' ? data.counts || {} : {};
    const recent = data && typeof data === 'object' ? data.recent || {} : {};

    const statsWrapper = document.createElement('div');
    statsWrapper.className = 'ap-dashboard__stats';

    const statMap = [
      { key: 'events', label: i18n.events },
      { key: 'artists', label: i18n.artists },
      { key: 'submissions', label: i18n.submissions },
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

    const recentEvents = Array.isArray(recent?.events) ? recent.events : [];
    const recentSubmissions = Array.isArray(recent?.submissions)
      ? recent.submissions
      : [];

    listsWrapper.appendChild(
      buildRecentList(i18n.recentEvents, recentEvents, i18n.noEvents)
    );
    listsWrapper.appendChild(
      buildRecentList(
        i18n.recentSubmissions,
        recentSubmissions,
        i18n.noSubmissions
      )
    );

    const rosterPanel = document.createElement('div');
    rosterPanel.className = 'ap-dashboard__panel';
    rosterPanel.id = 'ap-dashboard-roster-preview';

    const rosterHeading = document.createElement('h2');
    rosterHeading.className = 'ap-dashboard__section-title';
    rosterHeading.textContent = i18n.rosterPreview;
    rosterPanel.appendChild(rosterHeading);

    const rosterContainer = document.createElement('div');
    rosterContainer.className = 'ap-dashboard__roster';
    rosterContainer.textContent = i18n.loading;
    rosterPanel.appendChild(rosterContainer);

    root.appendChild(statsWrapper);
    const orgAnalytics = buildAnalyticsPanel('org');
    if (orgAnalytics) {
      root.appendChild(orgAnalytics);
    }
    root.appendChild(listsWrapper);
    root.appendChild(rosterPanel);
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

  function buildAnalyticsPanel(role) {
    if (!endpointRoot) {
      return null;
    }

    const panel = document.createElement('div');
    panel.className = 'ap-dashboard__panel ap-dashboard__analytics';
    panel.dataset.role = role;

    const heading = document.createElement('h2');
    heading.className = 'ap-dashboard__section-title';
    heading.textContent = i18n.analytics;

    const controls = document.createElement('div');
    controls.className = 'ap-dashboard__analytics-controls';

    const header = document.createElement('div');
    header.className = 'ap-dashboard__analytics-header';
    header.appendChild(heading);
    header.appendChild(controls);
    panel.appendChild(header);

    const status = document.createElement('div');
    status.className = 'ap-dashboard__analytics-status';
    panel.appendChild(status);

    const totals = document.createElement('div');
    totals.className = 'ap-dashboard__stats ap-dashboard__stats--analytics';
    panel.appendChild(totals);

    const chart = document.createElement('div');
    chart.className = 'ap-dashboard__analytics-chart';
    panel.appendChild(chart);

    const defaultRange = ANALYTICS_RANGES.includes(30) ? 30 : ANALYTICS_RANGES[0];
    const buttons = [];
    let requestToken = 0;

    ANALYTICS_RANGES.forEach((range) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'ap-dashboard__analytics-range';
      button.textContent = formatRangeLabel(range);
      button.dataset.range = String(range);
      if (range === defaultRange) {
        button.classList.add('is-active');
      }
      button.addEventListener('click', () => {
        if (button.classList.contains('is-active')) {
          return;
        }
        setRange(range);
      });
      controls.appendChild(button);
      buttons.push(button);
    });

    function setRange(range) {
      buttons.forEach((btn) => {
        if (Number(btn.dataset.range) === range) {
          btn.classList.add('is-active');
        } else {
          btn.classList.remove('is-active');
        }
      });

      panel.classList.add('is-loading');
      status.textContent = i18n.loading;
      totals.innerHTML = '';
      chart.innerHTML = '';
      panel.dataset.range = String(range);

      requestToken += 1;
      const token = requestToken;

      fetchAnalyticsData(role, range)
        .then((data) => {
          if (token !== requestToken) {
            return;
          }
          panel.classList.remove('is-loading');
          status.textContent = '';
          renderAnalyticsTotals(totals, data?.totals);
          renderAnalyticsChart(chart, data?.series);
        })
        .catch(() => {
          if (token !== requestToken) {
            return;
          }
          panel.classList.remove('is-loading');
          status.textContent = i18n.dashboardError;
        });
    }

    setRange(defaultRange);

    return panel;
  }

  function renderAnalyticsTotals(container, totals) {
    container.innerHTML = '';

    ANALYTICS_METRICS.forEach((metric) => {
      const rawValue = totals ? totals[metric.key] : 0;
      const numeric = typeof rawValue === 'number' ? rawValue : parseInt(rawValue, 10);
      const value = Number.isFinite(numeric) ? numeric : 0;

      const card = document.createElement('div');
      card.className = 'ap-dashboard__stat ap-dashboard__stat--analytics';

      const valueEl = document.createElement('span');
      valueEl.className = 'ap-dashboard__stat-value';
      try {
        valueEl.textContent = value.toLocaleString();
      } catch (e) {
        valueEl.textContent = String(value);
      }

      const labelEl = document.createElement('span');
      labelEl.className = 'ap-dashboard__stat-label';
      labelEl.textContent = metric.label || metric.key;

      card.appendChild(valueEl);
      card.appendChild(labelEl);
      container.appendChild(card);
    });
  }

  function renderAnalyticsChart(container, series) {
    container.innerHTML = '';

    if (!Array.isArray(series) || series.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'ap-dashboard__analytics-empty';
      empty.textContent = i18n.chartEmpty;
      container.appendChild(empty);
      return;
    }

    const values = series.map((item) => {
      const raw = item ? item.views : 0;
      const numeric = typeof raw === 'number' ? raw : parseInt(raw, 10);
      return Number.isFinite(numeric) ? numeric : 0;
    });

    const width = 320;
    const height = 160;
    const padding = 20;
    const innerWidth = width - padding * 2;
    const innerHeight = height - padding * 2;
    const max = Math.max(...values);
    const safeMax = max > 0 ? max : 1;
    const step = series.length > 1 ? innerWidth / (series.length - 1) : 0;
    const svgNS = 'http://www.w3.org/2000/svg';

    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('class', 'ap-dashboard__analytics-svg');
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', i18n.analyticsPrimary);

    const axis = document.createElementNS(svgNS, 'line');
    axis.setAttribute('x1', String(padding));
    axis.setAttribute('y1', String(height - padding));
    axis.setAttribute('x2', String(width - padding));
    axis.setAttribute('y2', String(height - padding));
    axis.setAttribute('class', 'ap-dashboard__analytics-axis');
    svg.appendChild(axis);

    let pathData = '';
    const points = [];

    series.forEach((point, index) => {
      const x = padding + (series.length > 1 ? step * index : innerWidth / 2);
      const ratio = safeMax ? values[index] / safeMax : 0;
      const y = padding + (innerHeight - ratio * innerHeight);
      points.push({
        x,
        y,
        value: values[index],
        date: point?.date || '',
      });
      pathData += `${index === 0 ? 'M' : 'L'}${x.toFixed(2)} ${y.toFixed(2)} `;
    });

    const path = document.createElementNS(svgNS, 'path');
    path.setAttribute('d', pathData.trim());
    path.setAttribute('fill', 'none');
    path.setAttribute('class', 'ap-dashboard__analytics-path');
    svg.appendChild(path);

    points.forEach((point) => {
      const circle = document.createElementNS(svgNS, 'circle');
      circle.setAttribute('cx', point.x.toFixed(2));
      circle.setAttribute('cy', point.y.toFixed(2));
      circle.setAttribute('r', '3');
      circle.setAttribute('class', 'ap-dashboard__analytics-point');

      const title = document.createElementNS(svgNS, 'title');
      const label = formatAnalyticsDate(point.date);
      title.textContent = `${label}: ${point.value}`;
      circle.appendChild(title);

      svg.appendChild(circle);
    });

    container.appendChild(svg);

    const rangeSummary = document.createElement('div');
    rangeSummary.className = 'ap-dashboard__analytics-summary';
    const startLabel = formatAnalyticsDate(series[0]?.date);
    const endLabel = formatAnalyticsDate(series[series.length - 1]?.date);
    if (startLabel || endLabel) {
      if (startLabel && endLabel && startLabel !== endLabel) {
        rangeSummary.textContent = `${startLabel} – ${endLabel}`;
      } else {
        rangeSummary.textContent = startLabel || endLabel || '';
      }
      container.appendChild(rangeSummary);
    }
  }

  function fetchAnalyticsData(role, range) {
    const target = `${endpointRoot}analytics/${role}?range=${range}`;
    return fetchJSON(target);
  }

  function formatAnalyticsDate(value) {
    if (!value) {
      return '';
    }

    const direct = new Date(value);
    if (!Number.isNaN(direct.getTime())) {
      try {
        return direct.toLocaleDateString(undefined, {
          month: 'short',
          day: 'numeric',
        });
      } catch (e) {
        return direct.toISOString().slice(0, 10);
      }
    }

    const fallback = new Date(`${value}T00:00:00`);
    if (!Number.isNaN(fallback.getTime())) {
      try {
        return fallback.toLocaleDateString(undefined, {
          month: 'short',
          day: 'numeric',
        });
      } catch (e) {
        return fallback.toISOString().slice(0, 10);
      }
    }

    return String(value);
  }

  function formatRangeLabel(range) {
    switch (range) {
      case 7:
        return i18n.range7 || '7 days';
      case 30:
        return i18n.range30 || '30 days';
      case 90:
        return i18n.range90 || '90 days';
      default:
        return `${range} ${range === 1 ? 'day' : 'days'}`;
    }
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

  function renderRosterPreview(data) {
    const panel = document.getElementById('ap-dashboard-roster-preview');
    if (!panel) {
      return;
    }

    const container = panel.querySelector('.ap-dashboard__roster');
    if (!container) {
      return;
    }

    container.innerHTML = '';

    const items = data && Array.isArray(data.items) ? data.items : [];
    if (items.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'ap-dashboard__item ap-dashboard__item--empty';
      empty.textContent = i18n.noRoster;
      container.appendChild(empty);
      return;
    }

    const list = document.createElement('ul');
    list.className = 'ap-dashboard__item-list ap-dashboard__item-list--roster';

    items.forEach((member) => {
      const li = document.createElement('li');
      li.className = 'ap-dashboard__item ap-dashboard__item--roster';

      if (member?.avatar) {
        const avatar = document.createElement('img');
        avatar.className = 'ap-dashboard__item-avatar';
        avatar.src = member.avatar;
        avatar.alt = member?.name || '';
        avatar.width = 32;
        avatar.height = 32;
        li.appendChild(avatar);
      }

      const name = document.createElement('span');
      name.className = 'ap-dashboard__item-name';
      name.textContent = member?.name || i18n.untitled;
      li.appendChild(name);

      if (member?.role) {
        const role = document.createElement('span');
        role.className = 'ap-dashboard__item-meta';
        role.textContent = member.role;
        li.appendChild(role);
      }

      list.appendChild(li);
    });

    container.appendChild(list);
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
