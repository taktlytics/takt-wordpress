=== Takt Analytics ===
Contributors: vskstudio
Tags: analytics, privacy, woocommerce, statistics, tracking
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.5.2
License: MIT
License URI: https://opensource.org/licenses/MIT

Privacy-first analytics for WordPress: injects the Takt snippet and reports WooCommerce purchases as server-to-server events.

== Description ==

Takt Analytics connects your WordPress site to Takt, privacy-first web analytics. Takt is a managed service hosted in Europe; only the measurement script can be served from your own domain.

* Injects the Takt browser snippet into every page (inline, CDN, asset served from your own origin, or ES-module SDK).
* Autocapture for outbound links, file downloads, tagged events (elements carrying data-takt-event) and 404s.
* Advanced controls: sampling rate, query-string handling with a param allowlist, Do-Not-Track override and a tracking kill-switch.
* Sends WooCommerce orders as server-to-server purchase events with revenue, so sales are tracked even when an order completes off-session (payment callback, admin, cron).
* Uses WordPress' own HTTP API for server-to-server requests, no bundled HTTP libraries.
* All dependencies are namespace-isolated, so the plugin never conflicts with other plugins.

A Takt account is required. The browser snippet only needs your site domain; purchase tracking also needs the ingest origin and an API key.

== Installation ==

