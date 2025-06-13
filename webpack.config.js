const path = require('path');
const fs = require('fs');

const jsDir = path.resolve(__dirname, 'assets/js');
const entryFiles = fs.readdirSync(jsDir)
  .filter(file => file.endsWith('.js'))
  .map(file => path.join(jsDir, file));

module.exports = {
  mode: 'production',
  entry: entryFiles,
  output: {
    filename: 'bundle.js',
    path: path.resolve(__dirname, 'build'),
  },
};
