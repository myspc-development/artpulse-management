const { setDefaultOptions } = require('expect-puppeteer');

setDefaultOptions({ timeout: 60000 });

beforeAll(async () => {
  await page.setDefaultTimeout(60000);
  await page.setDefaultNavigationTimeout(60000);
});

afterEach(async () => {
  page.removeAllListeners('dialog');
});
