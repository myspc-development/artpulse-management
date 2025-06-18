document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('ap-org-modal');
  const openBtn = document.getElementById('ap-add-event-btn');
  const closeBtn = document.getElementById('ap-modal-close');
  const form = document.getElementById('ap-org-event-form');
  const eventsContainer = document.getElementById('ap-org-events');
  const statusBox = document.getElementById('ap-status-message');

  // Modal open/close
  openBtn?.addEventListener('click', () => modal?.classList.add('open'));
  closeBtn?.addEventListener('click', () => modal?.classList.remove('open'));

  // Form submit
  form?.addEventListener('submit', function (e) {
    e.preventDefault();
    statusBox.textContent = '';
    statusBox.className = '';

    const formData = new FormData(form);
    formData.append('action', 'ap_add_org_event');
    formData.append('nonce', APOrgDashboard.nonce);

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
        modal.classList.remove('open');
        eventsContainer.innerHTML = data.data.updated_list_html;
      } else {
        statusBox.textContent = data.data.message || 'Error submitting.';
        statusBox.className = 'error';
      }
    })
    .catch(err => {
      console.error(err);
      statusBox.textContent = 'Request failed.';
      statusBox.className = 'error';
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
          nonce: APOrgDashboard.nonce,
          event_id: eventId
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          eventsContainer.innerHTML = data.data.updated_list_html;
        } else {
          alert(data.data.message || 'Failed to delete.');
        }
      });
    }
  });
});
