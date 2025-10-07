import { defineConfig } from '@playwright/test';

const baseURL = process.env.WP_BASE_URL || 'http://localhost:8888';

export default defineConfig({
  testDir: './',
  timeout: 60_000,
  retries: process.env.CI ? 1 : 0,
  use: {
    baseURL,
    headless: true,
  },
  reporter: [['list']],
});
