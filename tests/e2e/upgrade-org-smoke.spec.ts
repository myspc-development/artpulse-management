import { test, expect, type Page, request as playwrightRequest } from '@playwright/test';

import { createBase64ImageBuffer } from './utils';

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
const ADMIN_USER = process.env.E2E_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.E2E_ADMIN_PASS || 'password';
const MEMBER_USER = process.env.E2E_MEMBER_USER || 'member_e2e';
const MEMBER_PASS = process.env.E2E_MEMBER_PASS || 'member_password';
const MEMBER_EMAIL =
  process.env.E2E_MEMBER_EMAIL || (MEMBER_USER.includes('@') ? MEMBER_USER : `${MEMBER_USER}@example.com`);

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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

async function ensureMemberExists(
  adminUsername: string,
  adminPassword: string,
  memberUsername: string,
  memberEmail: string,
  memberPassword: string,
): Promise<void> {
  const context = await playwrightRequest.newContext({ baseURL: BASE_URL });
  try {
    await context.get('/wp-login.php');
    const loginResponse = await context.post('/wp-login.php', {
      form: {
        log: adminUsername,
        pwd: adminPassword,
        redirect_to: `${BASE_URL}/wp-admin/`,
        testcookie: '1',
      },
    });

    if (loginResponse.status() >= 400) {
      throw new Error(`Failed to log in as admin. Status: ${loginResponse.status()}`);
    }

    await context.get('/wp-admin/');

    const nonceResponse = await context.get('/wp-admin/admin-ajax.php?action=rest-nonce');
    if (!nonceResponse.ok()) {
      throw new Error(`Unable to fetch REST nonce. Status: ${nonceResponse.status()}`);
    }

    const nonce = (await nonceResponse.text()).trim();
    const authHeaders = {
      'X-WP-Nonce': nonce,
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Referer: `${BASE_URL}/wp-admin/`,
    };

    let existingUserId: number | null = null;

    const searchResponse = await context.get(
      `/wp-json/wp/v2/users?search=${encodeURIComponent(memberUsername)}&per_page=100`,
      {
        headers: {
          'X-WP-Nonce': nonce,
          Accept: 'application/json',
          Referer: `${BASE_URL}/wp-admin/`,
        },
      },
    );

    if (searchResponse.ok()) {
      const results = (await searchResponse.json()) as Array<{ id: number; email?: string; slug?: string }>;
      const match = results.find((user) => user.email === memberEmail || user.slug === memberUsername);
      if (match) {
        existingUserId = match.id;
      }
    }

    if (existingUserId) {
      const deleteResponse = await context.fetch(`/wp-json/wp/v2/users/${existingUserId}?force=true&reassign=0`, {
        method: 'DELETE',
        headers: authHeaders,
      });

      if (!deleteResponse.ok()) {
        throw new Error(`Failed to delete existing member user. Status: ${deleteResponse.status()}`);
      }
    }

    const createResponse = await context.post('/wp-json/wp/v2/users', {
      headers: authHeaders,
      data: {
        username: memberUsername,
        email: memberEmail,
        password: memberPassword,
        roles: ['member'],
      },
    });

    if (!createResponse.ok()) {
      const body = await createResponse.text();
      throw new Error(`Failed to create member user. Status: ${createResponse.status()} Body: ${body}`);
    }
  } finally {
    await context.dispose();
  }
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

async function findEventOnArchive(page: Page, title: string): Promise<void> {
  await page.goto(`${BASE_URL}/events/`);
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('link', { name: title })).toBeVisible();
}

test.describe('Playwright smoke: Member→Org upgrade + Builder + Event publish (Salient)', () => {
  test.slow();

  test('member requests upgrade, builds org, and publishes an event', async ({ browser }) => {
    await ensureMemberExists(ADMIN_USER, ADMIN_PASS, MEMBER_USER, MEMBER_EMAIL, MEMBER_PASS);

    const memberContext = await browser.newContext();
    const memberPage = await memberContext.newPage();
    await login(memberPage, MEMBER_USER, MEMBER_PASS);

    await memberPage.goto(`${BASE_URL}/member-dashboard/`);
    await memberPage.waitForLoadState('networkidle');
    await Promise.all([
      memberPage.waitForNavigation({ waitUntil: 'networkidle' }),
      memberPage.locator('[data-test="org-upgrade-button"]').click(),
    ]);
    await memberPage.waitForLoadState('networkidle');
    await expect(memberPage.locator('[data-test="org-upgrade-status"]')).toContainText(/pending/i);

    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    await login(adminPage, ADMIN_USER, ADMIN_PASS);
    await approveFirstPendingUpgrade(adminPage);
    await adminContext.close();

    await memberPage.goto(`${BASE_URL}/member-dashboard/`);
    await memberPage.waitForLoadState('networkidle');
    await expect(memberPage.locator('[data-test="org-upgrade-status"]')).toContainText(/available/i);

    await memberPage.goto(`${BASE_URL}/org-builder/?step=images`);
    await memberPage.waitForLoadState('networkidle');
    const buffer = createBase64ImageBuffer();
    await memberPage.setInputFiles('input[data-test="org-logo-input"]', {
      name: 'logo.jpg',
      mimeType: 'image/jpeg',
      buffer,
    });
    await Promise.all([
      memberPage.waitForNavigation({ waitUntil: 'networkidle' }),
      memberPage.click('button[data-test="org-builder-save"]'),
    ]);
    await memberPage.waitForLoadState('networkidle');
    await expect(memberPage.locator('.ap-org-builder__notice--success')).toBeVisible();

    await Promise.all([
      memberPage.waitForNavigation({ waitUntil: 'networkidle' }),
      memberPage.click('a[data-test="org-submit-event"]'),
    ]);
    await memberPage.waitForLoadState('networkidle');

    const eventTitle = `E2E Event ${Date.now()}`;
    const eventDate = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    await memberPage.fill('input[data-test="event-title"]', eventTitle);
    await memberPage.fill('#ap_org_event_description', 'Automated event submission created by Playwright smoke test.');
    await memberPage.fill('input[data-test="event-date"]', eventDate);
    await memberPage.fill('#ap_org_event_location', 'Playwright City');
    await memberPage.setInputFiles('input[data-test="event-flyer"]', {
      name: 'flyer.jpg',
      mimeType: 'image/jpeg',
      buffer,
    });

    await Promise.all([
      memberPage.waitForNavigation({ waitUntil: 'networkidle' }),
      memberPage.click('button[data-test="event-submit"]'),
    ]);
    await memberPage.waitForLoadState('networkidle');

    await expect(memberPage).toHaveURL(new RegExp(`${escapeRegExp(BASE_URL)}/artpulse_event/[^/]+/`));
    const img = memberPage.locator('.nectar-portfolio-single-media img');
    await expect(img).toBeVisible();
    const naturalWidth = await img.evaluate((element) => element.naturalWidth);
    expect(naturalWidth).toBeGreaterThan(0);
    await expect(memberPage.getByRole('heading', { name: eventTitle, level: 1 })).toBeVisible();

    await findEventOnArchive(memberPage, eventTitle);

    await memberContext.close();
  });
});
