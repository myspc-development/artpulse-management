const { registerBlockType } = wp.blocks;
const { SelectControl, PanelBody } = wp.components;
const { useState, useEffect } = wp.element;
const { apiFetch } = wp;

registerBlockType('artpulse/ajax-filter', {
    title: 'AJAX Taxonomy Filter',
    icon: 'filter',
    category: 'widgets',

    attributes: {
        postType: { type: 'string', default: 'ead_artist' },
        taxonomy: { type: 'string', default: 'artist_specialty' },
    },

    edit({ attributes, setAttributes }) {
        const { postType, taxonomy } = attributes;
        const [postTypes, setPostTypes] = useState([]);
        const [taxonomies, setTaxonomies] = useState([]);

        useEffect(() => {
            apiFetch({ path: '/wp/v2/types' }).then(types => {
                const filtered = Object.entries(types).filter(([key]) => key.startsWith('ead_'));
                setPostTypes(filtered.map(([key]) => ({ label: key, value: key })));
            });
        }, []);

        useEffect(() => {
            if (!postType) {
                setTaxonomies([]);
                return;
            }
            apiFetch({ path: `/wp/v2/types/${postType}` }).then(type => {
                setTaxonomies(Object.keys(type.taxonomies || {}).map(t => ({ label: t, value: t })));
            });
        }, [postType]);

        return (
            <>
                <PanelBody title="Settings" initialOpen>
                    <SelectControl
                        label="Post Type"
                        value={postType}
                        options={postTypes}
                        onChange={val => setAttributes({ postType: val, taxonomy: '' })}
                    />
                    <SelectControl
                        label="Taxonomy"
                        value={taxonomy}
                        options={taxonomies}
                        onChange={val => setAttributes({ taxonomy: val })}
                    />
                </PanelBody>
                <p>This block renders a live filter widget on the frontend.</p>
            </>
        );
    },

    save() {
        return null; // Rendered via PHP and frontend JS
    },
});

// Frontend JS — Render filter UI and fetch filtered posts dynamically
document.addEventListener('DOMContentLoaded', () => {
    const containers = document.querySelectorAll('.artpulse-ajax-filter-block');

    containers.forEach(container => {
        const postType = container.dataset.postType;
        const taxonomy = container.dataset.taxonomy;

        if (!postType || !taxonomy) return;

        const filterDiv = document.createElement('div');
        filterDiv.className = 'ap-ajax-filter-controls';

        const resultsDiv = document.createElement('div');
        resultsDiv.className = 'ap-ajax-filter-results';

        container.appendChild(filterDiv);
        container.appendChild(resultsDiv);

        // Fetch taxonomy terms to build checkboxes
        wp.apiFetch({ path: `/wp/v2/${taxonomy}?per_page=100` }).then(terms => {
            if (!terms.length) {
                resultsDiv.textContent = 'No filter terms available.';
                return;
            }

            terms.forEach(term => {
                const label = document.createElement('label');
                label.style.marginRight = '10px';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = term.slug;
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(' ' + term.name));
                filterDiv.appendChild(label);

                checkbox.addEventListener('change', () => fetchAndRender());
            });

            fetchAndRender();
        });

        function fetchAndRender(page = 1) {
            const checkedTerms = Array.from(filterDiv.querySelectorAll('input[type=checkbox]:checked')).map(input => input.value);
            let path = `/artpulse/v1/filtered-posts?post_type=${postType}&taxonomy=${taxonomy}&per_page=5&page=${page}`;

            if (checkedTerms.length) {
                path += `&terms=${checkedTerms.join(',')}`;
            }

            resultsDiv.innerHTML = '<p>Loading…</p>';

            wp.apiFetch({ path }).then(data => {
                if (!data.posts.length) {
                    resultsDiv.innerHTML = '<p>No posts found.</p>';
                    return;
                }

                const ul = document.createElement('ul');
                data.posts.forEach(post => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = post.link;
                    a.textContent = post.title;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    li.appendChild(a);
                    ul.appendChild(li);
                });

                resultsDiv.innerHTML = '';
                resultsDiv.appendChild(ul);

                if (data.totalPages > 1) {
                    const paginationDiv = document.createElement('div');
                    paginationDiv.className = 'ap-ajax-filter-pagination';

                    if (page > 1) {
                        const prevBtn = document.createElement('button');
                        prevBtn.textContent = 'Previous';
                        prevBtn.onclick = () => fetchAndRender(page - 1);
                        paginationDiv.appendChild(prevBtn);
                    }

                    const pageInfo = document.createElement('span');
                    pageInfo.textContent = ` Page ${page} of ${data.totalPages} `;
                    paginationDiv.appendChild(pageInfo);

                    if (page < data.totalPages) {
                        const nextBtn = document.createElement('button');
                        nextBtn.textContent = 'Next';
                        nextBtn.onclick = () => fetchAndRender(page + 1);
                        paginationDiv.appendChild(nextBtn);
                    }

                    resultsDiv.appendChild(paginationDiv);
                }
            });
        }
    });
});
