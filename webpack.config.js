const path = require('path');

module.exports = {
  mode: 'production',
  entry: './assets/js/user-dashboard/main.js',
  output: {
    filename: 'user-dashboard.bundle.js',
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
