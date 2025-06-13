jQuery(function($){
  var frame;
  $('.ead-upload-images').on('click', function(e){
    e.preventDefault();

    // If the media frame already exists, reopen it
    if (frame) {
      frame.open();
      return;
    }

    // Create a new media frame
    frame = wp.media({
      title: 'Select up to 5 artwork images',
      button: { text: 'Use these images' },
      library: { type: 'image' },
      multiple: true
    });

    // When images are selected, update the hidden input and preview
    frame.on('select', function(){
      var selection = frame.state().get('selection').toArray();
      if (selection.length > 5) {
        alert('Please select no more than 5 images.');
        return;
      }
      var ids = selection.map(function(att){ return att.id; });
      $('#ead_artwork_image_ids').val(ids.join(','));

      // Render thumbnails in preview container
      var preview = $('#ead-image-preview').empty();
      selection.forEach(function(att){
        var thumb = att.attributes.sizes && att.attributes.sizes.thumbnail
          ? att.attributes.sizes.thumbnail.url
          : att.attributes.url;
        $('<img>')
          .attr('src', thumb)
          .css({ width: '75px', height: '75px', objectFit: 'cover', margin: '4px' })
          .appendTo(preview);
      });
    });

    frame.open();
  });
});