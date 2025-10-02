const baseConfig = require('@wordpress/scripts/config/jest-e2e.config.js');

module.exports = {
  ...baseConfig,
  setupFilesAfterEnv: [
    ...(baseConfig.setupFilesAfterEnv || []),
    require.resolve('./tests/e2e/config/setup-tests.js'),
  ],
  testMatch: ['**/tests/e2e/**/*.spec.js'],
  testTimeout: 60000,
};
