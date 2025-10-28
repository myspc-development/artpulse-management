(function () {
    var settings = window.APArtistBuilderEmpty || {};
    var button = document.querySelector('[data-ap-artist-create]');

    if (!button) {
        return;
    }

    var statusEl = document.querySelector('[data-ap-artist-create-status]');
    var errorEl = document.querySelector('[data-ap-artist-create-error]');
    var endpoint = settings.endpoint || '';
    var nonce = settings.nonce || '';
    var builderUrl = settings.builderUrl || '';
    var creatingLabel = settings.creatingLabel || '';
    var successLabel = settings.successLabel || '';
    var fallbackError = settings.errorMessage || '';

    var handleError = function (message) {
        if (statusEl) {
            statusEl.textContent = '';
        }

        if (errorEl) {
            errorEl.textContent = message || fallbackError;
            errorEl.hidden = false;
        }

        button.disabled = false;
    };

    button.addEventListener('click', function () {
        if (!endpoint || !nonce) {
            handleError(fallbackError);
            return;
        }

        if (statusEl) {
            statusEl.textContent = creatingLabel;
        }

        if (errorEl) {
            errorEl.textContent = '';
            errorEl.hidden = true;
        }

        button.disabled = true;

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: '{}'
        })
            .then(function (response) {
                if (!response.ok) {
                    return response.json().catch(function () {
                        return {};
                    }).then(function (data) {
                        var message = data && data.message ? data.message : fallbackError;
                        throw new Error(message);
                    });
                }

                return response.json();
            })
            .then(function (data) {
                if (statusEl) {
                    statusEl.textContent = successLabel;
                }

                var postId = data && data.postId ? data.postId : null;
                if (!postId) {
                    throw new Error(fallbackError);
                }

                var redirectUrl = builderUrl || window.location.href;

                try {
                    var url = new URL(redirectUrl, window.location.origin);
                    url.searchParams.set('post_id', postId);
                    window.location.href = url.toString();
                    return;
                } catch (error) {
                    var separator = redirectUrl.indexOf('?') === -1 ? '?' : '&';
                    window.location.href = redirectUrl + separator + 'post_id=' + encodeURIComponent(postId);
                }
            })
            .catch(function (error) {
                handleError(error && error.message ? error.message : fallbackError);
            });
    });
})();
