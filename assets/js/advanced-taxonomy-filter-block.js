const { registerBlockType } = wp.blocks;
const { SelectControl, Spinner, Button } = wp.components;
const { InspectorControls } = wp.blockEditor;
const { useState, useEffect } = wp.element;
const { apiFetch } = wp;

registerBlockType('artpulse/advanced-taxonomy-filter', {
    title: 'Advanced Taxonomy Filter',
    icon: 'filter',
    category: 'widgets',
    attributes: {
        postType: { type: 'string', default: 'ead_artist' },
        taxonomy: { type: 'string', default: 'artist_specialty' },
    },

    edit: (props) => {
        const { attributes, setAttributes } = props;
        const { postType, taxonomy } = attributes;

        const [availablePostTypes, setAvailablePostTypes] = useState([]);
        const [availableTaxonomies, setAvailableTaxonomies] = useState([]);
        const [terms, setTerms] = useState([]);
        const [selectedTerm, setSelectedTerm] = useState('');
        const [posts, setPosts] = useState([]);
        const [page, setPage] = useState(1);
        const [totalPages, setTotalPages] = useState(1);
        const [loading, setLoading] = useState(false);

        useEffect(() => {
            apiFetch({ path: '/wp/v2/types' }).then(types => {
                const cpts = Object.entries(types)
                    .filter(([key, val]) => val.rest_base && key.startsWith('ead_'))
                    .map(([key]) => key);
                setAvailablePostTypes(cpts);
            });
        }, []);

        useEffect(() => {
            if (!postType) {
                setAvailableTaxonomies([]);
                return;
            }
            apiFetch({ path: `/wp/v2/types/${postType}` }).then(type => {
                setAvailableTaxonomies(Object.keys(type.taxonomies || {}));
                setSelectedTerm('');
            });
        }, [postType]);

        useEffect(() => {
            if (!taxonomy) {
                setTerms([]);
                setSelectedTerm('');
                return;
            }
            apiFetch({ path: `/wp/v2/${taxonomy}?per_page=100` }).then(setTerms);
        }, [taxonomy]);

        const fetchPosts = (pageNum = 1) => {
            setLoading(true);
            let path = `/wp/v2/${postType}?per_page=5&page=${pageNum}`;
            if (selectedTerm) {
                path += `&tax_${taxonomy}=${selectedTerm}`;
            }
            apiFetch({ path }).then((data, res) => {
                setPosts(data);
                setTotalPages(parseInt(res.headers.get('X-WP-TotalPages'), 10) || 1);
                setPage(pageNum);
                setLoading(false);
            }).catch(() => {
                setPosts([]);
                setLoading(false);
            });
        };

        useEffect(() => {
            fetchPosts();
        }, [postType, taxonomy, selectedTerm]);

        return (
            <>
                <InspectorControls>
                    <SelectControl
                        label="Post Type"
                        value={postType}
                        options={availablePostTypes.map(pt => ({ label: pt, value: pt }))}
                        onChange={(val) => setAttributes({ postType: val, taxonomy: '', selectedTerm: '' })}
                    />
                    {availableTaxonomies.length > 0 && (
                        <SelectControl
                            label="Taxonomy"
                            value={taxonomy}
                            options={availableTaxonomies.map(t => ({ label: t, value: t }))}
                            onChange={(val) => setAttributes({ taxonomy: val, selectedTerm: '' })}
                        />
                    )}
                    {terms.length > 0 && (
                        <SelectControl
                            label="Filter by Term"
                            value={selectedTerm}
                            options={[{ label: 'All', value: '' }, ...terms.map(term => ({ label: term.name, value: term.slug }))]}
                            onChange={(val) => setSelectedTerm(val)}
                        />
                    )}
                </InspectorControls>

                {loading ? <Spinner /> : (
                    <div>
                        <ul>
                            {posts.map(post => (
                                <li key={post.id}><a href={post.link} target="_blank" rel="noopener noreferrer">{post.title.rendered || '(No title)'}</a></li>
                            ))}
                        </ul>

                        <div>
                            <Button isDisabled={page <= 1} onClick={() => fetchPosts(page - 1)}>Prev</Button>
                            <span> Page {page} of {totalPages} </span>
                            <Button isDisabled={page >= totalPages} onClick={() => fetchPosts(page + 1)}>Next</Button>
                        </div>
                    </div>
                )}
            </>
        );
    },

    save: () => null,
});
