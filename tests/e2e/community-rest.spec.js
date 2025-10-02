const { execFile } = require('node:child_process');
const { promisify } = require('node:util');
const crypto = require('node:crypto');

const execFileAsync = promisify(execFile);
const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8889';

async function runWpCli(args, { ignoreError = false } = {}) {
  try {
    const { stdout } = await execFileAsync('npx', ['wp-env', 'run', 'tests-cli', ...args], {
      env: process.env,
    });
    return stdout.trim();
  } catch (error) {
    if (ignoreError) {
      const stdout = error.stdout ? error.stdout.toString() : '';
      return stdout.trim();
    }
    throw error;
  }
}

describe('ArtPulse community REST API', () => {
  const username = 'community-e2e-user';
  const password = 'CommunityE2E@123';
  let nonce = '';
  let postId = 0;
  let userId = 0;

  beforeAll(async () => {
    await runWpCli(['wp', 'user', 'delete', username, '--yes'], { ignoreError: true });

    const userCreateOutput = await runWpCli([
      'wp',
      'user',
      'create',
      username,
      'community-e2e@example.com',
      '--role=subscriber',
      `--user_pass=${password}`,
      '--porcelain',
    ]);
    userId = parseInt(userCreateOutput, 10);
    expect(Number.isNaN(userId)).toBe(false);

    const postCreateOutput = await runWpCli([
      'wp',
      'post',
      'create',
      '--post_type=artpulse_artist',
      '--post_title=Community E2E Artist',
      '--post_status=publish',
      '--porcelain',
    ]);
    postId = parseInt(postCreateOutput, 10);
    expect(Number.isNaN(postId)).toBe(false);

    await runWpCli([
      'wp',
      'option',
      'patch',
      'update',
      'artpulse_settings',
      'stripe_webhook_secret',
      'test_secret',
    ], { ignoreError: true });
    await runWpCli([
      'wp',
      'option',
      'patch',
      'update',
      'artpulse_settings',
      'stripe_secret',
      'sk_test_dummy',
    ], { ignoreError: true });

    await page.goto(`${BASE_URL}/wp-login.php`);
    await page.fill('#user_login', username);
    await page.fill('#user_pass', password);
    await Promise.all([
      page.click('#wp-submit'),
      page.waitForNavigation({ waitUntil: 'networkidle' }),
    ]);

    await page.goto(`${BASE_URL}/wp-admin/`);
    await page.waitForSelector('#wpadminbar');
    nonce = await page.evaluate(() => window.wpApiSettings && window.wpApiSettings.nonce);
    expect(nonce).toBeTruthy();
  });

  afterAll(async () => {
    if (postId) {
      await runWpCli(['wp', 'post', 'delete', String(postId), '--force'], { ignoreError: true });
    }
    await runWpCli(['wp', 'user', 'delete', username, '--yes'], { ignoreError: true });
  });

  it('returns notifications for authenticated users', async () => {
    const result = await page.evaluate(async ({ baseUrl, nonceValue }) => {
      const response = await fetch(`${baseUrl}/wp-json/artpulse/v1/notifications`, {
        headers: {
          'X-WP-Nonce': nonceValue,
        },
      });
      const data = await response.json();
      return {
        status: response.status,
        data,
      };
    }, { baseUrl: BASE_URL, nonceValue: nonce });

    expect(result.status).toBeGreaterThanOrEqual(200);
    expect(result.status).toBeLessThan(300);
    expect(result.data).toHaveProperty('notifications');
    expect(Array.isArray(result.data.notifications)).toBe(true);
  });

  it('allows following and unfollowing content', async () => {
    const followResult = await page.evaluate(async ({ baseUrl, nonceValue, id }) => {
      const response = await fetch(`${baseUrl}/wp-json/artpulse/v1/follows`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonceValue,
        },
        body: JSON.stringify({
          post_id: id,
          post_type: 'artpulse_artist',
        }),
      });
      const data = await response.json();
      return {
        status: response.status,
        data,
      };
    }, { baseUrl: BASE_URL, nonceValue: nonce, id: postId });

    expect(followResult.status).toBeGreaterThanOrEqual(200);
    expect(followResult.status).toBeLessThan(300);
    expect(followResult.data.status).toBe('following');
    expect(followResult.data.follows).toEqual(expect.arrayContaining([postId]));

    const unfollowResult = await page.evaluate(async ({ baseUrl, nonceValue, id }) => {
      const response = await fetch(`${baseUrl}/wp-json/artpulse/v1/follows`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonceValue,
        },
        body: JSON.stringify({
          post_id: id,
          post_type: 'artpulse_artist',
        }),
      });
      const data = await response.json();
      return {
        status: response.status,
        data,
      };
    }, { baseUrl: BASE_URL, nonceValue: nonce, id: postId });

    expect(unfollowResult.status).toBeGreaterThanOrEqual(200);
    expect(unfollowResult.status).toBeLessThan(300);
    expect(unfollowResult.data.status).toBe('unfollowed');
    expect(Array.isArray(unfollowResult.data.follows)).toBe(true);
  });

  it('accepts signed Stripe webhook payloads', async () => {
    const payload = JSON.stringify({
      id: `evt_${Date.now()}`,
      type: 'checkout.session.completed',
      data: {
        object: {
          client_reference_id: userId,
          customer: 'cus_test_123',
        },
      },
    });
    const timestamp = Math.floor(Date.now() / 1000);
    const signaturePayload = `${timestamp}.${payload}`;
    const signature = crypto
      .createHmac('sha256', 'test_secret')
      .update(signaturePayload)
      .digest('hex');
    const signatureHeader = `t=${timestamp},v1=${signature}`;

    const result = await page.evaluate(async ({ baseUrl, body, header }) => {
      const response = await fetch(`${baseUrl}/wp-json/artpulse/v1/stripe-webhook`, {
        method: 'POST',
        headers: {
          'Stripe-Signature': header,
          'Content-Type': 'application/json',
        },
        body,
      });
      const data = await response.json();
      return {
        status: response.status,
        data,
      };
    }, { baseUrl: BASE_URL, body: payload, header: signatureHeader });

    expect(result.status).toBe(200);
    expect(result.data).toHaveProperty('received', true);
  });
});
