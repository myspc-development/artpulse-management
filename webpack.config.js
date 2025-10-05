const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        index: path.resolve(process.cwd(), 'src/index.js'),
        'sidebar-taxonomies': path.resolve(process.cwd(), 'assets/js/sidebar-taxonomies.js'),
        'advanced-taxonomy-filter-block': path.resolve(process.cwd(), 'assets/js/advanced-taxonomy-filter-block.js'),
        'filtered-list-shortcode-block': path.resolve(process.cwd(), 'assets/js/filtered-list-shortcode-block.js'),
        'ajax-filter-block': path.resolve(process.cwd(), 'assets/js/ajax-filter-block.js'),
    },
};
