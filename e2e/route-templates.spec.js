const { test, expect } = require('@playwright/test');

const mock = `https://localhost:${process.env.MOCK_INGEST_PORT || 9911}`;
const rawSegment = 'takt-e2e-token-42';

const cases = [
  { name: 'an unknown path', visit: `/${rawSegment}`, template: '/[redacted]', hides: rawSegment },
  { name: 'a single post', visit: '/takt-e2e-article/', template: '/%7Bpost_type%7D/%7Bslug%7D', hides: 'takt-e2e-article' },
  { name: 'the home page', visit: '/', template: '/', hides: null },
];

for (const { name, visit, template, hides } of cases) {
  test(`a route template sends ${name} under its template @route-templates`, async ({ page, request }) => {
    await request.post(`${mock}/__reset`);
    await page.route('https://taktlytics.com/api/event', (route) => route.continue({ url: `${mock}/api/event` }));

    await page.goto(visit);

    let pageview;
    await expect
      .poll(
        async () => {
          const res = await request.get(`${mock}/__events`);
          pageview = (await res.json()).find((e) => e.n === 'pageview');
          return Boolean(pageview);
        },
        { timeout: 20000, intervals: [500] },
      )
      .toBe(true);

    expect(new URL(pageview.u).pathname).toBe(template);
    if (hides) {
      expect(JSON.stringify(pageview)).not.toContain(hides);
    }
  });
}
