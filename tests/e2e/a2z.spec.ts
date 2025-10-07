import { test, expect } from '@playwright/test';

const artistsPath = '/artists/letter/a/';
const galleriesPath = '/organizations/letter/%23/';

test.describe('ArtPulse A–Z directories', () => {
  test('artists directory renders and supports searching within a letter', async ({ page }) => {
    const response = await page.goto(artistsPath);
    expect(response?.ok()).toBeTruthy();

    await expect(page.locator('.ap-directory__heading')).toContainText('A');
    await expect(page.locator('.ap-directory__letter-link[aria-current="page"]')).toContainText('A');
    await expect(page.locator('.ap-directory__item a').first()).toBeVisible();

    const searchInput = page.locator('form.ap-directory__search input[name="s"]');
    if (await searchInput.count()) {
      await searchInput.fill('man');
      await Promise.all([
        page.waitForLoadState('networkidle'),
        searchInput.press('Enter'),
      ]);

      await expect(page.locator('.ap-directory__heading')).toContainText('A');
      await expect(page.locator('.ap-directory__item a').first()).toBeVisible();
    }
  });

  test('galleries hash directory renders results', async ({ page }) => {
    const response = await page.goto(galleriesPath);
    expect(response?.ok()).toBeTruthy();

    await expect(page.locator('.ap-directory__heading')).toContainText('#');
    await expect(page.locator('.ap-directory__item').first()).toBeVisible();
  });
});
