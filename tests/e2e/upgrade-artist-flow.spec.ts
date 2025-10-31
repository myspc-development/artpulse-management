import { test, expect, type Page, request as playwrightRequest, type APIRequestContext } from '@playwright/test';

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
const ADMIN_USER = process.env.E2E_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.E2E_ADMIN_PASS || 'password';
const MEMBER_USER = process.env.E2E_MEMBER_USER || 'member_e2e';
const MEMBER_PASS = process.env.E2E_MEMBER_PASS || 'member_password';
const MEMBER_EMAIL =
  process.env.E2E_MEMBER_EMAIL || (MEMBER_USER.includes('@') ? MEMBER_USER : `${MEMBER_USER}@example.com`);

type AdminRestClient = {
  context: APIRequestContext;
  nonce: string;
};

function adminHeaders(client: AdminRestClient): Record<string, string> {
  return {
    'X-WP-Nonce': client.nonce,
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Referer: `${BASE_URL}/wp-admin/`,
  };
}

async function withAdminRestClient<T>(callback: (client: AdminRestClient) => Promise<T>): Promise<T> {
  const context = await playwrightRequest.newContext({ baseURL: BASE_URL });
  try {
    await context.get('/wp-login.php');
    const loginResponse = await context.post('/wp-login.php', {
      form: {
        log: ADMIN_USER,
        pwd: ADMIN_PASS,
        redirect_to: `${BASE_URL}/wp-admin/`,
        testcookie: '1',
      },
    });

    if (!loginResponse.ok()) {
      throw new Error(`Failed to log in as admin. Status: ${loginResponse.status()}`);
    }

    await context.get('/wp-admin/');

    const nonceResponse = await context.get('/wp-admin/admin-ajax.php?action=rest-nonce');
    if (!nonceResponse.ok()) {
      throw new Error(`Unable to fetch REST nonce. Status: ${nonceResponse.status()}`);
    }

    const nonce = (await nonceResponse.text()).trim();
    const client: AdminRestClient = { context, nonce };

    return await callback(client);
  } finally {
    await context.dispose();
  }
}

async function ensureMemberExists(username: string, email: string, password: string): Promise<number> {
  return withAdminRestClient(async (client) => {
    let existingUserId: number | null = null;

    const searchResponse = await client.context.get(
      `/wp-json/wp/v2/users?search=${encodeURIComponent(username)}&per_page=100`,
      {
        headers: {
          'X-WP-Nonce': client.nonce,
          Accept: 'application/json',
          Referer: `${BASE_URL}/wp-admin/`,
        },
      },
    );

    if (searchResponse.ok()) {
      const results = (await searchResponse.json()) as Array<{ id: number; email?: string; slug?: string }>;
      const match = results.find((user) => user.email === email || user.slug === username);
      if (match) {
        existingUserId = match.id;
      }
    }

    if (existingUserId) {
      const deleteResponse = await client.context.fetch(`/wp-json/wp/v2/users/${existingUserId}?force=true&reassign=0`, {
        method: 'DELETE',
        headers: adminHeaders(client),
      });

      if (!deleteResponse.ok()) {
        throw new Error(`Failed to delete existing member user. Status: ${deleteResponse.status()}`);
      }
    }

    const createResponse = await client.context.post('/wp-json/wp/v2/users', {
      headers: adminHeaders(client),
      data: {
        username,
        email,
        password,
        roles: ['member'],
      },
    });

    if (!createResponse.ok()) {
      const body = await createResponse.text();
      throw new Error(`Failed to create member user. Status: ${createResponse.status()} Body: ${body}`);
    }

    const created = (await createResponse.json()) as { id: number };
    return created.id;
  });
}

