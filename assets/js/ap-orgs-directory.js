(function () {
  const debounce = (fn, wait) => {
    let timeout;
    return (...args) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => fn.apply(null, args), wait);
    };
  };

  const updateUrl = (letter, search) => {
    const url = new URL(window.location.href);
    if (letter && letter !== 'All') {
      url.searchParams.set('letter', letter);
    } else {
      url.searchParams.delete('letter');
    }

    if (search) {
      url.searchParams.set('search', search);
    } else {
      url.searchParams.delete('search');
    }

    url.searchParams.delete('page');
    window.history.replaceState({}, '', url.toString());
  };

  const render = (wrapper, data, letter, search) => {
    const grid = wrapper.querySelector('.ap-grid');
    const emptyState = wrapper.querySelector('.ap-orgs-dir__empty');
    const summary = wrapper.querySelector('.ap-orgs-dir__summary');

    if (!grid) {
      return;
    }

    const activeLetter = letter || 'All';
    const searchValue = search ? search.toLowerCase() : '';

    const matches = data.items.filter((item) => {
      const matchesLetter = activeLetter === 'All' || item.letter === activeLetter;
      const matchesSearch = !searchValue || item.search_index.indexOf(searchValue) !== -1;
      return matchesLetter && matchesSearch;
    });

    grid.setAttribute('aria-busy', 'true');
    grid.innerHTML = matches.map((item) => item.html).join('');
    grid.setAttribute('aria-busy', 'false');

    if (summary) {
      const count = matches.length;
      const text = count === 1 ? window.apOrgsDirectoryL10n.one.replace('%s', count) : window.apOrgsDirectoryL10n.many.replace('%s', count.toLocaleString());
      summary.textContent = text;
    }

    if (emptyState) {
      if (matches.length === 0) {
        emptyState.hidden = false;
      } else {
        emptyState.hidden = true;
      }
    }

    const pagination = wrapper.querySelector('.ap-orgs-dir__pagination');
    if (pagination) {
      pagination.remove();
    }
  };

  const enhanceDirectory = (wrapper) => {
    const dataNode = wrapper.querySelector('.ap-orgs-dir__data');
    if (!dataNode) {
      return;
    }

    let data;
    try {
      data = JSON.parse(dataNode.textContent || '{}');
    } catch (e) {
      return;
    }

    if (!data || !Array.isArray(data.items)) {
      return;
    }

    const letters = wrapper.querySelectorAll('.ap-az__link');
    const searchInput = wrapper.querySelector('#ap-orgs-dir-search');

    const l10n = {
      one: '%s organization found',
      many: '%s organizations found',
      ...(window.apOrgsDirectoryL10n || {}),
    };

    window.apOrgsDirectoryL10n = l10n;

    const apply = (letter, search) => {
      render(wrapper, data, letter, search);
      updateUrl(letter, search);
    };

    let currentLetter = data.activeLetter || 'All';
    let currentSearch = data.searchTerm || '';

    const setActiveLetter = (letter) => {
      currentLetter = letter;
      letters.forEach((link) => {
        const linkLetter = link.getAttribute('data-letter');
        if (linkLetter === letter) {
          link.classList.add('is-active');
          link.setAttribute('aria-current', 'true');
        } else {
          link.classList.remove('is-active');
          link.removeAttribute('aria-current');
        }
      });
    };

    setActiveLetter(currentLetter);
    apply(currentLetter, currentSearch);

    letters.forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const letter = link.getAttribute('data-letter') || 'All';
        setActiveLetter(letter);
        apply(letter, currentSearch);
      });
    });

    if (searchInput) {
      searchInput.value = currentSearch;
      const handleSearch = debounce(() => {
        currentSearch = searchInput.value.trim();
        apply(currentLetter, currentSearch);
      }, 250);
      searchInput.addEventListener('input', handleSearch);
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ap-orgs-dir').forEach((wrapper) => enhanceDirectory(wrapper));
  });
})();
