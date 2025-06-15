const path = require('path');

module.exports = {
  mode: 'production',
  entry: {
    'user-dashboard': './assets/js/user-dashboard/main.js',
    'membership-manager': './assets/js/membership-manager.js',
  },
  output: {
    filename: '[name].bundle.js',
    path: path.resolve(__dirname, 'build'),
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env'],
          },
        },
      },
    ],
  },
};
