document.addEventListener('DOMContentLoaded', function () {
    const artistSelect = document.getElementById('filter-artist');
    const mediumSelect = document.getElementById('filter-medium');
    if (!artistSelect || !mediumSelect) return;

    const cards = document.querySelectorAll('.artwork-gallery-card');

    function filterCards() {
        const artist = artistSelect.value;
        const medium = mediumSelect.value;

        cards.forEach(card => {
            const cardArtist = card.getAttribute('data-artist-id');
            const cardMedium = card.getAttribute('data-medium');

            const matchArtist = !artist || cardArtist === artist;
            const matchMedium = !medium || cardMedium === medium;

            if (matchArtist && matchMedium) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }

    artistSelect.addEventListener('change', filterCards);
    mediumSelect.addEventListener('change', filterCards);
});
