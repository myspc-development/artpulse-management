module.exports = {
  root: true,
  extends: [ 'plugin:@wordpress/eslint-plugin/custom' ],
  parser: '@babel/eslint-parser',
  parserOptions: {
    requireConfigFile: false,
    babelOptions: {
      configFile: require.resolve( './babel.config.js' ),
    },
  },
};
