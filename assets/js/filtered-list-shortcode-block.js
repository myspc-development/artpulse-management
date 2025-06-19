const { registerBlockType } = wp.blocks;
const { TextControl, PanelBody, SelectControl, RangeControl } = wp.components;
const { InspectorControls } = wp.blockEditor;
const { useState, useEffect } = wp.element;
const { apiFetch } = wp;

const postTypeOptions = [
    { label: 'Artist', value: 'ead_artist' },
    { label: 'Artwork', value: 'ead_artwork' },
    { label: 'Event', value: 'ead_event' },
    { label: 'Organization', value: 'ead_organization' },
];

registerBlockType('artpulse/filtered-list-shortcode', {
    title: 'Filtered List (Shortcode)',
    icon: 'list-view',
    category: 'widgets',
    attributes: {
        postType: { type: 'string', default: 'ead_artist' },
        taxonomy: { type: 'string', default: 'artist_specialty' },
        terms: { type: 'string', default: '' },
        postsPerPage: { type: 'number', default: 5 },
    },

    edit: (props) => {
        const { attributes, setAttributes } = props;
        const { postType, taxonomy, terms, postsPerPage } = attributes;

        const [availableTaxonomies, setAvailableTaxonomies] = useState([]);

        useEffect(() => {
            if (!postType) {
                setAvailableTaxonomies([]);
                return;
            }
            apiFetch({ path: `/wp/v2/types/${postType}` }).then(type => {
                setAvailableTaxonomies(Object.keys(type.taxonomies || {}));
            });
        }, [postType]);

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Settings" initialOpen={true}>
                        <SelectControl
                            label="Post Type"
                            value={postType}
                            options={postTypeOptions}
                            onChange={(val) => setAttributes({ postType: val, taxonomy: '', terms: '' })}
                        />
                        <SelectControl
                            label="Taxonomy"
                            value={taxonomy}
                            options={availableTaxonomies.map(t => ({ label: t, value: t }))}
                            onChange={(val) => setAttributes({ taxonomy: val, terms: '' })}
                        />
                        <TextControl
                            label="Terms (comma separated slugs)"
                            value={terms}
                            onChange={(val) => setAttributes({ terms: val })}
                        />
                        <RangeControl
                            label="Number of posts"
                            value={postsPerPage}
                            onChange={(val) => setAttributes({ postsPerPage: val })}
                            min={1}
                            max={20}
                        />
                    </PanelBody>
                </InspectorControls>
                <p>
                    This block renders a filtered list using the shortcode:<br />
                    <code>[ap_filtered_list post_type="{postType}" taxonomy="{taxonomy}" terms="{terms}" posts_per_page="{postsPerPage}"]</code>
                </p>
            </>
        );
    },

    save: () => null, // Dynamic block rendered via PHP
});
