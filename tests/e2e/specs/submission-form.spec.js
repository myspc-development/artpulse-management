const { login, createPageWithShortcode, deletePage } = require('../utils/wp-admin');

describe('Submission form', () => {
  let createdPage;
  const shortcode = '[ap_submission_form post_type="artpulse_event"]';

  beforeAll(async () => {
    await login(page);
    const title = `Submission Form ${Date.now()}`;
    createdPage = await createPageWithShortcode(page, { title, shortcode });
  });

  afterAll(async () => {
    if (createdPage?.id) {
      await deletePage(page, createdPage.id);
    }
  });

  it('submits the front-end form and renders confirmation', async () => {
    expect(createdPage?.link).toBeTruthy();
    await page.goto(createdPage.link, { waitUntil: 'networkidle0' });
    await page.waitForSelector('form.ap-submission-form');

    const eventTitle = `Test Event ${Date.now()}`;
    const eventDate = new Date().toISOString().split('T')[0];
    const eventLocation = 'Downtown Gallery';

    await page.click('form.ap-submission-form input[name="title"]');
    await page.type('form.ap-submission-form input[name="title"]', eventTitle);
    await page.type('form.ap-submission-form input[name="event_date"]', eventDate);
    await page.type('form.ap-submission-form input[name="event_location"]', eventLocation);

    const submissionResponse = page.waitForResponse((response) => {
      return (
        response.url().includes('/wp-json/artpulse/v1/submissions') &&
        response.request().method() === 'POST'
      );
    });

    const dialogPromise = new Promise((resolve) => {
      page.once('dialog', resolve);
    });

    await page.click('form.ap-submission-form button[type="submit"]');

    const response = await submissionResponse;
    const payload = await response.json();

    expect(response.status()).toBeLessThan(400);
    expect(payload).toMatchObject({
      title: eventTitle,
      type: 'artpulse_event',
    });

    const dialog = await dialogPromise;
    expect(dialog.message()).toContain('Submission successful');
    await dialog.accept();
  });
});
