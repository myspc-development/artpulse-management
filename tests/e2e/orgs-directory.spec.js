const { execFile } = require('child_process');
const { promisify } = require('util');

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

describe('Organizations directory shortcode', () => {
  const createdOrgIds = [];
  let pageId = 0;
  let favoritesEnabled = false;

  beforeAll(async () => {
    const orgs = [
      { title: 'Art Guild' },
      { title: 'The Blue Whale' },
      { title: 'Civic Collective' },
    ];

    for (const org of orgs) {
      const idOutput = await runWpCli([
        'wp',
        'post',
        'create',
        '--post_type=artpulse_org',
        `--post_title=${org.title}`,
        '--post_status=publish',
        '--porcelain',
      ]);
      const orgId = parseInt(idOutput, 10);
      expect(Number.isNaN(orgId)).toBe(false);
      createdOrgIds.push(orgId);
    }

    const pageOutput = await runWpCli([
      'wp',
      'post',
      'create',
      '--post_type=page',
      '--post_title=Organizations Directory Test Page',
      '--post_status=publish',
      '--post_content=[ap_orgs_directory]',
      '--porcelain',
    ]);
    pageId = parseInt(pageOutput, 10);
    expect(Number.isNaN(pageId)).toBe(false);

    const favoritesCheck = await runWpCli([
      'wp',
      'eval',
      "echo class_exists('\\\\ArtPulse\\\\Community\\\\FavoritesManager') ? '1' : '0';",
    ]);
    favoritesEnabled = favoritesCheck.trim() === '1';

    if (favoritesEnabled) {
      await runWpCli([
        'wp',
        'eval',
        `ArtPulse\\\\Community\\\\FavoritesManager::add_favorite(1, ${createdOrgIds[0]}, 'artpulse_org'); echo 'done';`,
      ]);
    }
  });

  afterAll(async () => {
    if (pageId) {
      await runWpCli(['wp', 'post', 'delete', String(pageId), '--force'], { ignoreError: true });
    }

    for (const orgId of createdOrgIds) {
      await runWpCli(['wp', 'post', 'delete', String(orgId), '--force'], { ignoreError: true });
    }
  });

  it('filters organizations by letter and search', async () => {
    await page.goto(`${BASE_URL}/wp-login.php`);
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await Promise.all([
      page.waitForNavigation(),
      page.click('#wp-submit'),
    ]);

    await page.goto(`${BASE_URL}/?p=${pageId}`);
    await page.waitForSelector('.ap-orgs-dir');
    await page.waitForSelector('.ap-grid .ap-card');

    const letterB = await page.waitForSelector('.ap-az__link[data-letter="B"]');
    await letterB.click();

    await page.waitForFunction(() => {
      const titles = Array.from(document.querySelectorAll('.ap-grid .ap-card__title')).map((el) => el.textContent.trim());
      return titles.length === 1 && titles[0] === 'The Blue Whale';
    });

    const visibleAfterLetter = await page.$$eval('.ap-grid .ap-card__title', (nodes) => nodes.map((node) => node.textContent.trim()));
    expect(visibleAfterLetter).toEqual(['The Blue Whale']);

    const letterAll = await page.waitForSelector('.ap-az__link[data-letter="All"]');
    await letterAll.click();

    await page.waitForFunction(() => {
      const titles = Array.from(document.querySelectorAll('.ap-grid .ap-card__title')).map((el) => el.textContent.trim());
      return titles.length >= 3;
    });

    if (favoritesEnabled) {
      const starExists = await page.$eval('.ap-grid .ap-card__favorite', () => true).catch(() => false);
      expect(starExists).toBe(true);
    }

    await page.fill('#ap-orgs-dir-search', 'Civic');
    await page.waitForFunction(() => {
      const titles = Array.from(document.querySelectorAll('.ap-grid .ap-card__title')).map((el) => el.textContent.trim());
      return titles.length === 1 && titles[0] === 'Civic Collective';
    });
  });
});
