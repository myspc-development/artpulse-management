document.addEventListener('DOMContentLoaded', () => {
    const artistSelect = document.getElementById('filter-artist');
    const mediumSelect = document.getElementById('filter-medium');
    const styleSelect  = document.getElementById('filter-style');
    const grid = document.getElementById('artwork-grid');
    if (!artistSelect || !mediumSelect || !grid) return;

    const ajaxEnabled = window.ARTWORK_GALLERY && window.ARTWORK_GALLERY.ajaxEnabled;
    const ajaxUrl = window.ARTWORK_GALLERY ? window.ARTWORK_GALLERY.ajaxurl : null;

    const cards = Array.from(grid.children);

    function filterClient() {
        const artist = artistSelect.value;
        const medium = mediumSelect.value;
        const style  = styleSelect ? styleSelect.value : '';

        cards.forEach(card => {
            const cardArtist = card.dataset.artistId;
            const cardMedium = card.dataset.medium;
            const cardStyle  = card.dataset.style;

            const matchArtist = !artist || cardArtist === artist;
            const matchMedium = !medium || cardMedium === medium;
            const matchStyle  = !style || cardStyle === style;

            card.style.display = matchArtist && matchMedium && matchStyle ? '' : 'none';
        });
    }

    function filterAjax() {
        if (!ajaxUrl) return;
        const data = new FormData();
        data.append('action', 'filter_artworks');
        data.append('artist', artistSelect.value);
        data.append('medium', mediumSelect.value);
        if (styleSelect) {
            data.append('style', styleSelect.value);
        }

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(res => res.json())
            .then(json => {
                if (json.success) {
                    grid.innerHTML = json.data;
                }
            });
    }

    function applyFilters() {
        if (ajaxEnabled) {
            filterAjax();
        } else {
            filterClient();
        }
    }

    artistSelect.addEventListener('change', applyFilters);
    mediumSelect.addEventListener('change', applyFilters);
    if (styleSelect) {
        styleSelect.addEventListener('change', applyFilters);
    }
});
