jQuery(document).ready(function ($) {
    $('.ead-upload-image-button').on('click', function (e) {
        e.preventDefault();
        const $container = $(this).closest('.ead-image-upload-container');
        const $idInput = $container.find('.ead-image-id-input');
        const $preview = $container.find('.ead-image-preview');
        const $remove = $container.find('.ead-remove-image-button');

        const frame = wp.media({
            title: eadOrgGallery.select_image_title || 'Select Image',
            button: { text: eadOrgGallery.use_image_button || 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            $idInput.val(attachment.id);
            $preview.css('background-image', 'url(' + attachment.url + ')').addClass('has-image').html('');
            $remove.removeClass('hidden');
        });

        frame.open();
    });

    $('.ead-remove-image-button').on('click', function (e) {
        e.preventDefault();
        const $container = $(this).closest('.ead-image-upload-container');
        const $idInput = $container.find('.ead-image-id-input');
        const $preview = $container.find('.ead-image-preview');
        const index = $container.data('image-index');
        $idInput.val('');
        $preview.css('background-image', '').removeClass('has-image')
                .html('<span class="placeholder">' + (eadOrgGallery.placeholder_prefix || 'Image ') + (index + 1) + '</span>');
        $(this).addClass('hidden');
    });
});