1. Upload the plugin ZIP via Plugins → Add New → Upload Plugin, or extract it into wp-content/plugins/.
2. Activate the plugin.
3. Go to Settings → Takt Analytics and enter your domain. For WooCommerce purchase tracking, also enter the ingest origin (https://taktlytics.com) and your API key.

For server-to-server purchase tracking, keep WooCommerce active and choose the order status that should fire the event.

You can also define the API key in wp-config.php so it never lives in the database:

`define('TAKT_API_KEY', 'your-key');`

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. WooCommerce is only required for purchase (revenue) tracking. The browser snippet works on any site.

= Is customer data shared? =

Purchase events are sent from your server to the configured Takt ingest origin and include the customer's IP and user agent for attribution. They are never logged by the plugin. The browser tracker honours Do Not Track unless you turn that off.

= What goes in the "Ingest origin" field? =

The origin of the Takt service (https://taktlytics.com), without a path: the plugin appends /api/event itself. If you paste the full collect URL (https://taktlytics.com/api/event), it is folded back to its origin when saved, so both forms work.

= Where is my API key stored? =

In the WordPress options table, or in a wp-config.php constant (TAKT_API_KEY) which takes precedence. The settings field is write-only and never echoes the stored key.

== Changelog ==

= 0.5.2 =
* Fix: from 0.4.0 to 0.5.1, the tracker stopped sending events after the first pageview. The bundled tracker overwrote window.takt when it loaded, so later events were rejected by the ingest with a 400, and takt('Event') threw an error. Updating to 0.5.2 is enough: no setting to change. Sites that rolled back to 0.3.3 can update straight to this release.
* The bundled takt-core-php moves to 0.7.3, which loads takt-core 0.12.3 and carries the tracker fix.

= 0.5.1 =
* The bundled takt-core-php moves to 0.7.2, which loads takt-core 0.12.2. Its default download extensions now include doc and ppt: when the Extensions setting is left empty, downloads of .doc and .ppt files are tracked too.

= 0.5.0 =
* The bundled takt-core-php moves to 0.7.1, which loads takt-core 0.12.1. The ingest origin and the script origin must now use https, localhost included: plain http values are ignored, an admin notice names the setting, and the TAKT_PLAINTEXT_HOSTS constant no longer has any effect.
* New setting Redacted routes (SDK mode): route patterns such as /verify/{token} are sent as their pattern instead of the real path. Constraints such as {code<\d{1,3}>} are supported. A value that breaks its constraint is not masked (/reset/1234 with {code<\d{1,3}>} is sent in clear), and the pattern /* rewrites every page: do not use it.
* New setting Route templates (SDK mode): sends every page under a template, never under its real path. The template comes from the takt_route_template filter, or defaults to / on the front page, /{post_type}/{slug} on a single post or page, and /[redacted] elsewhere. The filter value is not validated: it is the integrator's responsibility. Downloads stay tracked: the destination is sent as the followed origin plus /[redacted], and only its extension remains in clear. The referrer is always reduced to its origin with a trailing / (for example https://example.com/). Outbound links to the same host are masked; links to another host only go through Redacted routes. A page URL that cannot be read reliably (backslash, control character, surrounding spaces, user information, non-DNS host) goes out as the followed origin plus /[redacted]; on WooCommerce, the URL falls back to https://<domain>/.
* An admin notice warns when Redacted routes or Route templates are set outside SDK mode: those settings are ignored there and raw paths are sent.
* A plain http script origin suspends the tracking (no snippet at all) until it is replaced by a valid https origin, instead of falling back to the default collect URL.
* The redacted routes also apply to the server-to-server purchase events, in SDK mode.
* The admin screen and its messages are translatable (text domain takt-analytics, French source).
* With an http origin, the server-to-server client is no longer built, so a misconfigured origin cannot break page loads.

= 0.4.0 =
* The Script origin and Ingest origin settings accept https only (plain http stays allowed for localhost, 127.0.0.1 and [::1], or for hosts listed in the TAKT_PLAINTEXT_HOSTS constant). A stored ingest origin that is no longer accepted is ignored and an admin notice says so.
* The admin screen warns when the API key is stored in the database rather than in the TAKT_API_KEY constant.
* The bundled takt-core-php moves to 0.6.1, which loads takt-core 0.11.1. Outbound-link, file-download and 404 paths are now scrubbed like the page URL, and excluded paths match case-insensitively.

= 0.3.4 =
* The bundled takt-core-php moves to 0.5.2, which loads takt-core 0.9.0. With a custom URL scrubber, outbound-link and file-download URLs are now scrubbed too, not only the page URL and referrer.

= 0.3.3 =
* Fix: the Kept query params, Excluded paths and File extensions settings were emptied on every page load, right after being saved. The snippet never carried data-query-params and the admin fields came back blank.

= 0.3.2 =
* Fix: the ingest origin setting now accepts the full collect URL (https://taktlytics.com/api/event) as well as the origin. It used to be sent verbatim to a client that appends /api/event, so purchases silently posted to /api/event/api/event and were lost.
* The settings field is renamed "Ingest origin" and documents both accepted forms. Existing settings are unaffected.
* Docs: corrected the settings reference (19 settings, real defaults), the tagged-events selector (data-takt-event) and the description of Takt as a managed service.

= 0.3.1 =
* Harden the Excluded paths sanitizer so a trailing newline can no longer slip through.

= 0.3.0 =
* New Excluded paths setting: path prefixes the browser tracker never records (SDK mode only).

= 0.2.0 =
* WooCommerce orders are claimed before the Purchase event is sent, so a re-entrant status hook counts a purchase at most once.
* Server-to-server header sanitization and revenue amount validation from takt-core-php.

= 0.1.0 =
* Initial release: snippet injection and WooCommerce server-to-server purchase events.

== Upgrade Notice ==

= 0.5.2 =
Fixes event tracking broken since 0.4.0: events after the first pageview were rejected and takt('Event') threw. No settings change, update to 0.5.2.

= 0.5.1 =
Ships takt-core-php 0.7.2 (takt-core 0.12.2). No settings change; .doc and .ppt downloads are now tracked when the Extensions setting is empty.

= 0.5.0 =
The ingest and script origins must use https, localhost included. A plain http origin is now ignored: switch it to https. A refused script origin suspends the tracking until it is fixed, and a refused ingest origin stops WooCommerce purchases from arriving.

= 0.4.0 =
The ingest and script origins must use https. If WooCommerce purchases stop arriving, check Settings → Takt Analytics for a plain http origin.

= 0.3.4 =
Ships takt-core-php 0.5.2 (takt-core 0.9.0). No settings change.

= 0.3.3 =
Fixes three list settings being silently emptied after save. Re-enter your Kept query params, Excluded paths and File extensions if you had set them.

= 0.3.2 =
Fixes WooCommerce purchases silently failing when the ingest setting held the full collect URL. Check Settings → Takt Analytics if purchases never arrived.

= 0.1.0 =
Initial release.
