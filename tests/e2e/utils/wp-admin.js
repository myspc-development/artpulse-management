const { baseURL, adminUsername, adminPassword } = require('./env');

const stripTrailingSlash = (url) => url.replace(/\/$/, '');
const adminBase = stripTrailingSlash(baseURL);

async function login(page) {
  await page.goto(`${adminBase}/wp-login.php`, { waitUntil: 'networkidle0' });
  await page.type('#user_login', adminUsername);
  await page.type('#user_pass', adminPassword);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
    page.click('#wp-submit'),
  ]);
}

async function ensureLoggedIn(page) {
  await page.goto(`${adminBase}/wp-admin/`, { waitUntil: 'networkidle0' });
  if (page.url().includes('wp-login.php')) {
    await login(page);
  }
}

async function withRestNonce(page, callback) {
  await ensureLoggedIn(page);
  await page.goto(`${adminBase}/wp-admin/`, { waitUntil: 'networkidle0' });
  await page.waitForFunction(() => window.wpApiSettings && window.wpApiSettings.nonce, {
    timeout: 15000,
  });
  const nonce = await page.evaluate(() => window.wpApiSettings.nonce);
  return callback(nonce);
}

async function requestWithNonce(page, { url, method = 'POST', data }) {
  return withRestNonce(page, (nonce) =>
    page.evaluate(
      async ({ endpoint, payload, nonceValue, httpMethod }) => {
        const response = await fetch(endpoint, {
          method: httpMethod,
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonceValue,
          },
          body: payload ? JSON.stringify(payload) : undefined,
        });

        const text = await response.text();
        let json;
        try {
          json = text ? JSON.parse(text) : {};
        } catch (error) {
          json = { raw: text };
        }

        if (!response.ok) {
          throw new Error(JSON.stringify(json));
        }

        return json;
      },
      {
        endpoint: url,
        payload: data || null,
        nonceValue: nonce,
        httpMethod: method,
      }
    )
  );
}

async function createPageWithShortcode(page, { title, shortcode }) {
  const endpoint = `${adminBase}/wp-json/wp/v2/pages`;
  return requestWithNonce(page, {
    url: endpoint,
    method: 'POST',
    data: {
      title,
      status: 'publish',
      content: shortcode,
    },
  });
}

async function deletePage(page, id) {
  const endpoint = `${adminBase}/wp-json/wp/v2/pages/${id}?force=true`;
  return requestWithNonce(page, {
    url: endpoint,
    method: 'DELETE',
  });
}

module.exports = {
  login,
  ensureLoggedIn,
  createPageWithShortcode,
  deletePage,
};
