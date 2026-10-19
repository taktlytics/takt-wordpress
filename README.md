# Takt Analytics for WordPress

> 📚 **Documentation** — [taktlytics.com/docs/wrappers/wordpress](https://taktlytics.com/docs/wrappers/wordpress)

The official WordPress plugin for [Takt](https://taktlytics.com), privacy-first web analytics. It injects the Takt browser snippet into your site and — when WooCommerce is active — reports completed orders as server-to-server purchase events with revenue.

Takt itself is a managed service hosted in Europe; the plugin connects your site to it. Only the measurement script can be served from your own domain (the `asset` mode below).

- **Snippet injection** into `wp_head` (inline, CDN, asset served from your own origin, or ES-module SDK).
- **Autocapture** for outbound links, file downloads, tagged events (`data-takt-event`) and 404s.
- **WooCommerce purchases** sent server-to-server, so revenue is tracked even when an order completes off-session (IPN, admin, cron).
- **No bundled HTTP stack** — server-to-server requests go through WordPress' own `wp_remote_post`.
- **Dependency-isolated** — all Composer dependencies are namespaced under `Takt\WP\Vendor\`, so the plugin never clashes with another plugin's PSR-7/HTTP libraries.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- WooCommerce (optional, for purchase tracking)
- A Takt account: a site domain, and — for purchase tracking — an API key

## Installation

1. Download `takt-analytics.zip` from the [latest release](https://github.com/taktlytics/takt-wordpress/releases).
2. In **WP Admin → Plugins → Add New → Upload Plugin**, upload the ZIP and activate it.
3. Open **Settings → Takt Analytics** and set your domain. That alone is enough for the browser snippet: it posts to `https://taktlytics.com/api/event` by default. To also report WooCommerce purchases, fill in the ingest origin (`https://taktlytics.com`) and your API key.

Or via WP-CLI:

```bash
wp plugin install takt-analytics.zip --activate
```

The release ZIP is self-contained: it bundles `takt-core-php` and its PSR-7 dependencies (scoped), so no `composer install` is needed on the server.

## Configuration

**Settings → Takt Analytics** — 19 settings, all stored in the single `takt_settings` option. Defaults below are what a fresh, never-saved install uses.

| Setting | Default | Description |
| --- | --- | --- |
| Domain | *(empty)* | The site identifier sent with every event (e.g. `example.com`). While empty, no snippet is injected at all. |
| Mode | `inline` | `inline` (tracker embedded in the page, anti-adblock), `cdn` (jsDelivr), `asset` (served from your own origin) or `sdk` (ES module — required by *Excluded paths* and URL scrubbing). |
| Script origin | *(empty)* | Base URL the tracker is served from in `cdn`/`asset` mode. |
| Exclude localhost | off | Skip tracking on local hostnames (`localhost`, `*.local`, private ranges). **Off by default**: turn it on if your production domain is also used locally. |
| Outbound clicks | off | Autocapture: sends `Outbound Link: Click` for links to another hostname. |
| Downloads | off | Autocapture: sends `File Download` for links ending in a tracked extension. |
| Tagged events | off | Autocapture: sends a custom event on click for any element carrying `data-takt-event="Name"`. Extra properties come from `data-takt-prop-*` attributes on the same element. |
| 404 pages | off | Autocapture: sends `404` when the page is marked `[data-takt-404]` / `<meta name="takt:404">` or the navigation responded 404. |
| File extensions | *(tracker's own list)* | Overrides which extensions count as downloads (e.g. `pdf, zip, docx`). |
| Sampling rate | *(empty)* | Keep a fraction of hits, e.g. `0.5` for ~50%. Empty tracks everything. |
| Keep query string | off | Keep the raw query string + hash in tracked URLs. Off strips them. |
| Kept query params | *(empty)* | Allowlist of params kept when *Keep query string* is off (e.g. `utm_source, utm_medium`). |
| Excluded paths | *(empty)* | Path prefixes never tracked (e.g. `/app, /account`). SDK mode only — silently dropped in the other modes. |
| Ignore Do-Not-Track | off | Stop honoring the browser Do-Not-Track header (honored by default). |
| Pause tracking | off | Kill-switch: disable tracking entirely without removing the plugin. |
| WooCommerce | off | Send purchase events with revenue. Also needs the ingest origin and the API key. |
| Trigger status | `completed` | Order status that fires the purchase (`completed` or `processing`). |
| Ingest origin | *(empty)* | Origin of the Takt service for server-to-server events: `https://taktlytics.com`, or your first-party proxy origin. See below. |
| API key | *(empty)* | Bearer token for server-to-server events. Write-only field: it is never echoed back, and submitting it blank keeps the stored value. |

### Ingest origin vs. collect URL

Two different things share the word "endpoint" across Takt, so the plugin is explicit about which one it wants:

- The **browser snippet** posts to a full **collect URL**. It is not configurable here and defaults to `https://taktlytics.com/api/event`. Setting a *Script origin* makes the tracker derive `{script origin}/api/event` instead, which is how a first-party proxy is used.
- The **Ingest origin** setting is server-to-server only, and is an **origin**: `/api/event` is appended to it on every send.

Pasting the full collect URL into the *Ingest origin* field is the obvious mistake, so it is accepted: a trailing `/api/event` is folded back to its origin when the settings are saved and read. Both forms therefore post to exactly one `/api/event`, and origins already stored keep working unchanged.

### API key via `wp-config.php`

For environments where secrets must not live in the database, define the key as a constant. It takes precedence over the stored value and the admin field becomes read-only:

```php
define('TAKT_API_KEY', 'your-key');
```

### URL scrubbing via `wp-config.php`

`scrubUrl` is a raw JavaScript function injected verbatim into the page, so it is **dev-controlled only** — it lives in a constant, never in the database or the admin UI, and only takes effect in `sdk` mode:

```php
define('TAKT_SCRUB_URL', '(u) => u.split("#")[0]');
```

### Plain `http` endpoints

The ingest origin and the script origin must use `https`. Plain `http` is accepted for `localhost`, `127.0.0.1` and `[::1]` only. For a development setup that reaches Takt over another host, list the extra hosts in a constant (comma-separated, empty by default):

```php
define('TAKT_PLAINTEXT_HOSTS', 'takt.test, host.docker.internal');
```

### Privacy

WooCommerce purchase events are sent from your server to the configured Takt ingest origin and include the customer's IP address and user agent for attribution. They are forwarded, never logged by the plugin. The browser tracker honours `Do Not Track` (unless *Ignore Do-Not-Track* is on) and a `takt_ignore` localStorage opt-out.

The IP address sent with a purchase is the one WooCommerce records on the order at checkout (`get_customer_ip_address()`). WooCommerce reads it from the `X-Real-IP` header, then from the first address of `X-Forwarded-For`, and only falls back to `REMOTE_ADDR` when neither is present. It does not check that the request came through a proxy you control, so those headers are client-supplied unless your edge overwrites them. The address reflects the real visitor only when the reverse proxy or CDN in front of WordPress sets `X-Real-IP` or `X-Forwarded-For` from the connecting address, or when WordPress is reached directly. A forged value can only distort the attribution of a purchase (geography, stats); it never changes the revenue or the number of events.

## Development

```bash
composer install
composer check        # php-cs-fixer + phpstan + phpunit
composer test         # unit tests only
```

### Building the release ZIP

```bash
bin/build.sh          # → dist/takt-analytics.zip
```

This installs production dependencies, scopes them under `Takt\WP\Vendor\` with [PHP-Scoper](https://github.com/humbug/php-scoper), regenerates the autoloader, smoke-tests it and packages the ZIP.

### End-to-end tests

The E2E suite spins up a real WordPress + WooCommerce via [`wp-env`](https://www.npmjs.com/package/@wordpress/env), runs [Playwright](https://playwright.dev) against it and asserts the snippet, the pageview beacon and a WooCommerce purchase event (captured by a mock ingest server):

```bash
npm install
npx playwright install chromium
npm run env:start
npx wp-env run cli wp eval-file wp-content/plugins/takt-wordpress/e2e/setup-wp.php
npm run e2e
```

## License

MIT
