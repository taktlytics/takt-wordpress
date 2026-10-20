# Takt Analytics for WordPress

## 0.5.0

- The bundled `vskstudio/takt-core-php` moves to 0.7.0, which loads
  `@vskstudio/takt-core` 0.12.0.
- **Breaking for local development**: the ingest origin and the script origin
  must use `https`, `localhost` included. `takt-core-php` 0.7 refuses any other
  endpoint, so a stored `http://` value is ignored on save and on read, the
  admin field comes back empty and a notice names the setting. The
  `TAKT_PLAINTEXT_HOSTS` constant has no effect any more and is removed from the
  docs. The server-to-server client is no longer built from a refused origin, so
  a misconfigured origin never breaks page loads.
- New **Redacted routes** setting (SDK mode, comma-separated): route patterns
  such as `/verify/{token}` are sent as their pattern instead of the real path,
  and segment constraints like `{code<\d{1,3}>}` are honoured. Like **Excluded
  paths**, it is dropped in the other modes. It also applies to server-to-server
  purchase events. Two limits: a value that breaks its constraint is not masked
  (`/reset/1234` with `{code<\d{1,3}>}` is sent in clear), and the pattern `/*`
  rewrites every page, so avoid it.
- A plain `http` **Script origin** now suspends the tracking: no snippet is
  rendered, so nothing falls back to the default collect URL. The refusal is
  stored with the settings and an admin notice says tracking is suspended until
  the origin is fixed. A blank save does not lift it.
- New **Route templates** setting (SDK mode): every page is sent under a
  template, never its raw path. The template comes from the `takt_route_template`
  filter; without one it is `/` on the front page, `/{post_type}/{slug}` on a
  single post or page, and `/[redacted]` elsewhere.
- The admin screen strings are translatable (text domain `takt-analytics`,
  French source).

## 0.4.0

- The bundled `vskstudio/takt-core-php` moves to 0.6.1, which pins and vendors
  `@vskstudio/takt-core` 0.11.1. Route redaction and URL scrubbing now also
  apply to outbound-link and file-download destinations and to the `404` path,
  and excluded paths match case-insensitively on the percent-decoded path.

### Hardening

- The **Script origin** and **Ingest origin** settings accept `https` only. Plain
  `http` is still accepted for `localhost`, `127.0.0.1` and `[::1]`; other hosts
  can be allowed with the `TAKT_PLAINTEXT_HOSTS` constant in `wp-config.php`.
  Upgrade note: a stored ingest origin that is no longer accepted (plain `http`
  outside loopback, or a URL without a scheme) is ignored. WooCommerce purchases
  stop being sent until it is corrected to `https`; an admin notice and the
  settings screen both say so.
- The admin screen warns when the API key is stored in the database rather than
  through the `TAKT_API_KEY` constant in `wp-config.php`.
- The release workflow grants `contents: write` only to the job that publishes
  the release, the build job runs read-only, and every action is pinned to a
  commit SHA with `persist-credentials: false` on checkouts.
- `bin/build.sh` pins php-scoper to 0.18.20 and refuses to run it unless its
  SHA256 matches. `composer.lock` is now versioned, so the build resolves the
  same dependencies every time.
- The README documents where the IP address sent with a WooCommerce purchase
  comes from and when it reflects the real visitor.

## 0.3.4

- The bundled `vskstudio/takt-core-php` moves to 0.5.2, which pins and vendors
  `@vskstudio/takt-core` 0.9.0. In SDK mode, a custom `scrubUrl` now also
  rewrites the `url` prop of outbound-link and file-download events.

## 0.3.3

- **Kept query params**, **Excluded paths** and **File extensions** were silently
  emptied on every page load. `Settings::sanitize()` parses the comma-separated
  string typed in the admin field into a list, but it is also re-applied to the
  stored settings on each read (`Plugin::boot`, `AdminPage::render`). Fed its own
  output, the three parsers saw a list instead of a string and returned an empty
  one. The setting was therefore lost right after being saved: the snippet never
  carried `data-query-params`, and the admin field came back blank. The parsers
  now accept both forms, so `sanitize()` is idempotent, which a test locks in.

## 0.3.2

- **Ingest origin**: the server-to-server setting is an *origin*: `takt-core-php`
  appends `/api/event` on every send, but nothing stopped an admin from pasting
  the full collect URL, which is the form documented everywhere on the snippet
  side (`https://taktlytics.com/api/event`). The request then went to
  `/api/event/api/event` and failed silently; since the order is claimed before
  the send, the purchase was lost for good. Both forms are now accepted: a
  trailing `/api/event` is folded back to its origin. Settings already stored are
  origins and go through unchanged.
- The settings field is renamed **Origine d'ingestion** and spells out which of
  the two URLs it expects.
- Docs: the settings reference now lists the 19 settings with their real
  defaults (**Exclure localhost** is off on a fresh install, not on), names the
  actual tagged-events selector (`[data-takt-event]`, with `data-takt-prop-*`
  properties), separates the ingest origin from the snippet's collect URL, and
  no longer suggests Takt can be self-hosted. It is a managed service hosted in
  Europe, and only the measurement script can be served from your own domain.
  `readme.txt` also regains the changelog entries it was missing since 0.1.0.

## 0.3.1

- Harden the **Excluded paths** sanitizer: anchor the path pattern with `\z`
  so a trailing newline can no longer slip through.

## 0.3.0

- New **Excluded paths** setting: a comma-separated list of path prefixes
  (e.g. `/app, /account`) that the browser tracker never records. This is a
  privacy control backed by the tracker's `exclude` option and is therefore
  **SDK-mode only**. It is silently dropped in the inline / CDN / asset modes,
  where `takt-core-php` would otherwise throw.
- Require `vskstudio/takt-core-php` `^0.5.0`, which adds the `exclude` option.

## 0.2.0

### Hardening

- WooCommerce: the order is now claimed (the `_takt_tracked` guard is marked and
  saved) **before** the Purchase event is sent, not after, so a re-entrant status
  hook (processing→completed, a plugin re-saving the order, a double admin click)
  counts a purchase at most once.
- Require `vskstudio/takt-core-php` `^0.3.2`, pulling in the S2S header
  sanitization (CR/LF stripped from the forwarded buyer IP/User-Agent and the
  API key), the numeric `Revenue` amount validation, and the re-bundled inline
  tracker.

## 0.1.0

- Initial release: injects the privacy-first Takt browser snippet into
  `wp_head` (inline / CDN / hosted-asset / SDK modes) and reports WooCommerce
  purchases as server-to-server events.
