const baseURL = (process.env.WP_BASE_URL || 'http://localhost:8888').replace(/\/$/, '');

module.exports = {
  launch: {
    headless: process.env.HEADLESS !== 'false',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  },
  browserContext: 'default',
  exitOnPageError: false,
  baseURL,
};
