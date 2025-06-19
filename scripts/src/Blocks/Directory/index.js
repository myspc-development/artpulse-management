import { registerBlockType } from '@wordpress/blocks';
import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';

registerBlockType('artpulse/directory', {
  edit: ({ attributes, setAttributes }) => {
    return (
      <>
        <InspectorControls>
          <PanelBody title="Directory Settings">
            <SelectControl
              label="Type"
              value={attributes.type}
              options={[
                { label: 'Events', value: 'event' },
                { label: 'Artists', value: 'artist' },
                { label: 'Artworks', value: 'artwork' },
                { label: 'Orgs', value: 'org' }
              ]}
              onChange={(type) => setAttributes({ type })}
            />
            <RangeControl
              label="Limit"
              value={attributes.limit}
              min={1}
              max={100}
              onChange={(limit) => setAttributes({ limit })}
            />
          </PanelBody>
        </InspectorControls>
        <div className="ap-block-preview">
          <p>ArtPulse Directory will render on the front end</p>
          <p><strong>Type:</strong> {attributes.type}</p>
          <p><strong>Limit:</strong> {attributes.limit}</p>
        </div>
      </>
    );
  },
  save: ({ attributes }) => {
    return (
      <div>
        {`<!-- wp:shortcode -->[ap_directory type="${attributes.type}" limit="${attributes.limit}"]<!-- /wp:shortcode -->`}
      </div>
    );
  }
});
