jQuery(document).ready(function ($) {
    const MAX_FILES = 5;
    const MAX_SIZE = 2 * 1024 * 1024; // 2MB

    const $area = $('.ead-artist-image-upload-area');
    if (!$area.length) return;

    function enforceLimit() {
        const filled = $area.find('.ead-image-id-input').filter(function () {
            return $(this).val();
        }).length;
        const disabled = filled >= MAX_FILES;
        $area.find('.ead-upload-image-button').prop('disabled', disabled);
    }

    $area.sortable({
        items: '.ead-image-upload-container',
        stop: function () {
            $area.find('.ead-image-upload-container').each(function (idx) {
                $(this).attr('data-image-index', idx);
                const $preview = $(this).find('.ead-image-preview');
                if (!$preview.hasClass('has-image')) {
                    $preview.find('.placeholder').text((eadArtistGallery.placeholder_prefix || 'Image ') + (idx + 1));
                }
            });
        }
    });

    $area.on('eadImageSelected', '.ead-image-upload-container', function (e, attachment) {
        if (attachment && attachment.filesizeInBytes && attachment.filesizeInBytes > MAX_SIZE) {
            alert('Image exceeds 2MB limit.');
            $(this).find('.ead-remove-image-button').trigger('click');
            return;
        }
        enforceLimit();
    });

    $area.on('eadImageRemoved', '.ead-image-upload-container', enforceLimit);

    enforceLimit();
});

