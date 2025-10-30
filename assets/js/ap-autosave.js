(function (window, document, undefined) {
  'use strict';

  if (!window || !document) {
    return;
  }

  var settings = window.APAutosave || {};
  if (!settings || typeof settings !== 'object') {
    return;
  }

  var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
  var __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : function (text) {
    return text;
  };
  var sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : function (format) {
    var args = Array.prototype.slice.call(arguments, 1);
    return format.replace(/%s|%d/g, function () {
      var value = args.shift();
      return value === undefined ? '' : value;
    });
  };

  var apiFetch = window.wp && window.wp.apiFetch ? window.wp.apiFetch : null;
  if (!apiFetch) {
    return;
  }

  var endpoint = typeof settings.endpoint === 'string' ? settings.endpoint : '';
  var nonce = typeof settings.nonce === 'string' ? settings.nonce : '';
  var postId = parseInt(settings.postId, 10) || 0;

  if (!endpoint || !nonce || !postId) {
    return;
  }

  var root = document.querySelector('[data-ap-autosave-root]');
  if (!root) {
    return;
  }

  var form = root.querySelector('#ap-profile-form');
  if (!form) {
    return;
  }

  var statusEl = document.getElementById('ap-save-status');
  if (!statusEl) {
    return;
  }

  var strings = settings.strings || {};
  var savingLabel = strings.saving || 'Saving…';
  var savedJustNowLabel = strings.savedJustNow || 'Saved just now';
  var savedAgoLabel = strings.savedAgo || 'Saved %s ago';
  var failedLabel = strings.failed || 'Failed to save. Retry?';
  var sessionExpiredLabel = strings.sessionExpired || 'Your session expired. Please refresh.';
  var retryingInLabel = strings.retryingIn || 'Retrying in %d seconds…';
  var savingErrorLabel = strings.savingError || 'Please fix the highlighted field.';

  var debounceTimer = null;
  var backgroundTimer = null;
  var retryTimer = null;
  var dirty = false;
  var pending = false;
  var lastSavedAt = 0;
  var lastSnapshot = '';

  var retryDelay = 0;
  var beforeUnloadAttached = false;

  function fieldNodes(field) {
    return Array.prototype.slice.call(form.querySelectorAll('[data-field="' + field + '"]'));
  }

  function getFieldValue(field) {
    var nodes = fieldNodes(field);
    if (!nodes.length) {
      return null;
    }

    if (nodes.length > 1) {
      if ('socials' === field || 'gallery' === field) {
        return nodes.map(function (node) {
          return (node.value || '').trim();
        }).filter(function (value) {
          return value !== '';
        });
      }
    }

    var element = nodes[0];
    var type = element.type || element.nodeName;

    if (type === 'select-one') {
      return element.value;
    }

    if (type === 'textarea') {
      return element.value;
    }

    if (type === 'hidden' || type === 'text' || type === 'url') {
      if ('featured_media' === field) {
        return parseInt(element.value, 10) || 0;
      }

      if ('gallery' === field) {
        var galleryValues = nodes.map(function (node) {
          return parseInt(node.value, 10) || 0;
        }).filter(function (value) {
          return value > 0;
        });
        return galleryValues;
      }

      return element.value;
    }

    return element.value;
  }

  function collectPayload() {
    var payload = {};
    payload.title = (getFieldValue('title') || '').trim();
    payload.tagline = (getFieldValue('tagline') || '').trim();
    payload.bio = getFieldValue('bio') || '';
    payload.website_url = (getFieldValue('website_url') || '').trim();

    var socials = getFieldValue('socials');
    if (Array.isArray(socials)) {
      payload.socials = socials;
    } else if (socials) {
      payload.socials = [socials];
    } else {
      payload.socials = [];
    }

    payload.featured_media = parseInt(getFieldValue('featured_media'), 10) || 0;

    var gallery = getFieldValue('gallery');
    if (Array.isArray(gallery)) {
      payload.gallery = gallery.map(function (value) {
        return parseInt(value, 10) || 0;
      }).filter(function (value) {
        return value > 0;
      });
    } else {
      payload.gallery = [];
    }

    payload.visibility = getFieldValue('visibility') || '';
    payload.status = getFieldValue('status') || '';

    return payload;
  }

  function computeSnapshot() {
    try {
      return JSON.stringify(collectPayload());
    } catch (error) {
      return '';
    }
  }

  function setDirty(value) {
    dirty = value;
    updateBeforeUnload();
  }

  function markDirty() {
    if (pending) {
      return;
    }

    var snapshot = computeSnapshot();
    if (snapshot !== lastSnapshot) {
      setDirty(true);
      scheduleSave();
    }
  }

  function updateBeforeUnload() {
    if (dirty && !beforeUnloadAttached) {
      window.addEventListener('beforeunload', beforeUnloadHandler);
      beforeUnloadAttached = true;
    } else if (!dirty && beforeUnloadAttached) {
      window.removeEventListener('beforeunload', beforeUnloadHandler);
      beforeUnloadAttached = false;
    }
  }

  function beforeUnloadHandler(event) {
    if (!dirty) {
      return;
    }

    event.preventDefault();
    event.returnValue = '';
    return '';
  }

  function scheduleSave() {
    if (debounceTimer) {
      window.clearTimeout(debounceTimer);
    }

    debounceTimer = window.setTimeout(function () {
      if (dirty && !pending) {
        save(false);
      }
    }, 750);
  }

  function startBackgroundTimer() {
    if (backgroundTimer) {
      window.clearInterval(backgroundTimer);
    }

    backgroundTimer = window.setInterval(function () {
      if (dirty && !pending) {
        save(false);
      }
    }, 15000);
  }

  function setStatus(text) {
    if (!statusEl) {
      return;
    }

    statusEl.textContent = text || '';
  }

  function updateSavedStatus() {
    if (!lastSavedAt) {
      return;
    }

    var diffSeconds = Math.max(0, Math.round((Date.now() - lastSavedAt) / 1000));
    if (diffSeconds <= 5) {
      setStatus(savedJustNowLabel);
      return;
    }

    var display;
    if (diffSeconds < 60) {
      display = diffSeconds + 's';
    } else if (diffSeconds < 3600) {
      display = Math.round(diffSeconds / 60) + 'm';
    } else {
      display = Math.round(diffSeconds / 3600) + 'h';
    }

    setStatus(savedAgoLabel.replace('%s', display));
  }

  function clearErrors() {
    Array.prototype.slice.call(form.querySelectorAll('[data-error]')).forEach(function (node) {
      node.textContent = '';
    });

    Array.prototype.slice.call(form.querySelectorAll('[data-field]')).forEach(function (node) {
      node.removeAttribute('aria-invalid');
    });
  }

  function applyFieldError(field, message) {
    var nodes = fieldNodes(field);
    nodes.forEach(function (node) {
      node.setAttribute('aria-invalid', 'true');
    });

    var errorNode = form.querySelector('[data-error="' + field + '"]');
    if (errorNode) {
      errorNode.textContent = message;
    }

    if (nodes.length && typeof nodes[0].focus === 'function') {
      nodes[0].focus({ preventScroll: false });
    }
  }

  function applyErrors(error) {
    if (!error || !error.data) {
      return;
    }

    if (error.data.field) {
      applyFieldError(error.data.field, error.message || savingErrorLabel);
      return;
    }

    if (error.data.fields && typeof error.data.fields === 'object') {
      Object.keys(error.data.fields).forEach(function (field) {
        applyFieldError(field, error.data.fields[field]);
      });
    }
  }

  function handleProgress(response) {
    if (!response || typeof response !== 'object') {
      return;
    }

    if (!response.progress || typeof response.progress !== 'object') {
      return;
    }

    var steps = Array.isArray(response.progress.steps) ? response.progress.steps : [];
    var percent = parseInt(response.progress.percent, 10) || 0;

    var stepButtons = root.querySelectorAll('[data-step]');
    Array.prototype.forEach.call(stepButtons, function (button) {
      var slug = button.getAttribute('data-step');
      var match = steps.find(function (step) {
        return step.slug === slug;
      });
      if (match && match.complete) {
        button.classList.add('is-complete');
      } else {
        button.classList.remove('is-complete');
      }
    });

    var bar = root.querySelector('.ap-profile-builder__progress-bar');
    if (bar) {
      bar.style.width = percent + '%';
    }
  }

  function save(fromSubmit) {
    if (!dirty && !fromSubmit) {
      return;
    }

    pending = true;
    clearErrors();
    setStatus(savingLabel);

    if (retryTimer) {
      window.clearTimeout(retryTimer);
      retryTimer = null;
      retryDelay = 0;
    }

    var payload = collectPayload();
    var snapshot = computeSnapshot();

    apiFetch({
      url: endpoint,
      method: 'POST',
      headers: {
        'X-WP-Nonce': nonce,
      },
      data: payload,
    }).then(function (data) {
      pending = false;
      lastSnapshot = snapshot;
      setDirty(false);
      lastSavedAt = Date.now();
      setStatus(savedJustNowLabel);
      handleProgress(data);
    }).catch(function (error) {
      pending = false;
      setStatus(failedLabel);

      var status = error && error.status ? error.status : (error && error.data && error.data.status ? error.data.status : 0);

      if (status) {
        if (status === 403) {
          setStatus(sessionExpiredLabel);
          setDirty(false);
          return;
        }

        if (status === 429) {
          var retryAfter = 0;
          var response = error && error.response ? error.response : null;
          if (response && response.headers && typeof response.headers.get === 'function') {
            retryAfter = parseInt(response.headers.get('Retry-After'), 10) || 0;
          }

          if (!retryAfter && error.data && error.data.retry_after) {
            retryAfter = parseInt(error.data.retry_after, 10) || 0;
          }

          retryAfter = Math.max(retryAfter, 5);
          setStatus(retryingInLabel.replace('%d', retryAfter));

          retryDelay = retryAfter;
          scheduleRetry();
          return;
        }

        if (status === 422) {
          applyErrors(error);
          setDirty(true);
          setStatus(savingErrorLabel);
          return;
        }
      }

      setDirty(true);
    });
  }

  function scheduleRetry() {
    if (retryTimer) {
      window.clearTimeout(retryTimer);
    }

    retryTimer = window.setTimeout(function () {
      retryTimer = null;
      if (dirty && !pending) {
        save(false);
      }
    }, Math.max(5000, retryDelay * 1000));
  }

  function bindFieldListeners() {
    Array.prototype.slice.call(form.querySelectorAll('[data-field]')).forEach(function (node) {
      var eventName = node.tagName === 'SELECT' ? 'change' : 'input';
      node.addEventListener(eventName, markDirty);
      node.addEventListener('change', markDirty);
    });
  }

  function bindFormSubmit() {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      save(true);
    });
  }

  function setupSocialControls() {
    var addButton = form.querySelector('[data-social-add]');
    var list = form.querySelector('[data-social-items]');

    if (!addButton || !list) {
      return;
    }

    addButton.addEventListener('click', function () {
      var index = list.querySelectorAll('.ap-profile-builder__social-item').length;
      var wrapper = document.createElement('div');
      wrapper.className = 'ap-profile-builder__social-item';

      var input = document.createElement('input');
      input.type = 'url';
      input.name = 'socials[]';
      input.placeholder = 'https://';
      input.setAttribute('data-field', 'socials');
      input.id = 'ap-social-' + index + '-' + Date.now();

      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'button-link';
      remove.setAttribute('data-social-remove', '1');
      remove.textContent = __('Remove', 'artpulse-management');

      wrapper.appendChild(input);
      wrapper.appendChild(remove);
      list.appendChild(wrapper);

      bindFieldListeners();
      markDirty();
    });

    list.addEventListener('click', function (event) {
      if (event.target && event.target.hasAttribute('data-social-remove')) {
        event.preventDefault();
        var item = event.target.closest('.ap-profile-builder__social-item');
        if (item) {
          item.parentNode.removeChild(item);
          markDirty();
        }
      }
    });
  }

  function createGalleryItem(id) {
    var container = form.querySelector('[data-gallery-items]');
    if (!container) {
      return;
    }

    var item = document.createElement('div');
    item.className = 'ap-profile-builder__gallery-item';
    item.setAttribute('data-gallery-item', String(id));

    var label = document.createElement('span');
    label.className = 'ap-profile-builder__gallery-label';
    label.textContent = sprintf(__('Media #%s', 'artpulse-management'), id);

    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'gallery[]';
    input.value = String(id);
    input.setAttribute('data-field', 'gallery');

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button-link';
    remove.setAttribute('data-gallery-remove', String(id));
    remove.textContent = __('Remove', 'artpulse-management');

    item.appendChild(label);
    item.appendChild(input);
    item.appendChild(remove);
    container.appendChild(item);
  }

  function setupGalleryControls() {
    var addButton = form.querySelector('[data-gallery-add]');
    var container = form.querySelector('[data-gallery-items]');

    if (!addButton || !container || !window.wp || !window.wp.media) {
      return;
    }

    var frame = null;

    addButton.addEventListener('click', function (event) {
      event.preventDefault();

      if (frame) {
        frame.open();
        return;
      }

      frame = window.wp.media({
        title: __('Select media', 'artpulse-management'),
        multiple: true,
      });

      frame.on('select', function () {
        var selection = frame.state().get('selection');
        selection.each(function (attachment) {
          var id = attachment.get('id');
          if (!id) {
            return;
          }

          createGalleryItem(id);
        });
        bindFieldListeners();
        markDirty();
      });

      frame.open();
    });

    container.addEventListener('click', function (event) {
      if (event.target && event.target.hasAttribute('data-gallery-remove')) {
        event.preventDefault();
        var item = event.target.closest('[data-gallery-item]');
        if (item) {
          item.parentNode.removeChild(item);
          markDirty();
        }
      }
    });
  }

  function setupFeaturedMedia() {
    var button = form.querySelector('[data-media-select="featured_media"]');
    var input = form.querySelector('input[data-field="featured_media"]');

    if (!button || !input || !window.wp || !window.wp.media) {
      return;
    }

    var frame = null;

    button.addEventListener('click', function (event) {
      event.preventDefault();

      if (frame) {
        frame.open();
        return;
      }

      frame = window.wp.media({
        title: __('Select featured image', 'artpulse-management'),
        multiple: false,
      });

      frame.on('select', function () {
        var selection = frame.state().get('selection');
        var attachment = selection.first();
        if (!attachment) {
          return;
        }
        input.value = String(attachment.get('id'));
        markDirty();
      });

      frame.open();
    });
  }

  function init() {
    lastSnapshot = computeSnapshot();
    bindFieldListeners();
    bindFormSubmit();
    setupSocialControls();
    setupGalleryControls();
    setupFeaturedMedia();
    startBackgroundTimer();

    window.setInterval(function () {
      if (!pending && !dirty) {
        updateSavedStatus();
      }
    }, 5000);
  }

  init();
})(window, document);
