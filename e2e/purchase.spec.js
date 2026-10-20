const { test, expect } = require('@playwright/test');
const { execSync } = require('node:child_process');

const mockPort = process.env.MOCK_INGEST_PORT || 9911;
const mock = `https://localhost:${mockPort}`;
const cli = process.env.TAKT_WP_CLI || 'npx wp-env run cli';
const triggerPath = 'wp-content/plugins/takt-wordpress/e2e/trigger-order.php';

async function events(request) {
  const res = await request.get(`${mock}/__events`);
  return res.json();
}

test('a completed WooCommerce order sends a Purchase event with revenue', async ({ request }) => {
  await request.post(`${mock}/__reset`);

  const out = execSync(`${cli} wp eval-file ${triggerPath}`, { encoding: 'utf8' });
  expect(out).toMatch(/ORDER_ID=\d+/);

  let purchase;
  await expect
    .poll(
      async () => {
        purchase = (await events(request)).find((e) => e.n === 'Purchase');
        return Boolean(purchase);
      },
      { timeout: 20000, intervals: [500] },
    )
    .toBe(true);

  expect(purchase.d).toBe('localhost');
  expect(purchase.$).toMatchObject({ a: '99.90', c: 'EUR' });
  expect(purchase.p.order_id).toMatch(/^\d+$/);
  expect(purchase.p.items).toBe('2');
});
