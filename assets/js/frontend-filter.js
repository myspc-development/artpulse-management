document.addEventListener('DOMContentLoaded', () => {
  const filterBlock = document.querySelector('.ap-ajax-filter');
  if (!filterBlock) return;

  const controls = filterBlock.querySelectorAll('.ap-ajax-filter-controls input[type="checkbox"]');
  const resultsList = filterBlock.querySelector('.ap-ajax-filter-results ul');
  const resultsMessage = filterBlock.querySelector('.ap-ajax-filter-results p');
  const prevBtn = filterBlock.querySelector('.ap-ajax-filter-pagination .prev');
  const nextBtn = filterBlock.querySelector('.ap-ajax-filter-pagination .next');

  let currentPage = 1;
  const postsPerPage = 5; // adjust as needed

  function fetchResults(page = 1) {
    resultsList.innerHTML = '';
    resultsMessage.textContent = 'Loading...';

    // Gather selected filters
    const selectedTerms = Array.from(controls)
      .filter(cb => cb.checked)
      .map(cb => cb.value);

    // Prepare AJAX query args (adjust for your actual AJAX endpoint & params)
    const params = new URLSearchParams({
      action: 'ap_filter_posts',  // your AJAX handler name
      page: page,
      per_page: postsPerPage,
      terms: selectedTerms.join(','),
      nonce: apFrontendFilter.nonce // pass nonce localized via wp_localize_script
    });

    fetch(apFrontendFilter.ajax_url + '?' + params.toString())
      .then(response => response.json())
      .then(data => {
        resultsList.innerHTML = '';
        if (!data.posts || data.posts.length === 0) {
          resultsMessage.textContent = 'No results found.';
          prevBtn.disabled = true;
          nextBtn.disabled = true;
          return;
        }
        resultsMessage.textContent = '';

        data.posts.forEach(post => {
          const li = document.createElement('li');
          const a = document.createElement('a');
          a.href = post.link;
          a.textContent = post.title;
          li.appendChild(a);
          resultsList.appendChild(li);
        });

        currentPage = data.page || 1;
        prevBtn.disabled = currentPage <= 1;
        nextBtn.disabled = currentPage >= data.max_page;
      })
      .catch(() => {
        resultsMessage.textContent = 'Error loading results.';
      });
  }

  // Event: filter change
  controls.forEach(cb => {
    cb.addEventListener('change', () => {
      currentPage = 1;
      fetchResults(currentPage);
    });
  });

  // Event: pagination buttons
  prevBtn.addEventListener('click', () => {
    if (currentPage > 1) {
      fetchResults(currentPage - 1);
    }
  });
  nextBtn.addEventListener('click', () => {
    fetchResults(currentPage + 1);
  });

  // Initial load
  fetchResults(currentPage);
});
