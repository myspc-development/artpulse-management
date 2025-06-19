#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="$(dirname "$0")"
BLOCK_DIR="$PLUGIN_DIR/src/Blocks/Directory"

echo "📦 Creating Directory block scaffold…"

# 1. Create block folder
mkdir -p "$BLOCK_DIR"

# 2. block.json
cat > "$BLOCK_DIR/block.json" <<'EOF'
{
  "apiVersion": 2,
  "name": "artpulse/directory",
  "title": "ArtPulse Directory",
  "category": "widgets",
  "icon": "grid-view",
  "supports": {
    "html": false
  },
  "attributes": {
    "type": {
      "type": "string",
      "default": "event"
    },
    "limit": {
      "type": "number",
      "default": 10
    }
  },
  "editorScript": "file:./index.js",
  "editorStyle": "file:./editor.css",
  "viewScript": "file:../../assets/js/ap-directory.js",
  "style": "file:../../assets/css/ap-directory.css"
}
EOF

# 3. index.js
cat > "$BLOCK_DIR/index.js" <<'EOF'
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
EOF

# 4. editor.css
cat > "$BLOCK_DIR/editor.css" <<'EOF'
.ap-block-preview {
  padding: 1em;
  background: #f5f5f5;
  border: 1px dashed #ccc;
}
EOF

# 5. Update plugin bootstrap to register the block
BOOTSTRAP="$PLUGIN_DIR/artpulse-management.php"

echo "🔧 Injecting block registration into artpulse-management.php…"

# Insert registration inside init hook
awk '/\add_action\( *'\''init'\''/,/^\)/ {
    print
    if (/^}/ && !x) {
        print "    // Register Gutenberg Directory block";
        print "    wp_register_script(";
        print "        'ap-directory-block',";
        print "        plugins_url('build/index.js', __FILE__),";
        print "        ['wp-blocks','wp-element','wp-editor','wp-components'],";
        print "        filemtime(plugin_dir_path(__FILE__).'build/index.js')";
        print "    );";
        print "    register_block_type('artpulse/directory', [";
        print "        'editor_script' => 'ap-directory-block',";
        print "    ]);";
        x=1
    }
    next
}1' "$BOOTSTRAP" > "${BOOTSTRAP}.tmp" && mv "${BOOTSTRAP}.tmp" "$BOOTSTRAP"

echo "✅ Directory block scaffolded. Now run:"
echo "   npm install --save-dev @wordpress/scripts"
echo "   npm run build"
