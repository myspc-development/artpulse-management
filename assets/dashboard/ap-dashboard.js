(function () {
  const boot = window.AP_BOOT || {};
  const root = document.getElementById('ap-dashboard');
  if (!root) {
    return;
  }

  const i18n = Object.assign(
    {
      loading: 'Loading…',
      loadingAnalytics: 'Loading analytics…',
      retry: 'Retry',
      dashboardError: 'We could not load your dashboard. Please try again.',
      analyticsError: 'We could not load analytics. Please try again.',
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
      analyticsPrimary: 'Views over time',
      views: 'Views',
      rsvps: 'RSVPs',
      range7: '7 days',
      range30: '30 days',
      range90: '90 days',
      chartEmpty: 'No analytics available yet.',
      memberFavoritesEmptyTitle: 'No favorites yet',
      memberFavoritesEmptyCta: 'Browse the directory',
      artistArtworksEmptyTitle: 'No artworks yet',
      artistArtworksEmptyCta: 'Create your first artwork',
      orgEventsEmptyTitle: 'No events scheduled',
      orgEventsEmptyCta: 'Add an event',
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
  const CACHE_TTL = 30000;
  const analyticsCache = new Map();

  root.setAttribute('role', 'region');
  root.setAttribute('aria-live', 'polite');
  root.dataset.loadingType = 'dashboard';
  showLoading(root);

  const endpointRoot = boot?.endpoints?.root || '';
  if (!endpointRoot) {
    const retryButton = showError(root, i18n.dashboardError);
    if (retryButton) {
      retryButton.disabled = true;
    }
    return;
  }

  const role = boot.role;
  if (role === 'org') {
    loadOrgOverview({ skipLoading: true });
    return;
  }
  if (role === 'artist') {
    loadArtistOverview({ skipLoading: true });
    return;
  }
  if (role === 'member') {
    loadMemberOverview({ skipLoading: true });
    return;
  }

  root.innerHTML = '';
  root.removeAttribute('aria-busy');

  function loadMemberOverview(options = {}) {
    const skipLoading = options.skipLoading === true;
    if (!skipLoading) {
      root.dataset.loadingType = 'dashboard';
      showLoading(root);
    }

    fetchJSON(endpointRoot + 'me/overview')
      .then((data) => {
        renderMemberOverview(data);
      })
      .catch(() => {
        renderOverviewError(loadMemberOverview);
      });
  }

  function loadArtistOverview(options = {}) {
    const skipLoading = options.skipLoading === true;
    if (!skipLoading) {
      root.dataset.loadingType = 'dashboard';
      showLoading(root);
    }

    fetchJSON(endpointRoot + 'artist/overview')
      .then((data) => {
        renderArtistOverview(data);
      })
      .catch(() => {
        renderOverviewError(loadArtistOverview);
      });
  }

  function loadOrgOverview(options = {}) {
    const skipLoading = options.skipLoading === true;
    if (!skipLoading) {
      root.dataset.loadingType = 'dashboard';
      showLoading(root);
    }

    fetchJSON(endpointRoot + 'org/overview')
      .then((data) => {
        renderOrgOverview(data);
        loadRosterPreview({ skipLoading: true });
      })
      .catch(() => {
        renderOverviewError(loadOrgOverview);
      });
  }

  function renderOverviewError(retryFn) {
    const retryButton = showError(root, i18n.dashboardError);
    if (retryButton) {
      retryButton.addEventListener('click', () => retryFn({ skipLoading: false }));
      retryButton.addEventListener('keydown', handleButtonKeydown);
    }
  }

  function renderMemberOverview(data) {
    const counts = data && typeof data === 'object' ? data.counts || {} : {};
    const events = Array.isArray(data?.nextEvents) ? data.nextEvents : [];

    const statsMarkup = buildStatsMarkup(
      [
        { key: 'favorites', label: i18n.favorites },
        { key: 'follows', label: i18n.follows },
        { key: 'upcoming', label: i18n.upcoming },
        { key: 'notifications', label: i18n.notifications },
      ],
      counts
    );

    const analyticsMarkup = buildAnalyticsMarkup('member');
    const upcomingMarkup = buildUpcomingMarkup(events);
    const emptyCallout = shouldShowFavoritesCallout(counts)
      ? buildEmptyPanel(
          i18n.memberFavoritesEmptyTitle,
          i18n.memberFavoritesEmptyCta,
          resolveLink('directory')
        )
      : '';

    const html = [statsMarkup, emptyCallout, analyticsMarkup?.html || '', upcomingMarkup]
      .filter(Boolean)
      .join('');

    root.innerHTML = html;
    root.removeAttribute('aria-busy');
    delete root.dataset.loadingType;

    if (analyticsMarkup?.uid) {
      const panel = root.querySelector(`[data-analytics-uid="${analyticsMarkup.uid}"]`);
      if (panel) {
        initAnalyticsPanel(panel, 'member');
      }
    }
  }

  function renderArtistOverview(data) {
    const counts = data && typeof data === 'object' ? data.counts || {} : {};
    const recent = data && typeof data === 'object' ? data.recent || {} : {};

    const statsMarkup = buildStatsMarkup(
      [
        { key: 'artworks', label: i18n.artworks },
        { key: 'events', label: i18n.events },
        { key: 'favorites', label: i18n.favorites },
        { key: 'follows', label: i18n.follows },
      ],
      counts
    );

    const analyticsMarkup = buildAnalyticsMarkup('artist');

    const artworks = Array.isArray(recent?.artworks) ? recent.artworks : [];
    const events = Array.isArray(recent?.events) ? recent.events : [];

    const listsMarkup = [
      buildRecentList(i18n.recentArtworks, artworks, {
        emptyTitle: i18n.artistArtworksEmptyTitle,
        emptyCta: i18n.artistArtworksEmptyCta,
        emptyHref: resolveLink('artworkCreate'),
      }),
      buildRecentList(i18n.recentEvents, events, {
        emptyTitle: i18n.noEvents,
      }),
    ].join('');

    const html = [statsMarkup, analyticsMarkup?.html || '', listsMarkup].join('');

    root.innerHTML = html;
    root.removeAttribute('aria-busy');
    delete root.dataset.loadingType;

    if (analyticsMarkup?.uid) {
      const panel = root.querySelector(`[data-analytics-uid="${analyticsMarkup.uid}"]`);
      if (panel) {
        initAnalyticsPanel(panel, 'artist');
      }
    }
  }

  function renderOrgOverview(data) {
    const counts = data && typeof data === 'object' ? data.counts || {} : {};
    const recent = data && typeof data === 'object' ? data.recent || {} : {};

    const statsMarkup = buildStatsMarkup(
      [
        { key: 'events', label: i18n.events },
        { key: 'artists', label: i18n.artists },
        { key: 'submissions', label: i18n.submissions },
      ],
      counts
    );

    const analyticsMarkup = buildAnalyticsMarkup('org');

    const recentEvents = Array.isArray(recent?.events) ? recent.events : [];
    const recentSubmissions = Array.isArray(recent?.submissions) ? recent.submissions : [];

    const html = [
      statsMarkup,
      analyticsMarkup?.html || '',
      [
        buildRecentList(i18n.recentEvents, recentEvents, {
          emptyTitle: i18n.orgEventsEmptyTitle,
          emptyCta: i18n.orgEventsEmptyCta,
          emptyHref: resolveLink('eventCreate'),
        }),
        buildRecentList(i18n.recentSubmissions, recentSubmissions, {
          emptyTitle: i18n.noSubmissions,
        }),
      ].join(''),
      buildRosterPanel(),
    ].join('');

    root.innerHTML = html;
    root.removeAttribute('aria-busy');
    delete root.dataset.loadingType;

    if (analyticsMarkup?.uid) {
      const panel = root.querySelector(`[data-analytics-uid="${analyticsMarkup.uid}"]`);
      if (panel) {
        initAnalyticsPanel(panel, 'org');
      }
    }

    const rosterContainer = root.querySelector(
      '#ap-dashboard-roster-preview .ap-dashboard__roster'
    );
    if (rosterContainer) {
      rosterContainer.dataset.loadingType = 'list';
      showLoading(rosterContainer, i18n.loading);
    }
  }

  function loadRosterPreview(options = {}) {
    const container = root.querySelector(
      '#ap-dashboard-roster-preview .ap-dashboard__roster'
    );
    if (!container) {
      return;
    }

    if (options.skipLoading !== true) {
      container.dataset.loadingType = 'list';
      showLoading(container, i18n.loading);
    }

    fetchJSON(endpointRoot + 'org/roster?per_page=5')
      .then((data) => {
        renderRosterPreview(data);
      })
      .catch(() => {
        const retryButton = showError(container, i18n.dashboardError);
        if (retryButton) {
          retryButton.addEventListener('click', () => loadRosterPreview({ skipLoading: false }));
          retryButton.addEventListener('keydown', handleButtonKeydown);
        }
      });
  }

  function renderRosterPreview(data) {
    const container = root.querySelector(
      '#ap-dashboard-roster-preview .ap-dashboard__roster'
    );
    if (!container) {
      return;
    }

    const items = data && Array.isArray(data.items) ? data.items : [];
    if (items.length === 0) {
      container.innerHTML = buildEmptyInline(i18n.noRoster);
      container.removeAttribute('aria-busy');
      return;
    }

    const listItems = items
      .map((member) => {
        const avatar = member?.avatar
          ? `<img class="ap-dashboard__item-avatar" src="${escapeAttribute(
              safeUrl(member.avatar)
            )}" alt="${escapeAttribute(member?.name || '')}" width="32" height="32" />`
          : '';
        const name = member?.name ? escapeHTML(member.name) : escapeHTML(i18n.untitled);
        const roleLabel = member?.role
          ? `<span class="ap-dashboard__item-meta">${escapeHTML(member.role)}</span>`
          : '';
        return `<li class="ap-dashboard__item ap-dashboard__item--roster">${avatar}<span class="ap-dashboard__item-name">${name}</span>${roleLabel}</li>`;
      })
      .join('');

    container.innerHTML = `<ul class="ap-dashboard__item-list ap-dashboard__item-list--roster">${listItems}</ul>`;
    container.removeAttribute('aria-busy');
  }

  function buildStatsMarkup(statMap, counts) {
    const cards = statMap
      .map((item) => {
        const rawValue = counts ? counts[item.key] : undefined;
        const numeric =
          typeof rawValue === 'number' ? rawValue : parseInt(rawValue, 10);
        const value = Number.isFinite(numeric) ? numeric : 0;
        const label = item.label || item.key;
        return `<div class="ap-dashboard__stat" role="group" aria-label="${escapeHTML(
          label
        )}"><span class="ap-dashboard__stat-value">${escapeHTML(
          formatNumber(value)
        )}</span><span class="ap-dashboard__stat-label">${escapeHTML(label)}</span></div>`;
      })
      .join('');

    return `<div class="ap-dashboard__stats" data-loading-type="stats" aria-live="polite">${cards}</div>`;
  }

  function buildUpcomingMarkup(events) {
    const hasEvents = Array.isArray(events) && events.length > 0;
    const items = hasEvents
      ? events
          .map((event) => {
            const title = event?.title ? escapeHTML(event.title) : escapeHTML(i18n.untitled);
            const href = safeUrl(event?.permalink || '');
            const dateText = formatDate(event?.date);
            const dateMarkup = dateText
              ? `<span class="ap-dashboard__event-date">${escapeHTML(dateText)}</span>`
              : '';
            return `<li class="ap-dashboard__event"><a class="ap-dashboard__event-link" href="${escapeAttribute(
              href
            )}">${title}</a>${dateMarkup}</li>`;
          })
          .join('')
      : `<li class="ap-dashboard__event ap-dashboard__event--empty">${buildEmptyInline(
          i18n.noUpcoming
        )}</li>`;

    return `
      <div class="ap-dashboard__panel ap-dashboard__upcoming" aria-live="polite">
        <h2 class="ap-dashboard__section-title">${escapeHTML(i18n.upcomingEvents)}</h2>
        <ul class="ap-dashboard__event-list" data-loading-type="list">${items}</ul>
      </div>
    `;
  }

  function buildRecentList(title, items, options = {}) {
    const listItems = Array.isArray(items) && items.length
      ? items
          .map((item) => {
            const primaryTitle = item?.title
              ? escapeHTML(item.title)
              : escapeHTML(i18n.untitled);
            const editHref = safeUrl(item?.edit_link || '');
            const viewHref = safeUrl(item?.permalink || '');
            const primaryHref = viewHref !== '#' ? viewHref : editHref;
            const linkHref = primaryHref !== '#' ? primaryHref : '#';

            const actions = [];
            if (editHref !== '#') {
              actions.push(
                `<a class="ap-dashboard__item-action" href="${escapeAttribute(
                  editHref
                )}">${escapeHTML(i18n.edit)}</a>`
              );
            }
            if (viewHref !== '#') {
              actions.push(
                `<a class="ap-dashboard__item-action" href="${escapeAttribute(
                  viewHref
                )}" target="_blank" rel="noopener noreferrer">${escapeHTML(i18n.view)}</a>`
              );
            }
            const actionsMarkup = actions.length
              ? `<div class="ap-dashboard__item-actions">${actions.join('')}</div>`
              : '';

            return `<li class="ap-dashboard__item"><a class="ap-dashboard__item-link" href="${escapeAttribute(
              linkHref
            )}">${primaryTitle}</a>${actionsMarkup}</li>`;
          })
          .join('')
      : `<li class="ap-dashboard__item ap-dashboard__item--empty">${buildEmptyInline(
          options.emptyTitle || '',
          options.emptyCta,
          options.emptyHref
        )}</li>`;

    return `
      <div class="ap-dashboard__panel" aria-live="polite">
        <h2 class="ap-dashboard__section-title">${escapeHTML(title)}</h2>
        <ul class="ap-dashboard__item-list" data-loading-type="list">${listItems}</ul>
      </div>
    `;
  }

  function buildRosterPanel() {
    return `
      <div class="ap-dashboard__panel" id="ap-dashboard-roster-preview" aria-live="polite">
        <h2 class="ap-dashboard__section-title">${escapeHTML(i18n.rosterPreview)}</h2>
        <div class="ap-dashboard__roster" data-loading-type="list"></div>
      </div>
    `;
  }

  function buildAnalyticsMarkup(role) {
    if (!endpointRoot) {
      return null;
    }

    const defaultRange = ANALYTICS_RANGES.includes(30) ? 30 : ANALYTICS_RANGES[0];
    const uid = `${role}-${Math.random().toString(36).slice(2, 10)}`;

    const controls = ANALYTICS_RANGES.map((range) => {
      const isActive = range === defaultRange;
      return `<button type="button" class="ap-dashboard__analytics-range${
        isActive ? ' is-active' : ''
      }" data-range="${range}" aria-pressed="${isActive ? 'true' : 'false'}">${escapeHTML(
        formatRangeLabel(range)
      )}</button>`;
    }).join('');

    const html = `
      <div class="ap-dashboard__panel ap-dashboard__analytics" data-analytics-role="${escapeAttribute(
        role
      )}" data-analytics-default="${defaultRange}" data-analytics-uid="${uid}" aria-live="polite" aria-busy="true">
        <div class="ap-dashboard__analytics-header">
          <h2 class="ap-dashboard__section-title">${escapeHTML(i18n.analytics)}</h2>
          <div class="ap-dashboard__analytics-controls" role="group" aria-label="${escapeHTML(
            i18n.analytics
          )}">
            ${controls}
          </div>
        </div>
        <div class="ap-dashboard__analytics-status" aria-live="polite"></div>
        <div class="ap-dashboard__stats ap-dashboard__stats--analytics" data-loading-type="stats" aria-live="polite"></div>
        <div class="ap-dashboard__analytics-chart" data-loading-type="chart" aria-live="polite"></div>
      </div>
    `;
    return { html, uid };
  }

  function buildEmptyPanel(title, cta, href) {
    return `
      <div class="ap-dashboard__panel ap-dashboard__panel--empty" aria-live="polite">
        ${buildEmptyInline(title, cta, href)}
      </div>
    `;
  }

  function buildEmptyInline(message, cta, href) {
    const parts = [];
    if (message) {
      parts.push(`<span class="ap-dashboard__empty-text">${escapeHTML(message)}</span>`);
    }
    if (cta) {
      const safeHref = safeUrl(href);
      if (safeHref !== '#') {
        parts.push(
          `<a class="ap-dashboard__empty-cta" href="${escapeAttribute(
            safeHref
          )}" role="button" tabindex="0">${escapeHTML(cta)}</a>`
        );
      } else {
        parts.push(
          `<span class="ap-dashboard__empty-cta ap-dashboard__empty-cta--disabled" aria-disabled="true">${escapeHTML(
            cta
          )}</span>`
        );
      }
    }
    if (!parts.length) {
      return '';
    }
    return `<div class="ap-dashboard__empty" role="note">${parts.join(' ')}</div>`;
  }

  function shouldShowFavoritesCallout(counts) {
    if (!counts) {
      return true;
    }
    const raw = counts.favorites;
    const numeric = typeof raw === 'number' ? raw : parseInt(raw, 10);
    return !Number.isFinite(numeric) || numeric <= 0;
  }

  function initAnalyticsPanel(panel, role) {
    const status = panel.querySelector('.ap-dashboard__analytics-status');
    const totals = panel.querySelector('.ap-dashboard__stats');
    const chart = panel.querySelector('.ap-dashboard__analytics-chart');
    const controls = Array.from(panel.querySelectorAll('.ap-dashboard__analytics-range'));

    if (!status || !totals || !chart || !controls.length) {
      return;
    }

    const defaultRange =
      Number(panel.dataset.analyticsDefault) || ANALYTICS_RANGES[0];
    const uid = panel.dataset.analyticsUid || `${role}-${Date.now()}`;
    panel.dataset.analyticsUid = uid;

    totals.id = `ap-dashboard-analytics-totals-${uid}`;
    chart.dataset.chartUid = uid;

    let currentRange = defaultRange;
    let requestId = 0;

    const debouncedLoad = debounce((range) => {
      fetchAndRender(range);
    }, 200);

    function fetchAndRender(range) {
      panel.setAttribute('aria-busy', 'true');
      status.textContent = i18n.loadingAnalytics || i18n.loading;
      status.classList.remove('is-error');
      totals.dataset.loadingType = 'stats';
      chart.dataset.loadingType = 'chart';
      showLoading(totals, i18n.loadingAnalytics || i18n.loading);
      showLoading(chart, i18n.loadingAnalytics || i18n.loading);

      const token = ++requestId;
      fetchAnalyticsData(role, range)
        .then((response) => {
          if (token !== requestId) {
            return;
          }
          panel.setAttribute('aria-busy', 'false');
          status.textContent = '';
          renderAnalyticsTotals(totals, response?.totals);
          renderAnalyticsChart(chart, response?.series, totals.id, uid);
        })
        .catch(() => {
          if (token !== requestId) {
            return;
          }
          panel.setAttribute('aria-busy', 'false');
          status.textContent = '';
          totals.innerHTML = '';
          const retryButton = showError(chart, i18n.analyticsError || i18n.dashboardError);
          if (retryButton) {
            retryButton.addEventListener('click', () => {
              currentRange = range;
              setActiveRange(currentRange);
              debouncedLoad.cancel?.();
              fetchAndRender(currentRange);
            });
            retryButton.addEventListener('keydown', handleButtonKeydown);
          }
        });
    }

    function setActiveRange(range) {
      controls.forEach((button) => {
        const isActive = Number(button.dataset.range) === range;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
    }

    controls.forEach((button) => {
      const range = Number(button.dataset.range);
      button.addEventListener('click', () => {
        if (range === currentRange) {
          return;
        }
        currentRange = range;
        setActiveRange(range);
        debouncedLoad(range);
      });
      button.addEventListener('keydown', handleButtonKeydown);
    });

    setActiveRange(currentRange);
    fetchAndRender(currentRange);
  }

  function fetchAnalyticsData(role, range) {
    const cacheKey = `${role}:${range}`;
    const cached = analyticsCache.get(cacheKey);
    const now = Date.now();
    if (cached && now - cached.time < CACHE_TTL) {
      return Promise.resolve(cached.data);
    }

    const target = `${endpointRoot}analytics/${role}?range=${range}`;
    return fetchJSON(target).then((response) => {
      analyticsCache.set(cacheKey, { time: Date.now(), data: response });
      return response;
    });
  }

  function renderAnalyticsTotals(container, totals) {
    if (!container) {
      return;
    }

    const data = totals && typeof totals === 'object' ? totals : {};
    const cards = ANALYTICS_METRICS.map((metric) => {
      const rawValue = data[metric.key];
      const numeric =
        typeof rawValue === 'number' ? rawValue : parseInt(rawValue, 10);
      const value = Number.isFinite(numeric) ? numeric : 0;
      const label = metric.label || metric.key;
      return `<div class="ap-dashboard__stat" role="group" aria-label="${escapeHTML(
        label
      )}"><span class="ap-dashboard__stat-value">${escapeHTML(
        formatNumber(value)
      )}</span><span class="ap-dashboard__stat-label">${escapeHTML(label)}</span></div>`;
    }).join('');

    container.innerHTML = cards;
    container.removeAttribute('aria-busy');
  }

  function renderAnalyticsChart(container, series, totalsId, uid) {
    if (!container) {
      return;
    }

    container.innerHTML = '';

    const dataSeries = Array.isArray(series) ? series : [];
    if (dataSeries.length === 0) {
      container.innerHTML = `<p class="ap-dashboard__analytics-empty">${escapeHTML(
        i18n.chartEmpty
      )}</p>`;
      container.removeAttribute('aria-busy');
      return;
    }

    const values = dataSeries.map((item) => {
      const raw = item ? item.views : 0;
      const numeric = typeof raw === 'number' ? raw : parseInt(raw, 10);
      return Number.isFinite(numeric) ? numeric : 0;
    });

    const width = 320;
    const height = 160;
    const padding = 20;
    const innerWidth = width - padding * 2;
    const innerHeight = height - padding * 2;
    const max = Math.max(...values, 0);
    const safeMax = max > 0 ? max : 1;
    const step = dataSeries.length > 1 ? innerWidth / (dataSeries.length - 1) : 0;
    const svgNS = 'http://www.w3.org/2000/svg';

    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('class', 'ap-dashboard__analytics-svg');
    svg.setAttribute('role', 'img');

    const titleId = `ap-dashboard-analytics-title-${uid}`;
    const descId = `ap-dashboard-analytics-desc-${uid}`;

    const titleEl = document.createElementNS(svgNS, 'title');
    titleEl.setAttribute('id', titleId);
    titleEl.textContent = i18n.analyticsPrimary || i18n.analytics;
    svg.appendChild(titleEl);

    const descEl = document.createElementNS(svgNS, 'desc');
    descEl.setAttribute('id', descId);
    svg.appendChild(descEl);

    svg.setAttribute('aria-labelledby', titleId);
    if (totalsId) {
      svg.setAttribute('aria-describedby', `${descId} ${totalsId}`);
    } else {
      svg.setAttribute('aria-describedby', descId);
    }

    const axis = document.createElementNS(svgNS, 'line');
    axis.setAttribute('x1', String(padding));
    axis.setAttribute('y1', String(height - padding));
    axis.setAttribute('x2', String(width - padding));
    axis.setAttribute('y2', String(height - padding));
    axis.setAttribute('class', 'ap-dashboard__analytics-axis');
    svg.appendChild(axis);

    let pathData = '';
    const points = [];

    dataSeries.forEach((point, index) => {
      const x = padding + (dataSeries.length > 1 ? step * index : innerWidth / 2);
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
      const valueText = formatNumber(point.value);
      title.textContent = label ? `${label}: ${valueText}` : valueText;
      circle.appendChild(title);

      svg.appendChild(circle);
    });

    container.appendChild(svg);

    const startLabel = formatAnalyticsDate(dataSeries[0]?.date);
    const endLabel = formatAnalyticsDate(dataSeries[dataSeries.length - 1]?.date);
    let summaryText = '';
    if (startLabel && endLabel) {
      summaryText = startLabel === endLabel ? startLabel : `${startLabel} – ${endLabel}`;
    } else {
      summaryText = startLabel || endLabel || '';
    }
    descEl.textContent = summaryText || (i18n.analyticsPrimary || '');

    if (summaryText) {
      const rangeSummary = document.createElement('div');
      rangeSummary.className = 'ap-dashboard__analytics-summary';
      rangeSummary.textContent = summaryText;
      container.appendChild(rangeSummary);
    }

    container.removeAttribute('aria-busy');
  }

  function showLoading(root, text) {
    if (!root) {
      return;
    }

    const message = typeof text === 'string' && text ? text : i18n.loading;
    const type = root.dataset.loadingType || '';
    let skeleton = '';

    const statsSkeleton = () =>
      `<div class="ap-dashboard__skeleton ap-dashboard__skeleton--stats" aria-hidden="true">${Array(4)
        .fill('')
        .map(
          () =>
            '<div class="ap-dashboard__skeleton-card"><span class="ap-skeleton ap-skeleton--value"></span><span class="ap-skeleton ap-skeleton--label"></span></div>'
        )
        .join('')}</div>`;

    const listSkeleton = () =>
      `<ul class="ap-dashboard__skeleton ap-dashboard__skeleton--list" aria-hidden="true">${Array(3)
        .fill('')
        .map(() => '<li class="ap-skeleton ap-skeleton--line"></li>')
        .join('')}</ul>`;

    const chartSkeleton = () =>
      '<div class="ap-dashboard__skeleton ap-dashboard__skeleton--chart" aria-hidden="true"><span class="ap-skeleton ap-skeleton--chart"></span></div>';

    switch (type) {
      case 'stats':
        skeleton = statsSkeleton();
        break;
      case 'list':
        skeleton = listSkeleton();
        break;
      case 'chart':
        skeleton = chartSkeleton();
        break;
      default:
        skeleton = `<div class="ap-dashboard__skeleton-group">${statsSkeleton()}${listSkeleton()}${chartSkeleton()}</div>`;
        break;
    }

    root.innerHTML = `
      <div class="ap-dashboard__loading" role="status" aria-live="polite">
        <span class="ap-dashboard__loading-text">${escapeHTML(message)}</span>
        ${skeleton}
      </div>
    `;
    root.setAttribute('aria-busy', 'true');
  }

  function showError(root, message) {
    if (!root) {
      return null;
    }

    const safeMessage =
      typeof message === 'string' && message ? message : i18n.dashboardError;
    root.innerHTML = `
      <div class="ap-dashboard__error" role="alert">
        <span class="ap-dashboard__error-icon" aria-hidden="true">!</span>
        <p class="ap-dashboard__error-message">${escapeHTML(safeMessage)}</p>
        <button type="button" class="ap-dashboard__error-button" role="button" tabindex="0">${escapeHTML(
          i18n.retry || 'Retry'
        )}</button>
      </div>
    `;
    root.setAttribute('aria-busy', 'false');

    return root.querySelector('.ap-dashboard__error-button');
  }

  function handleButtonKeydown(event) {
    if (!event) {
      return;
    }
    const key = event.key;
    if (key === ' ' || key === 'Spacebar') {
      event.preventDefault();
      if (event.currentTarget && typeof event.currentTarget.click === 'function') {
        event.currentTarget.click();
      }
    } else if (key === 'Enter') {
      event.preventDefault();
      if (event.currentTarget && typeof event.currentTarget.click === 'function') {
        event.currentTarget.click();
      }
    }
  }

  function debounce(fn, delay) {
    let timeoutId;
    function debounced(...args) {
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
      timeoutId = setTimeout(() => {
        timeoutId = null;
        fn.apply(this, args);
      }, delay);
    }
    debounced.cancel = function () {
      if (timeoutId) {
        clearTimeout(timeoutId);
        timeoutId = null;
      }
    };
    return debounced;
  }

  function formatNumber(value) {
    if (!Number.isFinite(value)) {
      return '0';
    }
    try {
      return value.toLocaleString();
    } catch (error) {
      return String(value);
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
      } catch (error) {
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
      } catch (error) {
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
    } catch (error) {
      return parsed.toISOString();
    }
  }

  function resolveLink(type) {
    const links = (boot && boot.links) || {};
    const candidates = {
      directory: [links.directory, links.explore, links.browse],
      artworkCreate: [links.artworkCreate, links.createArtwork, links.builder, links.newArtwork],
      eventCreate: [links.eventCreate, links.createEvent, links.eventsCreate, links.newEvent],
    }[type] || [];

    for (let index = 0; index < candidates.length; index += 1) {
      const candidate = safeUrl(candidates[index]);
      if (candidate !== '#') {
        return candidate;
      }
    }
    return '#';
  }

  function safeUrl(url) {
    if (typeof url !== 'string') {
      return '#';
    }
    const trimmed = url.trim();
    if (!trimmed || /^javascript:/i.test(trimmed)) {
      return '#';
    }
    return trimmed;
  }

  function escapeHTML(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeAttribute(value) {
    return escapeHTML(value).replace(/\n/g, '&#10;');
  }
})();
