jQuery(document).ready(function($) {
    const $form = $('#ead-submit-event-form');
    const $submitButton = $('#ead-submit-event-button');
    const $spinner = $('#ead-submit-event-loading');
    const $messageBox = $('#ead-submit-event-message');
    const $galleryInput = $('#ead_event_gallery');
    const $galleryPreview = $('#ead-event-image-preview-area');
    const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
    const MAX_GALLERY_FILES = 5;
    let galleryFiles = [];

    if (!$form.length) return;

    // ======== Gallery Drag & Drop + Preview ========
    function refreshGalleryPreview() {
        $galleryPreview.empty();
        galleryFiles.forEach((file, idx) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const $imgWrap = $('<div class="ead-img-preview-thumb"></div>');
                const $img = $('<img>').attr('src', e.target.result);
                const $delBtn = $('<button type="button" class="ead-img-remove-btn" title="Remove image" tabindex="0">&times;</button>');
                $delBtn.on('click', function() {
                    galleryFiles.splice(idx, 1);
                    refreshGalleryPreview();
                });
                $imgWrap.append($img).append($delBtn);
                $galleryPreview.append($imgWrap);
            };
            reader.readAsDataURL(file);
        });
    }

    // File input selection
    $galleryInput.on('change', function() {
        let newFiles = Array.from(this.files);
        let total = galleryFiles.length + newFiles.length;
        if (total > MAX_GALLERY_FILES) {
            showFormMessage('error', `Maximum ${MAX_GALLERY_FILES} files allowed.`);
            return;
        }
        for (const file of newFiles) {
            if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                showFormMessage('error', `Invalid file type: ${file.name}`);
                return;
            }
            if (file.size > MAX_FILE_SIZE) {
                showFormMessage('error', `File too large: ${file.name} (max 2MB)`);
                return;
            }
            galleryFiles.push(file);
        }
        refreshGalleryPreview();
        this.value = ''; // Reset input so same files can be added again if removed
    });

    // Drag-and-drop zone
    $galleryPreview.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('drag-over');
    }).on('dragleave drop', function(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');
    }).on('drop', function(e) {
        e.preventDefault();
        const dtFiles = e.originalEvent.dataTransfer.files;
        let newFiles = Array.from(dtFiles);
        if (galleryFiles.length + newFiles.length > MAX_GALLERY_FILES) {
            showFormMessage('error', `Maximum ${MAX_GALLERY_FILES} files allowed.`);
            return;
        }
        for (const file of newFiles) {
            if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                showFormMessage('error', `Invalid file type: ${file.name}`);
                return;
            }
            if (file.size > MAX_FILE_SIZE) {
                showFormMessage('error', `File too large: ${file.name} (max 2MB)`);
                return;
            }
            galleryFiles.push(file);
        }
        refreshGalleryPreview();
    });

    // Keyboard remove for accessibility
    $galleryPreview.on('keydown', '.ead-img-remove-btn', function(e) {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            $(this).click();
        }
    });

    // ======== Error & Message Utilities ========
    function clearErrors() {
        $form.find('.ead-field-error').remove();
        $messageBox.hide().removeClass('notice-error notice-success').text('');
    }
    function showFieldError($field, msg) {
        $field.after('<span class="ead-field-error" style="color:red;font-size:12px;">' + msg + '</span>');
    }
    function showFormMessage(type, msg) {
        $messageBox.removeClass('notice-success notice-error')
            .addClass(type === 'success' ? 'notice-success' : 'notice-error')
            .text(msg)
            .fadeIn();
    }

    // ======== AJAX Form Submission ========
    $submitButton.on('click', function(e) {
        e.preventDefault();
        clearErrors();

        let isValid = true;
        let firstInvalid = null;

        // Validate all required fields
        $form.find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                showFieldError($(this), 'Required field');
                if (!firstInvalid) firstInvalid = $(this);
            }
        });

        // Email validation
        const $email = $('#ead_event_organizer_email');
        if ($email.length && $email.val() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($email.val())) {
            isValid = false;
            showFieldError($email, 'Invalid email');
            if (!firstInvalid) firstInvalid = $email;
        }

        // Gallery count validation
        if (galleryFiles.length > MAX_GALLERY_FILES) {
            isValid = false;
            showFieldError($galleryInput, `Maximum ${MAX_GALLERY_FILES} files allowed.`);
            if (!firstInvalid) firstInvalid = $galleryInput;
        }

        if (!isValid) {
            if (firstInvalid) {
                $('html, body').animate({ scrollTop: firstInvalid.offset().top - 120 }, 500);
            }
            showFormMessage('error', 'Please fill all required fields.');
            return;
        }

        // Build FormData for AJAX (including files and all fields)
        let formData = new FormData($form[0]);
        formData.delete('gallery[]'); // Remove old file input, if present
        galleryFiles.forEach((file, i) => {
            formData.append('gallery[]', file, file.name);
        });

        $submitButton.prop('disabled', true).text('Submitting...');
        $spinner.show();

        $.ajax({
            url: EAD_EVENTS.apiUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', EAD_EVENTS.nonce);
            },
            success: function(response) {
                showFormMessage('success', EAD_EVENTS.successMessage || 'Event submitted successfully!');
                $form[0].reset();
                galleryFiles = [];
                refreshGalleryPreview();
            },
            error: function(xhr) {
                let msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : (EAD_EVENTS.errorMessage || 'Error submitting event.');
                showFormMessage('error', msg);
            },
            complete: function() {
                $spinner.hide();
                $submitButton.prop('disabled', false).text('Submit Event');
            }
        });
    });
});
