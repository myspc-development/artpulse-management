document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('ap-org-modal');
  const openBtn = document.getElementById('ap-add-event-btn');
  const closeBtn = document.getElementById('ap-modal-close');
  const form = document.getElementById('ap-org-event-form');
  const nonceField = form?.querySelector('input[name="nonce"]');
  const eventsContainer = document.getElementById('ap-org-events');
  const statusBox = document.getElementById('ap-status-message');

  const baseStatusClass = statusBox?.className ?? '';

  const getNonce = () => {
    if (nonceField && nonceField.value) {
      return nonceField.value;
    }

    return typeof APOrgDashboard !== 'undefined' ? APOrgDashboard.nonce : '';
  };

  const clearStatus = () => {
    if (!statusBox) return;
    statusBox.textContent = '';
    statusBox.className = baseStatusClass;
  };

  const showStatus = (message, type = '') => {
    if (!statusBox) return;
    const classes = [baseStatusClass];
    if (type) classes.push(type);
    statusBox.className = classes.filter(Boolean).join(' ');
    statusBox.textContent = message;
  };

  const openModal = () => {
    if (!modal) return;
    modal.classList.add('open');
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');
    clearStatus();
  };

  const closeModal = () => {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('hidden', '');
    modal.setAttribute('aria-hidden', 'true');
    clearStatus();
  };

  // Modal open/close
  openBtn?.addEventListener('click', openModal);
  closeBtn?.addEventListener('click', closeModal);

  // Form submit
  form?.addEventListener('submit', function (e) {
    e.preventDefault();
    clearStatus();

    const formData = new FormData(form);
    formData.append('action', 'ap_add_org_event');
    formData.append('nonce', getNonce());

    fetch(APOrgDashboard.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        // Update UI
        form.reset();
        if (eventsContainer && data.data?.updated_list_html) {
          eventsContainer.innerHTML = data.data.updated_list_html;
        }
        showStatus(data.data?.message || 'Event submitted successfully.', 'success');
        setTimeout(() => {
          closeModal();
        }, 1500);
      } else {
        showStatus(data.data?.message || 'Error submitting.', 'error');
      }
    })
    .catch(err => {
      console.error(err);
      showStatus('Request failed.', 'error');
    });
  });

  // Optional: Handle deletes
  eventsContainer?.addEventListener('click', function (e) {
    if (e.target.matches('.ap-delete-event')) {
      const eventId = e.target.dataset.id;
      if (!confirm('Delete this event?')) return;

      fetch(APOrgDashboard.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          action: 'ap_delete_org_event',
          nonce: getNonce(),
          event_id: eventId
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (data.data?.updated_list_html) {
            eventsContainer.innerHTML = data.data.updated_list_html;
          }
        } else {
          alert(data.data?.message || 'Failed to delete.');
        }
      });
    }
  });
});
