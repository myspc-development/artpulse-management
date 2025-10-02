const baseConfig = require('@wordpress/scripts/config/jest-e2e.config.js');

module.exports = {
...baseConfig,
testTimeout: 120000,
};
