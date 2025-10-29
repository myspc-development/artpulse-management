(function () {
  const dashboardConfig = window.ArtPulseDashboards || {};
  const STRINGS = dashboardConfig.strings || {};

  function onReady(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
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

      if (window.ArtPulseDashboardsApp && typeof window.ArtPulseDashboardsApp.init === 'function') {
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
      });
    }

    const settings = window.wpApiSettings || {};
    const root = settings.root || '';
    const nonce = settings.nonce || '';
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
