(function (window, document) {
  const existingConfig = window.APSocial || {};
  const messages = existingConfig.messages || {};

  function bind(scope = document) {
    bindFavoriteButtons(scope);
    bindFollowButtons(scope);
  }

  function bindFavoriteButtons(scope) {
    scope.querySelectorAll('[data-ap-fav]').forEach((button) => {
      if (button.dataset.apFavBound === '1') {
        return;
      }

      const objectId = readNumeric(button.dataset.apObjectId);
      const objectType = button.dataset.apObjectType || button.getAttribute('data-object-type');

      if (!objectId || !objectType) {
        return;
      }

      button.dataset.apFavBound = '1';

      let isActive = button.dataset.apActive === '1' || button.getAttribute('aria-pressed') === 'true';
      updateFavoriteUI(button, isActive);

      button.addEventListener('click', (event) => {
        event.preventDefault();

        if (button.dataset.apBusy === '1') {
          return;
        }

        updateFavoriteUI(button, !isActive, true);
        setBusy(button, true);

        toggleFavorite(objectId, objectType, isActive)
          .then((response) => {
            if (!response || !response.status) {
              throw new Error('Invalid response');
            }

            isActive = response.status === 'favorited';
            updateFavoriteUI(button, isActive);
          })
          .catch((error) => {
            updateFavoriteUI(button, isActive);
            notifyError(button, error?.message || messages.favoriteError || 'Unable to update favorite.');
          })
          .finally(() => {
            setBusy(button, false);
          });
      });
    });
  }

  function bindFollowButtons(scope) {
    scope.querySelectorAll('[data-ap-follow]').forEach((button) => {
      if (button.dataset.apFollowBound === '1') {
        return;
      }

      const objectId = readNumeric(button.dataset.apObjectId);
      const objectType = button.dataset.apObjectType || button.getAttribute('data-object-type');

      if (!objectId || !objectType) {
        return;
      }

      button.dataset.apFollowBound = '1';

      let isActive = button.dataset.apActive === '1' || button.getAttribute('aria-pressed') === 'true';
      updateFollowUI(button, isActive);

      button.addEventListener('click', (event) => {
        event.preventDefault();

        if (button.dataset.apBusy === '1') {
          return;
        }

        updateFollowUI(button, !isActive, true);
        setBusy(button, true);

        toggleFollow(objectId, objectType, isActive)
          .then((response) => {
            if (!response || !response.status) {
              throw new Error('Invalid response');
            }

            isActive = response.status === 'following';
            updateFollowUI(button, isActive);
          })
          .catch((error) => {
            updateFollowUI(button, isActive);
            notifyError(button, error?.message || messages.followError || 'Unable to update follow.');
          })
          .finally(() => {
            setBusy(button, false);
          });
      });
    });
  }

  function toggleFavorite(objectId, objectType, isCurrentlyActive) {
    const path = isCurrentlyActive ? 'favorites/remove' : 'favorites/add';

    return request(path, {
      method: 'POST',
      body: {
        object_id: objectId,
        object_type: objectType,
      },
    });
  }

  function toggleFollow(objectId, objectType, isCurrentlyActive) {
    const method = isCurrentlyActive ? 'DELETE' : 'POST';

    return request('follows', {
      method,
      body: {
        post_id: objectId,
        post_type: objectType,
      },
    });
  }

  function request(path, options = {}) {
    const root = apiRoot();
    const nonce = apiNonce();

    const url = joinPath(root, path.startsWith('artpulse/v1/') ? path : `artpulse/v1/${path}`);
    const requestOptions = {
      method: options.method || 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      credentials: 'same-origin',
    };

    if (nonce) {
      requestOptions.headers['X-WP-Nonce'] = nonce;
    }

    if (options.body) {
      requestOptions.body = JSON.stringify(options.body);
    }

    return fetch(url, requestOptions).then(async (response) => {
      const data = await safeJson(response);
      if (!response.ok) {
        const error = new Error((data && data.message) || 'Request failed');
        error.status = response.status;
        error.data = data;
        throw error;
      }
      return data;
    });
  }

  function updateFavoriteUI(button, isActive, optimistic = false) {
    setState(button, isActive, 'fav');
    button.classList.toggle('is-active', isActive);
    button.classList.toggle('active', isActive);
    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');

    const label = isActive ? button.dataset.labelOn : button.dataset.labelOff;
    if (label) {
      button.textContent = label;
    } else if (optimistic && !button.textContent.trim()) {
      button.textContent = isActive ? 'Unfavorite' : 'Favorite';
    }
  }

  function updateFollowUI(button, isActive, optimistic = false) {
    setState(button, isActive, 'follow');
    button.classList.toggle('is-following', isActive);
    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');

    const label = isActive ? button.dataset.labelOn : button.dataset.labelOff;
    if (label) {
      button.textContent = label;
    } else if (optimistic && !button.textContent.trim()) {
      button.textContent = isActive ? 'Unfollow' : 'Follow';
    }
  }

  function setState(button, isActive, namespace) {
    if (namespace === 'fav') {
      button.dataset.apActive = isActive ? '1' : '0';
    } else if (namespace === 'follow') {
      button.dataset.apActive = isActive ? '1' : '0';
    }
  }

  function setBusy(button, isBusy) {
    button.dataset.apBusy = isBusy ? '1' : '0';
    button.toggleAttribute('disabled', isBusy);
    button.setAttribute('aria-busy', isBusy ? 'true' : 'false');
  }

  function notifyError(element, message) {
    const event = new CustomEvent('ap:social:error', {
      bubbles: true,
      cancelable: true,
      detail: {
        element,
        message,
      },
    });

    const prevented = !element.dispatchEvent(event);
    if (!prevented) {
      // eslint-disable-next-line no-alert
      window.alert(message);
    }
  }

  function readNumeric(value) {
    if (typeof value === 'undefined') {
      return null;
    }
    const number = parseInt(value, 10);
    return Number.isNaN(number) ? null : number;
  }

  function safeJson(response) {
    return response
      .clone()
      .json()
      .catch(() => null);
  }

  function apiRoot() {
    return (
      existingConfig.root ||
      (window.ArtPulseApi && window.ArtPulseApi.root) ||
      (window.wpApiSettings && window.wpApiSettings.root) ||
      ''
    );
  }

  function apiNonce() {
    return (
      existingConfig.nonce ||
      (window.ArtPulseApi && window.ArtPulseApi.nonce) ||
      (window.wpApiSettings && window.wpApiSettings.nonce) ||
      ''
    );
  }

  function joinPath(root, path) {
    if (!root) {
      return path;
    }
    const normalizedRoot = root.replace(/\/$/, '');
    const normalizedPath = path.replace(/^\//, '');
    return `${normalizedRoot}/${normalizedPath}`;
  }

  const api = {
    root: apiRoot(),
    nonce: apiNonce(),
    bind,
    toggleFavorite,
    toggleFollow,
  };

  window.APSocial = Object.assign({}, existingConfig, api);

  if (!window.ArtPulseApi) {
    window.ArtPulseApi = { root: api.root, nonce: api.nonce };
  } else {
    window.ArtPulseApi.root = window.ArtPulseApi.root || api.root;
    window.ArtPulseApi.nonce = window.ArtPulseApi.nonce || api.nonce;
  }

  document.addEventListener('DOMContentLoaded', () => {
    bind(document);
  });

  document.addEventListener('ap:social:bind', (event) => {
    const scope = (event.detail && event.detail.scope) || document;
    bind(scope);
  });
})(window, document);
