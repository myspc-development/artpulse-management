(function (window, document) {
  'use strict';

  if (!window || !document) {
    return;
  }

  var settings = window.APAutosave || {};
  if (!settings || typeof settings !== 'object') {
    return;
  }

  var apiFetch = window.wp && window.wp.apiFetch ? window.wp.apiFetch : null;
  if (!apiFetch) {
    return;
  }

  var endpoint = typeof settings.endpoint === 'string' ? settings.endpoint.trim() : '';
  var nonce = typeof settings.nonce === 'string' ? settings.nonce : '';
  var postId = parseInt(settings.postId, 10) || 0;

  if (!endpoint || !nonce || !postId) {
    return;
  }

  var root = document.querySelector('[data-ap-autosave-root]');
  if (!root) {
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

  var tracked = Array.prototype.slice.call(root.querySelectorAll('[data-ap-autosave-track]'));
  if (!tracked.length) {
    return;
  }

  var fieldMap = {};
  var errorMap = {};
  var describedByCache = {};
  var debounceTimer = null;
  var backgroundTimer = null;
  var retryTimer = null;
  var lastSavedSnapshot = '';
  var latestSnapshot = '';
  var pending = false;
  var dirty = false;
  var isEnabled = true;
  var lastSavedAt = 0;

  var sessionBanner = null;
  var statusInterval = null;

  var editorWatchers = [];
  var initialGallerySignature = '[]';

  (function initialiseGallerySignature() {
    var mediaRoot = root.querySelector('[data-ap-autosave="media"]');
    if (!mediaRoot) {
      return;
    }

    var initialIds = Array.prototype.slice.call(mediaRoot.querySelectorAll('input[name="existing_gallery_ids[]"]')).map(function (input) {
      return parseInt(input.value, 10) || 0;
    }).filter(function (value) {
      return value > 0;
    });

    initialGallerySignature = JSON.stringify(initialIds);
  })();

  Array.prototype.slice.call(root.querySelectorAll('[data-ap-autosave-field]')).forEach(function (element) {
    var field = element.getAttribute('data-ap-autosave-field');
    if (!field) {
      return;
    }

    fieldMap[field] = element;
    var describedBy = element.getAttribute('aria-describedby');
    if (describedBy) {
      describedByCache[field] = describedBy;
    }
  });

  Array.prototype.slice.call(root.querySelectorAll('[data-ap-error]')).forEach(function (element) {
    var field = element.getAttribute('data-ap-error');
    if (!field) {
      return;
    }

    if (!element.id) {
      element.id = 'ap-error-' + field;
    }

    errorMap[field] = element;
  });

  function beforeUnloadHandler(event) {
    if (!dirty || !isEnabled) {
      return;
    }

    event.preventDefault();
    event.returnValue = '';
    return '';
  }

  function updateBeforeUnload() {
    if (dirty && isEnabled) {
      window.addEventListener('beforeunload', beforeUnloadHandler);
    } else {
      window.removeEventListener('beforeunload', beforeUnloadHandler);
    }
  }

  function setStatus(text) {
    if (!statusEl) {
      return;
    }

    statusEl.textContent = text || '';
  }

  function updateSavedStatus() {
    if (!statusEl || !lastSavedAt) {
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
      var minutes = Math.round(diffSeconds / 60);
      display = minutes + 'm';
    } else {
      var hours = Math.round(diffSeconds / 3600);
      display = hours + 'h';
    }

    setStatus(savedAgoLabel.replace('%s', display));
  }

  function startSavedInterval() {
    if (statusInterval) {
      window.clearInterval(statusInterval);
    }

    statusInterval = window.setInterval(function () {
      if (!pending && !dirty) {
        updateSavedStatus();
      }
    }, 5000);
  }

  function clearErrors() {
    Object.keys(fieldMap).forEach(function (field) {
      var element = fieldMap[field];
      if (!element) {
        return;
      }

      element.removeAttribute('aria-invalid');

      if (Object.prototype.hasOwnProperty.call(describedByCache, field)) {
        element.setAttribute('aria-describedby', describedByCache[field]);
      } else {
        element.removeAttribute('aria-describedby');
      }
    });

    Object.keys(errorMap).forEach(function (field) {
      errorMap[field].textContent = '';
    });
  }

  function applyFieldErrors(fieldErrors) {
    if (!fieldErrors || typeof fieldErrors !== 'object') {
      return;
    }

    var firstInvalid = null;

    Object.keys(fieldErrors).forEach(function (field) {
      var message = fieldErrors[field];
      var element = fieldMap[field];
      var errorEl = errorMap[field];

      if (element) {
        element.setAttribute('aria-invalid', 'true');
        var describedByIds = describedByCache[field] ? describedByCache[field].split(/\s+/) : [];
        if (errorEl) {
          if (!errorEl.id) {
            errorEl.id = 'ap-error-' + field;
          }
          describedByIds.push(errorEl.id);
        }
        if (describedByIds.length) {
          element.setAttribute('aria-describedby', describedByIds.join(' ').trim());
        }
        if (!firstInvalid && typeof element.focus === 'function') {
          firstInvalid = element;
        }
      }

      if (errorEl) {
        errorEl.textContent = message;
      }
    });

    if (firstInvalid) {
      firstInvalid.focus({ preventScroll: false });
    }
  }

  function ensureSessionBanner() {
    if (sessionBanner && document.body.contains(sessionBanner)) {
      return sessionBanner;
    }

    var banner = document.createElement('div');
    banner.className = 'ap-autosave-banner';
    banner.setAttribute('role', 'alert');
    banner.textContent = sessionExpiredLabel;

    var container = root.querySelector('.ap-org-builder__header') || root;
    container.insertBefore(banner, container.firstChild || null);

    sessionBanner = banner;
    return banner;
  }

  function computeSnapshot() {
    try {
      return JSON.stringify(collectPayload());
    } catch (error) {
      return '';
    }
  }

  function markDirty() {
    if (!isEnabled) {
      return;
    }

    latestSnapshot = computeSnapshot();
    if (latestSnapshot !== lastSavedSnapshot) {
      dirty = true;
      updateBeforeUnload();
      scheduleSave();
    }
  }

  function scheduleSave() {
    if (debounceTimer) {
      window.clearTimeout(debounceTimer);
    }

    debounceTimer = window.setTimeout(function () {
      if (dirty && !pending) {
        save();
      }
    }, 750);
  }

  function startBackgroundTimer() {
    if (backgroundTimer) {
      window.clearInterval(backgroundTimer);
    }

    backgroundTimer = window.setInterval(function () {
      if (dirty && !pending) {
        save();
      }
    }, 15000);
  }

  function collectPayload() {
    var payload = {};

    if (fieldMap.title) {
      payload.title = fieldMap.title.value || '';
    }

    if (fieldMap.tagline) {
      payload.tagline = fieldMap.tagline.value || '';
    }

    if (fieldMap.website) {
      payload.website = fieldMap.website.value || '';
    }

    if (fieldMap.phone) {
      payload.phone = fieldMap.phone.value || '';
    }

    if (fieldMap.email) {
      payload.email = fieldMap.email.value || '';
    }

    if (fieldMap.address) {
      payload.address = fieldMap.address.value || '';
    }

    if (fieldMap.socials) {
      var socialsValue = fieldMap.socials.value || '';
      payload.socials = socialsValue.split(/\r?\n/).map(function (item) {
        return item.trim();
      }).filter(function (item) {
        return item !== '';
      });
    }

    if (fieldMap.visibility) {
      payload.visibility = fieldMap.visibility.value || '';
    }

    if (fieldMap.about) {
      payload.about = getEditorContent(fieldMap.about);
    }

    var locationField = root.querySelector('[data-ap-autosave-location]');
    if (locationField) {
      try {
        var parsed = JSON.parse(locationField.value);
        if (parsed && typeof parsed === 'object') {
          payload.location = parsed;
        }
      } catch (error) {
        // ignore malformed location payloads
      }
    }

    var mediaSection = root.querySelector('[data-ap-autosave="media"]');
    if (mediaSection) {
      var galleryIds = Array.prototype.slice.call(mediaSection.querySelectorAll('input[name="existing_gallery_ids[]"]')).map(function (input) {
        return parseInt(input.value, 10) || 0;
      }).filter(function (value) {
        return value > 0;
      });

      if (galleryIds.length) {
        payload.gallery_ids = galleryIds;
        initialGallerySignature = JSON.stringify(galleryIds);
      } else if (initialGallerySignature !== '[]') {
        payload.gallery_ids = [];
        initialGallerySignature = '[]';
      }

      var orderInputs = Array.prototype.slice.call(mediaSection.querySelectorAll('[data-ap-gallery-order]'));
      if (orderInputs.length) {
        var order = {};
        orderInputs.forEach(function (input) {
          var id = parseInt(input.getAttribute('data-ap-gallery-order'), 10) || 0;
          var position = parseInt(input.value, 10) || 0;
          if (id > 0 && position > 0) {
            order[id] = position;
          }
        });
        if (Object.keys(order).length) {
          payload.gallery_order = order;
        }
      }

      var featuredInput = mediaSection.querySelector('input[name="ap_featured_image"]:checked');
      if (featuredInput) {
        payload.featured_id = parseInt(featuredInput.value, 10) || 0;
      }
    }

    return payload;
  }

  function getEditorContent(element) {
    if (!element) {
      return '';
    }

    var textareaId = element.id || element.getAttribute('id');
    if (textareaId && window.tinymce) {
      var editor = window.tinymce.get(textareaId);
      if (editor) {
        return editor.getContent({ format: 'html' }) || '';
      }
    }

    return element.value || '';
  }

  function save() {
    if (!isEnabled || pending) {
      return;
    }

    if (retryTimer) {
      window.clearTimeout(retryTimer);
      retryTimer = null;
    }

    clearErrors();

    var payload = collectPayload();
    var snapshot = JSON.stringify(payload);

    if (snapshot === lastSavedSnapshot && !dirty) {
      return;
    }

    pending = true;
    setStatus(savingLabel);

    apiFetch({
      url: endpoint,
      method: 'POST',
      headers: {
        'X-WP-Nonce': nonce,
      },
      data: payload,
    }).then(function () {
      pending = false;
      lastSavedSnapshot = snapshot;
      lastSavedAt = Date.now();

      if (snapshot === latestSnapshot) {
        dirty = false;
      }

      updateBeforeUnload();
      updateSavedStatus();
      startSavedInterval();
    }).catch(function (error) {
      pending = false;
      dirty = true;
      updateBeforeUnload();
      handleError(error);
    });
  }

  function handleError(error) {
    if (!error) {
      setStatus(failedLabel);
      return;
    }

    var status = error.status || (error.data && error.data.status) || 0;

    if (status === 403) {
      isEnabled = false;
      ensureSessionBanner();
      setStatus(failedLabel);
      return;
    }

    if (status === 429) {
      var retryAfter = 0;
      if (error.headers && typeof error.headers.get === 'function') {
        var headerValue = error.headers.get('Retry-After');
        if (headerValue) {
          retryAfter = parseInt(headerValue, 10) || 0;
        }
      }

      if (!retryAfter && error.data && error.data.retry_after) {
        retryAfter = parseInt(error.data.retry_after, 10) || 0;
      }

      retryAfter = retryAfter > 0 ? retryAfter : 15;
      setStatus(retryingInLabel.replace('%d', retryAfter));

      retryTimer = window.setTimeout(function () {
        retryTimer = null;
        if (dirty && !pending) {
          save();
        }
      }, retryAfter * 1000);

      return;
    }

    if (status === 422 && error.data && error.data.errors) {
      applyFieldErrors(error.data.errors);
    }

    setStatus(failedLabel);
  }

  function initEditorWatcher(textarea) {
    if (!textarea || !textarea.id || !window || !window.tinymce) {
      return;
    }

    var intervalId = window.setInterval(function () {
      var editor = window.tinymce.get(textarea.id);
      if (editor) {
        editor.on('change keyup paste setcontent', markDirty);
        window.clearInterval(intervalId);
      }
    }, 300);

    editorWatchers.push(intervalId);
  }

  tracked.forEach(function (element) {
    element.addEventListener('input', markDirty, { passive: true });
    element.addEventListener('change', markDirty, { passive: true });
  });

  if (fieldMap.about) {
    initEditorWatcher(fieldMap.about);
  }

  latestSnapshot = computeSnapshot();
  lastSavedSnapshot = latestSnapshot;
  lastSavedAt = Date.now();
  updateSavedStatus();
  startBackgroundTimer();
  startSavedInterval();

  window.addEventListener('unload', function () {
    if (debounceTimer) {
      window.clearTimeout(debounceTimer);
    }
    if (backgroundTimer) {
      window.clearInterval(backgroundTimer);
    }
    if (retryTimer) {
      window.clearTimeout(retryTimer);
    }
    editorWatchers.forEach(function (intervalId) {
      window.clearInterval(intervalId);
    });
    if (statusInterval) {
      window.clearInterval(statusInterval);
    }
  });
})(window, document);
