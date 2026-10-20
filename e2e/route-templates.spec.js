const { test, expect } = require('@playwright/test');

const mock = `https://localhost:${process.env.MOCK_INGEST_PORT || 9911}`;
const rawSegment = 'takt-e2e-token-42';

test('a route template sends the beacon under its template, never the raw path @route-templates', async ({ page, request }) => {
  await request.post(`${mock}/__reset`);
  await page.route('https://taktlytics.com/api/event', (route) => route.continue({ url: `${mock}/api/event` }));

  await page.goto(`/${rawSegment}`);

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

  expect(JSON.stringify(pageview)).not.toContain(rawSegment);
  expect(pageview.u).toContain('[redacted]');
});
