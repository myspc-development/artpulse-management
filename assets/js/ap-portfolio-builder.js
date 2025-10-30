jQuery(function ($) {
  let imageUrl = '';

  // Launch media uploader
  $(document).on('click', '#ap-upload-image', function (e) {
    e.preventDefault();
    const frame = wp.media({
      title: 'Select or Upload Image',
      button: { text: 'Use this image' },
      multiple: false
    });

    frame.on('select', function () {
      const attachment = frame.state().get('selection').first().toJSON();
      imageUrl = attachment.url;
      $('#ap-preview').attr('src', imageUrl).show();
    });

    frame.open();
  });

  $('#ap-portfolio-form').on('submit', function (e) {
    e.preventDefault();

    const data = {
      action: 'ap_save_portfolio',
      nonce: APPortfolio.nonce,
      title: $('input[name="title"]').val(),
      category: $('select[name="category"]').val(),
      description: $('textarea[name="description"]').val(),
      link: $('input[name="link"]').val(),
      visibility: $('select[name="visibility"]').val(),
      image: imageUrl,
      post_id: $('input[name="post_id"]').val() || ''
    };

    $.post(APPortfolio.ajaxUrl, data, function (res) {
      $('#ap-portfolio-message').text(res.data.message);
      $('#ap-portfolio-form')[0].reset();
      $('#ap-preview').hide();
    }).fail(() => {
      $('#ap-portfolio-message').text('Error saving item.');
    });
  });
});
