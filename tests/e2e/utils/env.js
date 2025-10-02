const path = require('path');

let config = {};
try {
  config = require(path.join(__dirname, '..', 'jest-playwright.config.js'));
} catch (error) {
  config = {};
}

const baseURL = (process.env.WP_BASE_URL || config.baseURL || 'http://localhost:8888').replace(/\/$/, '');
const adminUsername = process.env.WP_USERNAME || 'admin';
const adminPassword = process.env.WP_PASSWORD || 'password';

module.exports = {
  baseURL,
  adminUsername,
  adminPassword,
};