async function fetchArtistDraftIds(userId: number): Promise<number[]> {
  return withAdminRestClient(async (client) => {
    const response = await client.context.get(
      `/wp-json/wp/v2/artpulse_artist?status=draft&per_page=100&author=${userId}`,
      { headers: adminHeaders(client) },
    );

    if (!response.ok()) {
      const body = await response.text();
      throw new Error(`Failed to fetch artist drafts. Status: ${response.status()} Body: ${body}`);
    }

    const posts = (await response.json()) as Array<{ id: number }>;
    return posts.map((post) => post.id).sort((a, b) => a - b);
  });
}

async function login(page: Page, username: string, password: string): Promise<void> {
  await page.goto(`${BASE_URL}/wp-login.php`);
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="log"]', username);
  await page.fill('input[name="pwd"]', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.click('input[name="wp-submit"]'),
  ]);
  await page.waitForLoadState('networkidle');
}

async function approveFirstPendingUpgrade(page: Page): Promise<void> {
  await expect(async () => {
    await page.goto(`${BASE_URL}/wp-admin/admin.php?page=ap-upgrade-reviews`);
    await page.waitForLoadState('networkidle');
    const approveButton = page.locator('[data-test="approve-upgrade"]').first();
    await expect(approveButton).toBeVisible();
    await approveButton.click();
    await expect(page.getByText(/approved/i)).toBeVisible();
  }).toPass({ intervals: [500, 1000], timeout: 15_000 });
}

function sortNumbers(values: number[]): number[] {
  return [...values].sort((a, b) => a - b);
}

test.describe('Playwright: Artist upgrade happy path', () => {
  test.slow();

  test('member requests artist upgrade and receives a single draft profile', async ({ browser }) => {
    const memberUserId = await ensureMemberExists(MEMBER_USER, MEMBER_EMAIL, MEMBER_PASS);

    const memberContext = await browser.newContext();
    const memberPage = await memberContext.newPage();
    await login(memberPage, MEMBER_USER, MEMBER_PASS);

    await memberPage.goto(`${BASE_URL}/member-dashboard/`);
    await memberPage.waitForLoadState('networkidle');

    const artistForm = memberPage
      .locator('form.ap-dashboard-journey__form')
      .filter({ has: memberPage.locator('input[name="upgrade_type"][value="artist"]') });
    await expect(artistForm).toBeVisible();

    await Promise.all([
      memberPage.waitForNavigation({ waitUntil: 'networkidle' }),
      artistForm.locator('button[type="submit"]').click(),
    ]);
    await memberPage.waitForLoadState('networkidle');

    await expect(memberPage.getByText(/We are reviewing your artist request/i)).toBeVisible();

    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    await login(adminPage, ADMIN_USER, ADMIN_PASS);
    await approveFirstPendingUpgrade(adminPage);
    await adminContext.close();

    await memberPage.goto(`${BASE_URL}/member-dashboard/`);
    await memberPage.waitForLoadState('networkidle');
    await expect(memberPage.getByText(/Artist tools are ready/i)).toBeVisible();

    const createProfileLink = memberPage
      .locator('article[data-journey="artist"] a.ap-dashboard-button')
      .filter({ hasText: /start your profile/i })
      .first();
    await expect(createProfileLink).toBeVisible();

    const builderUrl = await createProfileLink.getAttribute('href');
    expect(builderUrl).toBeTruthy();
    expect(builderUrl).toContain('autocreate=1');

    await memberPage.goto(builderUrl!);
    await memberPage.waitForLoadState('networkidle');
    await expect(memberPage.locator('.ap-profile-builder')).toBeVisible();

    const firstDraftIds = await fetchArtistDraftIds(memberUserId);
    expect(firstDraftIds.length).toBeGreaterThan(0);

    await memberPage.goto(builderUrl!);
    await memberPage.waitForLoadState('networkidle');
    await expect(memberPage.locator('.ap-profile-builder')).toBeVisible();

    const secondDraftIds = await fetchArtistDraftIds(memberUserId);
    expect(sortNumbers(secondDraftIds)).toEqual(sortNumbers(firstDraftIds));

    await memberContext.close();
  });
});
