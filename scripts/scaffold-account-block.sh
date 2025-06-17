#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="$(dirname "$0")"
BLOCK_DIR="$PLUGIN_DIR/src/Blocks/Account"

echo "📦 Creating Account block scaffold…"

# 1. Create block directory
mkdir -p "$BLOCK_DIR"

# 2. block.json
cat > "$BLOCK_DIR/block.json" <<'EOF'
{
  "apiVersion": 2,
  "name": "artpulse/account",
  "title": "ArtPulse My Membership",
  "category": "widgets",
  "icon": "admin-users",
  "supports": {
    "html": false
  },
  "attributes": {},
  "editorScript": "file:./index.js",
  "editorStyle": "file:./editor.css"
}
EOF

# 3. index.js
cat > "$BLOCK_DIR/index.js" <<'EOF'
import { registerBlockType } from '@wordpress/blocks';

registerBlockType('artpulse/account', {
  edit: () => {
    return (
      <div className="ap-block-account-preview">
        <p>ArtPulse Membership Account will render here on the front end.</p>
      </div>
    );
  },
  save: () => {
    return (
      <div>
        {'<!-- wp:shortcode -->[ap_membership_account]<!-- /wp:shortcode -->'}
      </div>
    );
  }
});
EOF

# 4. editor.css
cat > "$BLOCK_DIR/editor.css" <<'EOF'
.ap-block-account-preview {
  padding: 1em;
  background: #f5f5f5;
  border: 1px dashed #ccc;
  text-align: center;
}
EOF

# 5. Inject registration into artpulse-management.php
BOOTSTRAP="$PLUGIN_DIR/artpulse-management.php"
echo "🔧 Injecting Account block registration into artpulse-management.php…"

awk '/\add_action\( *'\''init'\''/,/^\)/ {
    print
    if (/^}/ && !y) {
        print "    // Register Gutenberg Membership Account block";
        print "    wp_register_script(";
        print "        'ap-account-block',";
        print "        plugins_url('build/account.js', __FILE__),";
        print "        ['wp-blocks','wp-element'],";
        print "        filemtime(plugin_dir_path(__FILE__).'build/account.js')";
        print "    );";
        print "    register_block_type('artpulse/account', [";
        print "        'editor_script' => 'ap-account-block',";
        print "    ]);";
        y=1
    }
    next
}1' "$BOOTSTRAP" > "${BOOTSTRAP}.tmp" && mv "${BOOTSTRAP}.tmp" "$BOOTSTRAP"

echo "✅ Account block scaffolded. Next:"
echo "   npm install --save-dev @wordpress/scripts"
echo "   npm run build"
