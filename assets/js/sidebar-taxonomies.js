const { registerPlugin } = wp.plugins;
const { PluginDocumentSettingPanel } = wp.editPost;
const { PanelBody, SelectControl } = wp.components;
const { withSelect, withDispatch } = wp.data;
const { compose } = wp.compose;
const { Fragment } = wp.element;

const taxonomiesConfig = {
    ead_artist: [
        { slug: 'artist_specialty', label: 'Artist Specialties' },
    ],
    ead_artwork: [
        { slug: 'artwork_style', label: 'Artwork Styles' },
    ],
    ead_event: [
        { slug: 'event_type', label: 'Event Types' },
    ],
    ead_organization: [
        { slug: 'organization_category', label: 'Organization Categories' },
    ],
};

const SidebarTaxonomies = (props) => {
    const { postType, terms, setTerms } = props;
    const taxonomies = taxonomiesConfig[postType] || [];

    if (taxonomies.length === 0) {
        return null;
    }

    return (
        <PluginDocumentSettingPanel name="ap-taxonomies" title="Taxonomies">
            {taxonomies.map(({ slug, label }) => {
                const selectedTerms = terms[slug] || [];
                return (
                    <SelectControl
                        key={slug}
                        multiple
                        label={label}
                        value={selectedTerms}
                        options={props.allTerms[slug] || []}
                        onChange={(newTerms) => setTerms(slug, newTerms)}
                    />
                );
            })}
        </PluginDocumentSettingPanel>
    );
};

const SidebarTaxonomiesWithData = compose([
    withSelect((select) => {
        const postType = select('core/editor').getCurrentPostType();
        const terms = {};
        const allTerms = {};

        if (!postType) return { postType, terms, allTerms };

        Object.entries(taxonomiesConfig[postType] || []).forEach(([index, { slug }]) => {
            terms[slug] = select('core/editor').getEditedPostAttribute('taxonomies')[slug] || [];
            allTerms[slug] = select('core').getEntityRecords('taxonomy', slug, { per_page: -1 }) || [];
        });

        return { postType, terms, allTerms };
    }),
    withDispatch((dispatch) => {
        return {
            setTerms: (taxonomy, values) => {
                const { editPost } = dispatch('core/editor');
                editPost({ taxonomies: { [taxonomy]: values } });
            },
        };
    }),
])(SidebarTaxonomies);

registerPlugin('artpulse-taxonomy-sidebar', {
    render: SidebarTaxonomiesWithData,
});
