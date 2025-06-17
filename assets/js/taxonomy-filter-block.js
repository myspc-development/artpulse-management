const { registerBlockType } = wp.blocks;
const { SelectControl, CheckboxControl, PanelBody } = wp.components;
const { InspectorControls } = wp.blockEditor;
const { useState, useEffect } = wp.element;
const { apiFetch } = wp;

registerBlockType('artpulse/taxonomy-filter', {
    title: 'Taxonomy Filter',
    icon: 'filter',
    category: 'widgets',
    attributes: {
        postType: { type: 'string', default: 'ead_artist' },
        taxonomy: { type: 'string', default: 'artist_specialty' },
        terms: { type: 'array', default: [] },
    },
    edit: (props) => {
        const { attributes, setAttributes } = props;
        const { postType, taxonomy, terms } = attributes;

        const [availablePostTypes, setAvailablePostTypes] = useState([]);
        const [availableTaxonomies, setAvailableTaxonomies] = useState([]);
        const [taxonomyTerms, setTaxonomyTerms] = useState([]);

        useEffect(() => {
            // Get all custom post types with REST support
            apiFetch({ path: '/wp/v2/types' }).then((types) => {
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
            apiFetch({ path: `/wp/v2/types/${postType}` }).then((type) => {
                const taxKeys = Object.keys(type.taxonomies || {});
                setAvailableTaxonomies(taxKeys);
            });
        }, [postType]);

        useEffect(() => {
            if (!taxonomy) {
                setTaxonomyTerms([]);
                return;
            }
            apiFetch({ path: `/wp/v2/${taxonomy}?per_page=100` }).then(setTaxonomyTerms);
        }, [taxonomy]);

        const toggleTerm = (termSlug) => {
            const newTerms = terms.includes(termSlug)
                ? terms.filter((t) => t !== termSlug)
                : [...terms, termSlug];
            setAttributes({ terms: newTerms });
        };

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Settings" initialOpen={true}>
                        <SelectControl
                            label="Post Type"
                            value={postType}
                            options={availablePostTypes.map((pt) => ({ label: pt, value: pt }))}
                            onChange={(val) => setAttributes({ postType: val, taxonomy: '', terms: [] })}
                        />
                        {availableTaxonomies.length > 0 && (
                            <SelectControl
                                label="Taxonomy"
                                value={taxonomy}
                                options={availableTaxonomies.map((tax) => ({ label: tax, value: tax }))}
                                onChange={(val) => setAttributes({ taxonomy: val, terms: [] })}
                            />
                        )}
                        {taxonomyTerms.length > 0 && (
                            <>
                                <p>Select Terms:</p>
                                {taxonomyTerms.map((term) => (
                                    <CheckboxControl
                                        key={term.id}
                                        label={term.name}
                                        checked={terms.includes(term.slug)}
                                        onChange={() => toggleTerm(term.slug)}
                                    />
                                ))}
                            </>
                        )}
                    </PanelBody>
                </InspectorControls>
                <p>Front-end list will display filtered posts.</p>
            </>
        );
    },
    save: () => null, // Server-rendered block
});
