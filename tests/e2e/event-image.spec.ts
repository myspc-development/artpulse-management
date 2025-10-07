import { test, expect } from '@playwright/test';
import { execFile } from 'node:child_process';
import { Buffer } from 'node:buffer';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
const FIXTURE_IMAGE_BASE64 =
  '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhUQEhIWFRUVFRUVFRUVFRUVFRUVFRUXFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy0lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIALcBEwMBIgACEQEDEQH/xAAbAAACAgMBAAAAAAAAAAAAAAAEBQMGAAECB//EADkQAAEDAgMFBgQEBQMFAQAAAAEAAhEDIQQSMUEFUWEGEyJxgZGh8BRCUrHB0fAjM2KCktLh8RZTc4KS/8QAGgEAAwEBAQEAAAAAAAAAAAAAAAECAwQFBv/EACcRAQEAAgICAwACAwEAAAAAAAABAhESITFBEyJRYXGB8AUiMv/aAAwDAQACEQMRAD8A9ziIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiICIiAiIgIiIP/Z';

async function runWpCli(args: string[], { ignoreError = false }: { ignoreError?: boolean } = {}) {
  try {
    const { stdout } = await execFileAsync('npx', ['wp-env', 'run', 'tests-cli', ...args], {
      env: process.env,
    });
    return stdout.trim();
  } catch (error: any) {
    if (ignoreError) {
      const stdout = error?.stdout ? error.stdout.toString() : '';
      return stdout.trim();
    }
    throw error;
  }
}

function escapeForRegExp(value: string) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\$&');
}

test.describe('Event image submissions', () => {
  const username = 'event-image-e2e';
  const password = 'EventImagePass!123';
  const fixtureFile = {
    name: 'fixture-image.jpg',
    mimeType: 'image/jpeg',
    buffer: Buffer.from(FIXTURE_IMAGE_BASE64, 'base64'),
  };
  let userId = 0;
  let eventId = 0;
  let submissionPageId = 0;
  let submissionPageUrl = `${BASE_URL}/event-image-submission/`;

  test.beforeAll(async () => {
    await runWpCli(['wp', 'user', 'delete', username, '--yes'], { ignoreError: true });

    const userCreateOutput = await runWpCli([
      'wp',
      'user',
      'create',
      username,
      'event-image-e2e@example.com',
      '--role=subscriber',
      `--user_pass=${password}`,
      '--porcelain',
    ]);
    userId = Number.parseInt(userCreateOutput, 10);
    expect(Number.isNaN(userId)).toBe(false);

    const existingPageId = await runWpCli([
      'wp',
      'post',
      'list',
      '--post_type=page',
      '--name=event-image-submission',
      '--field=ID',
    ], { ignoreError: true });
    if (existingPageId) {
      await runWpCli(['wp', 'post', 'delete', existingPageId, '--force'], { ignoreError: true });
    }

    const pageCreateOutput = await runWpCli([
      'wp',
      'post',
      'create',
      '--post_type=page',
      '--post_title=Event Image Submission',
      '--post_name=event-image-submission',
      '--post_status=publish',
      "--post_content=[ap_org_submit_event]",
      '--porcelain',
    ]);
    submissionPageId = Number.parseInt(pageCreateOutput, 10);
    expect(Number.isNaN(submissionPageId)).toBe(false);

    submissionPageUrl = `${BASE_URL}/event-image-submission/`;
  });

  test.afterAll(async () => {
    if (eventId) {
      await runWpCli(['wp', 'post', 'delete', String(eventId), '--force'], { ignoreError: true });
    }
    if (submissionPageId) {
      await runWpCli(['wp', 'post', 'delete', String(submissionPageId), '--force'], { ignoreError: true });
    }
    if (userId) {
      await runWpCli(['wp', 'user', 'delete', username, '--yes'], { ignoreError: true });
    }
  });

  test('uploads an event flyer and renders it on single and archive views', async ({ page }) => {
    await page.goto(`${BASE_URL}/wp-login.php`);
    await page.fill('#user_login', username);
    await page.fill('#user_pass', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.click('#wp-submit'),
    ]);

    await page.goto(submissionPageUrl);
    await page.waitForSelector('form.ap-event-form');

    await page.fill('#ap_org_event_title', 'Event Image Verification');
    await page.fill('#ap_org_event_description', 'This event verifies featured image uploads.');
    await page.fill('#ap_org_event_date', '2025-12-05');
    await page.fill('#ap_org_event_location', 'Playwright City');
    await page.setInputFiles('#ap_org_event_flyer', fixtureFile);

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.click('.ap-event-form button[type="submit"]'),
    ]);

    await expect(page.locator('.ap-success-message')).toBeVisible();

    const latestEventIdOutput = await runWpCli([
      'wp',
      'post',
      'list',
      '--post_type=artpulse_event',
      `--author=${userId}`,
      '--orderby=date',
      '--order=desc',
      '--posts_per_page=1',
      '--field=ID',
    ]);
    const latestEventId = latestEventIdOutput.split(/\s+/).filter(Boolean)[0];
    expect(latestEventId).toBeTruthy();

    eventId = Number.parseInt(latestEventId, 10);
    expect(Number.isNaN(eventId)).toBe(false);

    await runWpCli(['wp', 'post', 'update', String(eventId), '--post_status=publish']);

    const attachmentIdOutput = await runWpCli(['wp', 'post', 'meta', 'get', String(eventId), '_thumbnail_id']);
    const attachmentId = Number.parseInt(attachmentIdOutput, 10);
    expect(Number.isNaN(attachmentId)).toBe(false);

    const attachedFile = await runWpCli(['wp', 'post', 'meta', 'get', String(attachmentId), '_wp_attached_file']);
    const fileName = attachedFile.trim().split('/').pop() ?? attachedFile.trim();
    expect(fileName).toBeTruthy();
    const fileNamePattern = new RegExp(escapeForRegExp(fileName));

    const eventUrl = await runWpCli(['wp', 'post', 'url', String(eventId)]);

    const singleResponse = await page.goto(eventUrl);
    expect(singleResponse?.ok()).toBeTruthy();

    const singleImage = page.locator('.nectar-portfolio-single-media img');
    await expect(singleImage).toBeVisible();
    await expect(singleImage).toHaveAttribute('src', fileNamePattern);

    const archiveResponse = await page.goto(`${BASE_URL}/events/`);
    expect(archiveResponse?.ok()).toBeTruthy();

    const archiveImage = page.locator(`img[src*="${fileName}"]`).first();
    await expect(archiveImage).toBeVisible();
  });
});
