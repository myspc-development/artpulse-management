document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('.ap-submission-form');
  if (!forms.length || typeof APSubmission === 'undefined') {
    return;
  }

  forms.forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const messageContainer = form.previousElementSibling && form.previousElementSibling.classList.contains('ap-form-messages')
        ? form.previousElementSibling
        : null;

      const showNotice = (message, type = 'error') => {
        if (messageContainer) {
          messageContainer.innerHTML = '';
          const notice = document.createElement('div');
          notice.className = `ap-notice ap-notice-${type}`;
          notice.textContent = message;
          messageContainer.appendChild(notice);
        } else {
          window.alert(message);
        }
      };

      const clearFieldErrors = () => {
        form.querySelectorAll('[aria-invalid="true"]').forEach((field) => {
          field.removeAttribute('aria-invalid');
        });
      };

      clearFieldErrors();

      const formData = new FormData(form);
      const postType = form.dataset.postType || formData.get('post_type') || 'artpulse_event';
      const titleField = form.querySelector('[name="title"]');
      const titleValue = (formData.get('title') || '').toString().trim();
      const titleMessage = titleField && titleField.dataset.required ? titleField.dataset.required : 'Title is required.';

      if (!titleValue) {
        showNotice(titleMessage);
        if (titleField) {
          titleField.setAttribute('aria-invalid', 'true');
          titleField.focus();
        }
        return;
      }

      const requiredFields = Array.from(form.querySelectorAll('[data-required]'));
      const missingMessages = [];

      requiredFields.forEach((field) => {
        const value = formData.get(field.name);
        const stringValue = typeof value === 'string' ? value.trim() : value;
        const isEmpty = stringValue === null || stringValue === '';
        if (isEmpty) {
          field.setAttribute('aria-invalid', 'true');
          if (field.dataset.required) {
            missingMessages.push(field.dataset.required);
          }
        }
      });

      if (missingMessages.length) {
        showNotice(missingMessages.join(' '));
        return;
      }

      const payload = { post_type: postType, title: titleValue };
      const skipKeys = new Set(['title', 'images[]', 'ap_event_nonce', 'ap_submission_nonce', 'ap_submit_event', 'post_type']);

      for (const [key, value] of formData.entries()) {
        if (skipKeys.has(key)) {
          continue;
        }

        if (value instanceof File) {
          continue;
        }

        const normalizedValue = typeof value === 'string' ? value.trim() : value;
        if (normalizedValue === '' || normalizedValue === null) {
          continue;
        }

        payload[key] = normalizedValue;
      }

      if (payload.event_organization) {
        payload.event_organization = parseInt(payload.event_organization, 10) || 0;
      }

      if (payload.artist_org) {
        payload.artist_org = parseInt(payload.artist_org, 10) || 0;
      }

      const mediaField = form.querySelector('input[type="file"][name="images[]"]');
      const images = mediaField ? Array.from(mediaField.files) : [];
      const imageIds = [];

      const submitButton = form.querySelector('button[type="submit"]');
      const originalButtonText = submitButton ? submitButton.textContent : '';

      try {
        if (submitButton) {
          submitButton.disabled = true;
          submitButton.textContent = submitButton.dataset.loadingText || 'Submitting…';
        }

        for (const file of images) {
          const mediaId = await uploadMedia(file);
          imageIds.push(mediaId);
        }

        if (imageIds.length) {
          payload.image_ids = imageIds;
        }

        const response = await fetch(APSubmission.endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': APSubmission.nonce
          },
          body: JSON.stringify(payload)
        });

        const data = await safeParseJson(response);

        if (!response.ok) {
          const message = data && data.message ? data.message : 'Submission failed.';
          throw new Error(message);
        }

        form.reset();
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalButtonText;
        }

        showNotice('Submission received! It will be reviewed shortly.', 'success');
      } catch (error) {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalButtonText;
        }

        showNotice(error.message || 'Submission failed.');
      }
    });
  });

  async function uploadMedia(file) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(APSubmission.mediaEndpoint, {
      method: 'POST',
      headers: {
        'X-WP-Nonce': APSubmission.nonce
      },
      body: formData
    });

    if (!response.ok) {
      const error = await safeParseJson(response);
      throw new Error(error && error.message ? error.message : 'Image upload failed');
    }

    const result = await response.json();
    return result.id;
  }

  async function safeParseJson(response) {
    try {
      return await response.clone().json();
    } catch (e) {
      return null;
    }
  }
});
