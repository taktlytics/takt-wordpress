<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress;

use Vskstudio\Takt\Options;

final class Settings
{
    /** Chemin de collecte que core-php ajoute lui-même à l'origine d'ingestion. */
    private const INGEST_PATH = '/api/event';

    private const MAX_ROUTE_LENGTH = 200;

    private const URL_KEYS = ['api_endpoint', 'script_origin'];

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    public static function sanitize(array $raw): array
    {
        $domain = is_string($raw['domain'] ?? null) ? trim($raw['domain']) : '';
        $mode = in_array($raw['mode'] ?? null, ['cdn', 'asset', 'sdk'], true) ? $raw['mode'] : 'inline';

        $clean = [
            'domain' => self::isValidHostname($domain) ? $domain : '',
            'mode' => $mode,
            'outbound' => !empty($raw['outbound']),
            'files' => !empty($raw['files']),
            'not_found' => !empty($raw['not_found']),
            'tagged' => !empty($raw['tagged']),
            'exclude_localhost' => !empty($raw['exclude_localhost']),
            'file_extensions' => self::parseExtensions($raw['file_extensions'] ?? ''),
            'script_origin' => self::sanitizeUrl($raw['script_origin'] ?? ''),
            // Advanced options. respect_dnt/enabled default ON in the tracker, so
            // they are surfaced as opt-out flags (ignore_dnt / disable_tracking)
            // A fresh, never-saved install keeps tracking on and DNT honored.
            'sample_rate' => self::sanitizeRate($raw['sample_rate'] ?? ''),
            'track_query' => !empty($raw['track_query']),
            'query_params' => self::parseParams($raw['query_params'] ?? ''),
            'exclude' => self::parsePaths($raw['exclude'] ?? ''),
            'redact_routes' => self::parseRoutes($raw['redact_routes'] ?? ''),
            'route_templates' => !empty($raw['route_templates']),
            'ignore_dnt' => !empty($raw['ignore_dnt']),
            'disable_tracking' => !empty($raw['disable_tracking']),
            'woocommerce' => !empty($raw['woocommerce']),
            'wc_trigger_status' => in_array($raw['wc_trigger_status'] ?? null, ['completed', 'processing'], true)
                ? $raw['wc_trigger_status']
                : 'completed',
            'api_key' => is_string($raw['api_key'] ?? null) ? trim($raw['api_key']) : '',
            'api_endpoint' => self::sanitizeIngestOrigin($raw['api_endpoint'] ?? ''),
        ];
        $clean['refused_urls'] = self::refusedUrlsFor($raw, $clean);

        return $clean;
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<string>
     */
    public static function refusedUrls(array $settings): array
    {
        $keys = is_array($settings['refused_urls'] ?? null) ? $settings['refused_urls'] : [];

        return array_values(array_filter(
            $keys,
            static fn (mixed $key): bool => is_string($key) && in_array($key, self::URL_KEYS, true),
        ));
    }

    /**
     * @param array<string,mixed> $s
     * @return list<string>
     */
    public static function redactRoutes(array $s): array
    {
        $routes = is_array($s['redact_routes'] ?? null) ? $s['redact_routes'] : [];

        return ($s['mode'] ?? 'inline') === 'sdk' ? array_values($routes) : [];
    }

    /**
     * Build the core-php Options (snippet config) from stored settings. The CSP
     * nonce is supplied at render time (via filter), not stored.
     *
     * @param array<string,mixed> $s
     */
    public static function toOptions(array $s, ?string $nonce = null): Options
    {
        $scriptOrigin = is_string($s['script_origin'] ?? null) ? $s['script_origin'] : '';
        $mode = $s['mode'] ?? 'inline';
        // scrubUrl is a raw JS function injected verbatim, dev-controlled via the
        // TAKT_SCRUB_URL constant (see Plugin::boot). It is only expressible in
        // sdk mode; outside it core-php would throw, so we drop it silently to
        // avoid fataling every page.
        $scrubRaw = is_string($s['scrub_url'] ?? null) ? trim($s['scrub_url']) : '';
        $scrubUrl = ($mode === 'sdk' && $scrubRaw !== '') ? $scrubRaw : null;
        // exclude is a path-prefix allowlist that, like scrubUrl, is only valid
        // in sdk mode, core-php throws if it is set otherwise. Drop it outside
        // sdk (or when empty) so the admin can't fatal every page.
        $exclude = is_array($s['exclude'] ?? null) ? $s['exclude'] : [];
        $redactRoutes = self::redactRoutes($s);
        $routeTemplates = $mode === 'sdk' && !empty($s['route_templates']);

        $args = [
            'domain' => $s['domain'] ?? '',
            'mode' => $mode,
            'outbound' => $s['outbound'] ?? false,
            'files' => $s['files'] ?? false,
            'not_found' => $s['not_found'] ?? false,
            'tagged' => $s['tagged'] ?? false,
            'exclude_localhost' => $s['exclude_localhost'] ?? true,
            'file_extensions' => $s['file_extensions'] ?? [],
            'scriptOrigin' => $scriptOrigin !== '' ? $scriptOrigin : null,
            'nonce' => $nonce,
            'sampleRate' => $s['sample_rate'] ?? null,
            'trackQuery' => !empty($s['track_query']) ? true : null,
            'queryParams' => $s['query_params'] ?? [],
            'respectDnt' => !empty($s['ignore_dnt']) ? false : null,
            'enabled' => !empty($s['disable_tracking']) ? false : null,
            'scrubUrl' => $scrubUrl,
            'routeTemplates' => $routeTemplates,
        ];

        if ($mode === 'sdk' && $exclude !== []) {
            $args['exclude'] = $exclude;
        }
        if ($redactRoutes !== []) {
            $args['redactRoutes'] = $redactRoutes;
        }

        return Options::fromArray($args);
    }

    /** A sampling rate in (0, 1], else null (track everything). */
    private static function sanitizeRate(mixed $raw): ?float
    {
        if (!is_scalar($raw) || ($s = trim((string) $raw)) === '' || !is_numeric($s)) {
            return null;
        }
        $rate = (float) $s;

        return ($rate > 0 && $rate <= 1) ? $rate : null;
    }

    /**
     * "utm_source, utm_medium ,bad token" → ["utm_source", "utm_medium"]: a
     * comma-separated allowlist of query-param names. Only [A-Za-z0-9_-] tokens
     * survive. The rest would not be valid param names anyway.
     *
     * @return list<string>
     */
    private static function parseParams(mixed $raw): array
    {
        $out = [];
        foreach (explode(',', self::toCsv($raw)) as $part) {
            $name = trim($part);
            if ($name !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * "/app, /account , foo,," → ["/app", "/account"]: a comma-separated list of
     * path prefixes never tracked. Only path-shaped entries survive. Each must
     * start with "/" and contain only [A-Za-z0-9_-] and slashes (no markup,
     * spaces or query strings). Consumed as the sdk-only `exclude` option.
     *
     * @return list<string>
     */
    private static function parsePaths(mixed $raw): array
    {
        $out = [];
        foreach (explode(',', self::toCsv($raw)) as $part) {
            $path = trim($part);
            if ($path !== '' && preg_match('#^/[A-Za-z0-9_\-/]*\z#', $path)) {
                $out[] = $path;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $raw
     * @param array<string,mixed> $clean
     * @return list<string>
     */
    private static function refusedUrlsFor(array $raw, array $clean): array
    {
        $persisted = self::refusedUrls($raw);
        $refused = [];
        foreach (self::URL_KEYS as $key) {
            $submitted = is_string($raw[$key] ?? null) ? trim($raw[$key]) : '';
            $wasRefused = $submitted !== '' || in_array($key, $persisted, true);
            if ($wasRefused && $clean[$key] === '') {
                $refused[] = $key;
            }
        }

        return $refused;
    }

    /** @return list<string> */
    private static function parseRoutes(mixed $raw): array
    {
        $out = [];
        foreach (preg_split('#,(?![^{}<>]*[}>])#', self::toCsv($raw)) ?: [] as $part) {
            $route = trim($part);
            if ($route !== '' && strlen($route) <= self::MAX_ROUTE_LENGTH && preg_match('#^/[A-Za-z0-9_\-./:*+?{}\[\]()<>|\\\\,=]*\z#', $route)) {
                $out[] = $route;
            }
        }

        return $out;
    }

    /**
     * L'origine d'ingestion serveur-à-serveur : core-php lui ajoute
     * « /api/event » à chaque envoi. Le réglage accepte donc les deux formes :
     * l'origine (https://taktlytics.com) et l'URL de collecte complète
     * (https://taktlytics.com/api/event, la forme documentée côté snippet), qui
     * est repliée sur son origine. Sans ce repli, l'envoi partait vers
     * « /api/event/api/event » et échouait en silence. Les réglages déjà
     * enregistrés sont des origines : ils traversent la normalisation inchangés.
     */
    private static function sanitizeIngestOrigin(mixed $raw): string
    {
        $url = self::sanitizeUrl($raw);
        if ($url === '' || !str_ends_with(strtolower($url), self::INGEST_PATH)) {
            return $url;
        }

        return rtrim(substr($url, 0, -strlen(self::INGEST_PATH)), '/');
    }

    private static function toCsv(mixed $raw): string
    {
        if (is_array($raw)) {
            $raw = implode(',', array_filter($raw, 'is_scalar'));
        }

        return is_string($raw) ? $raw : '';
    }

    private static function sanitizeUrl(mixed $raw): string
    {
        if (!is_string($raw) || ($url = trim($raw)) === '') {
            return '';
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($scheme !== 'https' || $host === '') {
            return '';
        }

        return rtrim($url, '/');
    }

    /**
     * "PDF, zip , .docx" → ["pdf", "zip", "docx"]: lower-cased, trimmed, a
     * leading dot dropped, blanks removed. Matches how takt.auto.js compares
     * download extensions (lower-cased, no dot).
     *
     * @return list<string>
     */
    private static function parseExtensions(mixed $raw): array
    {
        $out = [];
        foreach (explode(',', self::toCsv($raw)) as $part) {
            $ext = ltrim(strtolower(trim($part)), '.');
            if ($ext !== '') {
                $out[] = $ext;
            }
        }

        return $out;
    }

    /**
     * A hostname like "example.com" or "shop.example.co.uk": dot-separated
     * labels of letters, digits and hyphens (not leading/trailing), ≤253 chars.
     * Anything else (markup, spaces, schemes) is rejected. The snippet is
     * injected into every page's <head>, so the domain must never carry markup.
     */
    private static function isValidHostname(string $value): bool
    {
        if ($value === '' || strlen($value) > 253) {
            return false;
        }

        return (bool) preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
            $value,
        );
    }
}
