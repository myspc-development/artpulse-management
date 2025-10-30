jQuery(function ($) {
  const visibilityOrder = APPortfolio.visibilities || ['public', 'members', 'private'];
  const $form = $('#ap-portfolio-form');
  const $message = $('#ap-portfolio-message');

  function showMessage(text, isError = false) {
    $message.text(text).toggleClass('ap-form-message--error', isError);
  }

  function nextVisibility(current) {
    const index = visibilityOrder.indexOf(current);
    if (index === -1) {
      return visibilityOrder[0];
    }

    return visibilityOrder[(index + 1) % visibilityOrder.length];
  }

  function statusLabel(status) {
    const map = {
      publish: 'Published',
      pending: 'Pending Review',
      draft: 'Draft',
      future: 'Scheduled',
      private: 'Private'
    };

    return map[status] || status;
  }

  $form.on('submit', function (e) {
    e.preventDefault();

    const data = {
      action: 'ap_save_portfolio',
      nonce: APPortfolio.nonce,
      title: $('input[name="title"]').val(),
      category: $('select[name="category"]').val(),
      description: $('textarea[name="description"]').val(),
      link: $('input[name="link"]').val(),
      visibility: $('select[name="visibility"]').val(),
      post_id: $('input[name="post_id"]').val() || ''
    };

    $.post(APPortfolio.ajaxUrl, data)
      .done((res) => {
        showMessage(res.data.message || 'Saved successfully.');
        setTimeout(() => {
          window.location.reload();
        }, 400);
      })
      .fail((xhr) => {
        const response = xhr.responseJSON || {};
        const message = response.data && response.data.message ? response.data.message : 'Error saving item.';
        showMessage(message, true);
      });
  });

  $(document).on('click', '.edit-item', function (e) {
    e.preventDefault();
    const $item = $(this).closest('.ap-saved-item');
    const id = $item.data('id');

    $.get(APPortfolio.ajaxUrl, {
      action: 'ap_get_portfolio_item',
      nonce: APPortfolio.nonce,
      post_id: id
    })
      .done((res) => {
        const data = res.data || {};
        $('input[name="post_id"]').val(data.id || '');
        $('input[name="title"]').val(data.title || '');
        $('textarea[name="description"]').val(data.description || '');
        $('input[name="link"]').val(data.link || '');
        $('select[name="visibility"]').val(data.visibility || 'public');
        $('select[name="category"]').val(data.category || '');
        $('html, body').animate({ scrollTop: $form.offset().top }, 200);
      })
      .fail(() => {
        showMessage('Unable to load portfolio item.', true);
      });
  });

  $(document).on('click', '.toggle-visibility', function (e) {
    e.preventDefault();
    const $button = $(this);
    const $item = $button.closest('.ap-saved-item');
    const id = $item.data('id');
    const desired = $button.data('new');

    $.post(APPortfolio.ajaxUrl, {
      action: 'ap_toggle_visibility',
      nonce: APPortfolio.nonce,
      post_id: id,
      visibility: desired
    })
      .done((res) => {
        const data = res.data || {};
        const currentVisibility = data.visibility || desired;
        const next = nextVisibility(currentVisibility);
        $button.data('new', next);
        $button.text('Set ' + next.charAt(0).toUpperCase() + next.slice(1));
        $item.find('.ap-saved-item__visibility').text(currentVisibility.charAt(0).toUpperCase() + currentVisibility.slice(1));

        if (data.status) {
          const label = statusLabel(data.status);
          $item.attr('data-status', data.status);
          $item.find('.ap-saved-item__status').text(label);
        }

        showMessage('Visibility updated.');
      })
      .fail(() => {
        showMessage('Unable to change visibility.', true);
      });
  });

  $(document).on('click', '.delete-item', function (e) {
    e.preventDefault();
    const $item = $(this).closest('.ap-saved-item');
    const id = $item.data('id');

    if (!window.confirm('Delete this portfolio item?')) {
      return;
    }

    $.post(APPortfolio.ajaxUrl, {
      action: 'ap_delete_portfolio_item',
      nonce: APPortfolio.nonce,
      post_id: id
    })
      .done(() => {
        $item.remove();
        showMessage('Item moved to trash.');
      })
      .fail(() => {
        showMessage('Unable to delete portfolio item.', true);
      });
  });
});
