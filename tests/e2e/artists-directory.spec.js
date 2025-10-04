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

describe('Artists directory shortcode', () => {
  const createdArtistIds = [];
  let pageId = 0;

  beforeAll(async () => {
    const artistData = [
      { title: 'Amelia Abstract' },
      { title: 'Benedict Brush' },
      { title: 'Camila Canvas' },
    ];

    for (const artist of artistData) {
      const artistIdOutput = await runWpCli([
        'wp',
        'post',
        'create',
        '--post_type=artpulse_artist',
        `--post_title=${artist.title}`,
        '--post_status=publish',
        '--porcelain',
      ]);
      const artistId = parseInt(artistIdOutput, 10);
      expect(Number.isNaN(artistId)).toBe(false);
      createdArtistIds.push(artistId);
    }

    const pageOutput = await runWpCli([
      'wp',
      'post',
      'create',
      '--post_type=page',
      '--post_title=Artists Directory Test Page',
      '--post_status=publish',
      '--post_content=[ap_artists_directory]',
      '--porcelain',
    ]);
    pageId = parseInt(pageOutput, 10);
    expect(Number.isNaN(pageId)).toBe(false);
  });

  afterAll(async () => {
    if (pageId) {
      await runWpCli(['wp', 'post', 'delete', String(pageId), '--force'], { ignoreError: true });
    }

    for (const artistId of createdArtistIds) {
      await runWpCli(['wp', 'post', 'delete', String(artistId), '--force'], { ignoreError: true });
    }
  });

  it('filters artists by letter selection', async () => {
    await page.goto(`${BASE_URL}/?p=${pageId}`);
    await page.waitForSelector('.ap-directory-filter');
    await page.waitForSelector('.ap-directory-section[data-letter="All"] .ap-artist-card');

    const filterButton = await page.waitForSelector('.ap-directory-filter__control[data-letter="B"]');
    await filterButton.click();

    await page.waitForFunction(() => {
      const activeControl = document.querySelector('.ap-directory-filter__control.is-active');
      const bSection = document.querySelector('.ap-directory-section[data-letter="B"]');
      const aSection = document.querySelector('.ap-directory-section[data-letter="A"]');
      return (
        activeControl &&
        activeControl.getAttribute('data-letter') === 'B' &&
        bSection &&
        !bSection.hasAttribute('hidden') &&
        (!aSection || aSection.hasAttribute('hidden'))
      );
    });

    const visibleTitles = await page.$$eval(
      '.ap-directory-section:not([hidden]) .ap-artist-card__title',
      (nodes) => nodes.map((node) => node.textContent.trim())
    );

    expect(visibleTitles).toEqual(['Benedict Brush']);

    const hiddenTitles = await page.$$eval(
      '.ap-directory-section[hidden] .ap-artist-card__title',
      (nodes) => nodes.map((node) => node.textContent.trim())
    );

    expect(hiddenTitles).toEqual(expect.arrayContaining(['Amelia Abstract', 'Camila Canvas']));

    const emptyStateHidden = await page.$eval('.ap-directory-empty', (el) => el.hasAttribute('hidden'));
    expect(emptyStateHidden).toBe(true);
  });
});
