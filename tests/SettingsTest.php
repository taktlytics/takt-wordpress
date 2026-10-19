<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Vskstudio\Takt\Mode;
use Vskstudio\Takt\WordPress\Settings;

final class SettingsTest extends TestCase
{
    public function test_sanitize_keeps_a_valid_domain(): void
    {
        $out = Settings::sanitize(['domain' => 'example.com']);

        $this->assertSame('example.com', $out['domain']);
    }

    public function test_sanitize_rejects_a_domain_with_invalid_characters(): void
    {
        $out = Settings::sanitize(['domain' => 'evil.com"></script><script>alert(1)</script>']);

        $this->assertSame('', $out['domain']);
    }

    public function test_sanitize_whitelists_the_mode_and_defaults_to_inline(): void
    {
        $this->assertSame('cdn', Settings::sanitize(['mode' => 'cdn'])['mode']);
        $this->assertSame('asset', Settings::sanitize(['mode' => 'asset'])['mode']);
        $this->assertSame('sdk', Settings::sanitize(['mode' => 'sdk'])['mode']);
        $this->assertSame('inline', Settings::sanitize(['mode' => 'nonsense'])['mode']);
        $this->assertSame('inline', Settings::sanitize([])['mode']);
    }

    public function test_sanitize_clamps_sample_rate(): void
    {
        $this->assertSame(0.5, Settings::sanitize(['sample_rate' => '0.5'])['sample_rate']);
        $this->assertSame(1.0, Settings::sanitize(['sample_rate' => '1'])['sample_rate']);
        $this->assertNull(Settings::sanitize(['sample_rate' => '0'])['sample_rate']);
        $this->assertNull(Settings::sanitize(['sample_rate' => '1.5'])['sample_rate']);
        $this->assertNull(Settings::sanitize(['sample_rate' => 'abc'])['sample_rate']);
        $this->assertNull(Settings::sanitize([])['sample_rate']);
    }

    public function test_sanitize_parses_query_params_allowlist(): void
    {
        $out = Settings::sanitize(['query_params' => 'utm_source, utm_medium , bad token,,']);
        $this->assertSame(['utm_source', 'utm_medium'], $out['query_params']);
        $this->assertSame([], Settings::sanitize([])['query_params']);
    }

    public function test_sanitize_parses_exclude_paths(): void
    {
        $out = Settings::sanitize(['exclude' => '/app, /account/settings , not-a-path,bad path,/a_b-c/,,']);
        $this->assertSame(['/app', '/account/settings', '/a_b-c/'], $out['exclude']);
        $this->assertSame([], Settings::sanitize([])['exclude']);
    }

    public function test_sanitize_is_idempotent_on_its_own_output(): void
    {
        $once = Settings::sanitize([
            'domain' => 'example.com',
            'query_params' => 'utm_source, utm_medium, gclid',
            'exclude' => '/app, /account',
            'file_extensions' => 'pdf, zip',
        ]);

        $this->assertSame($once, Settings::sanitize($once));
    }

    public function test_sanitize_accepts_already_parsed_lists(): void
    {
        $out = Settings::sanitize([
            'query_params' => ['utm_source', 'gclid'],
            'exclude' => ['/app'],
            'file_extensions' => ['pdf', 'zip'],
        ]);

        $this->assertSame(['utm_source', 'gclid'], $out['query_params']);
        $this->assertSame(['/app'], $out['exclude']);
        $this->assertSame(['pdf', 'zip'], $out['file_extensions']);
    }

    public function test_sanitize_casts_advanced_boolean_flags(): void
    {
        $on = Settings::sanitize(['track_query' => '1', 'ignore_dnt' => '1', 'disable_tracking' => '1']);
        $this->assertTrue($on['track_query']);
        $this->assertTrue($on['ignore_dnt']);
        $this->assertTrue($on['disable_tracking']);

        $off = Settings::sanitize([]);
        $this->assertFalse($off['track_query']);
        $this->assertFalse($off['ignore_dnt']);
        $this->assertFalse($off['disable_tracking']);
    }

    public function test_sanitize_casts_boolean_flags(): void
    {
        $on = Settings::sanitize([
            'outbound' => '1',
            'files' => 'on',
            'not_found' => '1',
            'tagged' => '1',
            'exclude_localhost' => '1',
        ]);
        $this->assertTrue($on['outbound']);
        $this->assertTrue($on['files']);
        $this->assertTrue($on['not_found']);
        $this->assertTrue($on['tagged']);
        $this->assertTrue($on['exclude_localhost']);

        $off = Settings::sanitize([]);
        $this->assertFalse($off['outbound']);
        $this->assertFalse($off['files']);
        $this->assertFalse($off['not_found']);
        $this->assertFalse($off['tagged']);
        $this->assertFalse($off['exclude_localhost']);
    }

    public function test_sanitize_normalizes_file_extensions_csv_to_a_list(): void
    {
        $out = Settings::sanitize(['file_extensions' => 'PDF, zip , .docx,,']);

        $this->assertSame(['pdf', 'zip', 'docx'], $out['file_extensions']);
        $this->assertSame([], Settings::sanitize([])['file_extensions']);
    }

    public function test_sanitize_handles_woocommerce_fields(): void
    {
        $out = Settings::sanitize([
            'woocommerce' => '1',
            'wc_trigger_status' => 'processing',
            'api_key' => '  secret-key  ',
        ]);
        $this->assertTrue($out['woocommerce']);
        $this->assertSame('processing', $out['wc_trigger_status']);
        $this->assertSame('secret-key', $out['api_key']);

        $def = Settings::sanitize(['wc_trigger_status' => 'garbage']);
        $this->assertFalse($def['woocommerce']);
        $this->assertSame('completed', $def['wc_trigger_status']);
        $this->assertSame('', $def['api_key']);
    }

    public function test_sanitize_validates_url_fields(): void
    {
        $out = Settings::sanitize([
            'api_endpoint' => 'https://takt.example.com/',
            'script_origin' => 'https://cdn.example.com',
        ]);
        $this->assertSame('https://takt.example.com', $out['api_endpoint']);
        $this->assertSame('https://cdn.example.com', $out['script_origin']);

        $bad = Settings::sanitize([
            'api_endpoint' => 'javascript:alert(1)',
            'script_origin' => 'not a url',
        ]);
        $this->assertSame('', $bad['api_endpoint']);
        $this->assertSame('', $bad['script_origin']);
    }

    public function test_sanitize_rejects_plain_http_urls_outside_loopback(): void
    {
        $out = Settings::sanitize([
            'api_endpoint' => 'http://takt.example.com',
            'script_origin' => 'http://cdn.example.com',
        ]);

        $this->assertSame('', $out['api_endpoint']);
        $this->assertSame('', $out['script_origin']);
        $this->assertSame('', Settings::sanitize(['script_origin' => 'http://localhost.example.com'])['script_origin']);
        $this->assertSame('', Settings::sanitize(['api_endpoint' => 'http://host.docker.internal:9911'])['api_endpoint']);
    }

    public function test_sanitize_accepts_plain_http_on_loopback_hosts(): void
    {
        $this->assertSame(
            'http://localhost:9911',
            Settings::sanitize(['api_endpoint' => 'http://localhost:9911/'])['api_endpoint'],
        );
        $this->assertSame(
            'http://127.0.0.1:8080',
            Settings::sanitize(['script_origin' => 'http://127.0.0.1:8080'])['script_origin'],
        );
        $this->assertSame(
            'http://[::1]:8080',
            Settings::sanitize(['api_endpoint' => 'http://[::1]:8080'])['api_endpoint'],
        );
    }

    #[RunInSeparateProcess]
    public function test_sanitize_accepts_plain_http_on_hosts_listed_in_the_constant(): void
    {
        define('TAKT_PLAINTEXT_HOSTS', ' host.docker.internal, takt.internal.test ,');

        $this->assertSame(
            'http://host.docker.internal:9911',
            Settings::sanitize(['api_endpoint' => 'http://host.docker.internal:9911'])['api_endpoint'],
        );
        $this->assertSame(
            'http://takt.internal.test',
            Settings::sanitize(['script_origin' => 'http://takt.internal.test'])['script_origin'],
        );
        $this->assertSame('', Settings::sanitize(['api_endpoint' => 'http://other.example.com'])['api_endpoint']);
    }

    public function test_sanitize_folds_a_full_collect_url_back_to_its_ingest_origin(): void
    {
        // core-php appends "/api/event" itself: the documented collect URL must
        // not end up posting to "/api/event/api/event".
        $hosted = Settings::sanitize(['api_endpoint' => 'https://taktlytics.com/api/event']);
        $this->assertSame('https://taktlytics.com', $hosted['api_endpoint']);

        $slash = Settings::sanitize(['api_endpoint' => 'https://taktlytics.com/api/event/']);
        $this->assertSame('https://taktlytics.com', $slash['api_endpoint']);

        $upper = Settings::sanitize(['api_endpoint' => 'https://taktlytics.com/API/Event']);
        $this->assertSame('https://taktlytics.com', $upper['api_endpoint']);

        $proxy = Settings::sanitize(['api_endpoint' => 'https://analytics.example.com/t/api/event']);
        $this->assertSame('https://analytics.example.com/t', $proxy['api_endpoint']);
    }

    public function test_sanitize_leaves_an_ingest_origin_untouched(): void
    {
        // Backward compatibility: every already-stored setting is an origin.
        $this->assertSame(
            'https://taktlytics.com',
            Settings::sanitize(['api_endpoint' => 'https://taktlytics.com'])['api_endpoint'],
        );
        $this->assertSame(
            'https://analytics.example.com/ingest',
            Settings::sanitize(['api_endpoint' => 'https://analytics.example.com/ingest'])['api_endpoint'],
        );
        // "/api/event" only folds when it is the trailing path, not a substring.
        $this->assertSame(
            'https://takt.example.com/api/events',
            Settings::sanitize(['api_endpoint' => 'https://takt.example.com/api/events'])['api_endpoint'],
        );
    }

    public function test_to_options_maps_settings_to_core_options(): void
    {
        $opts = Settings::toOptions([
            'domain' => 'example.com',
            'mode' => 'cdn',
            'outbound' => true,
            'files' => true,
            'not_found' => true,
            'tagged' => true,
            'exclude_localhost' => false,
            'file_extensions' => ['pdf', 'zip'],
            'script_origin' => 'https://cdn.example.com',
        ], 'nonce-123');

        $this->assertSame('example.com', $opts->domain);
        $this->assertSame(Mode::Cdn, $opts->mode);
        $this->assertTrue($opts->outbound);
        $this->assertTrue($opts->files);
        $this->assertTrue($opts->notFound);
        $this->assertTrue($opts->tagged);
        $this->assertFalse($opts->excludeLocalhost);
        $this->assertSame(['pdf', 'zip'], $opts->fileExtensions);
        $this->assertSame('https://cdn.example.com', $opts->scriptOrigin);
        $this->assertSame('nonce-123', $opts->nonce);
    }

    public function test_to_options_maps_advanced_options(): void
    {
        $opts = Settings::toOptions([
            'domain' => 'example.com',
            'mode' => 'cdn',
            'sample_rate' => 0.5,
            'track_query' => true,
            'query_params' => ['utm_source'],
            'ignore_dnt' => true,
            'disable_tracking' => true,
        ]);

        $this->assertSame(0.5, $opts->sampleRate);
        $this->assertTrue($opts->trackQuery);
        $this->assertSame(['utm_source'], $opts->queryParams);
        $this->assertFalse($opts->respectDnt);
        $this->assertFalse($opts->enabled);
    }

    public function test_to_options_defaults_leave_advanced_options_unset(): void
    {
        $opts = Settings::toOptions(['domain' => 'example.com', 'mode' => 'cdn']);

        $this->assertNull($opts->sampleRate);
        $this->assertNull($opts->trackQuery);
        $this->assertSame([], $opts->queryParams);
        $this->assertNull($opts->respectDnt);
        $this->assertNull($opts->enabled);
        $this->assertNull($opts->scrubUrl);
    }

    public function test_to_options_passes_scrub_url_only_in_sdk_mode(): void
    {
        $sdk = Settings::toOptions(['domain' => 'example.com', 'mode' => 'sdk', 'scrub_url' => '(u)=>u']);
        $this->assertSame('(u)=>u', $sdk->scrubUrl);
        $this->assertSame(Mode::Sdk, $sdk->mode);

        // Outside sdk mode the JS function is dropped (core-php would otherwise throw).
        $cdn = Settings::toOptions(['domain' => 'example.com', 'mode' => 'cdn', 'scrub_url' => '(u)=>u']);
        $this->assertNull($cdn->scrubUrl);
    }

    public function test_to_options_passes_exclude_only_in_sdk_mode(): void
    {
        $sdk = Settings::toOptions(['domain' => 'example.com', 'mode' => 'sdk', 'exclude' => ['/app', '/account']]);
        $this->assertSame(['/app', '/account'], $sdk->exclude);
        $this->assertSame(Mode::Sdk, $sdk->mode);

        // Outside sdk mode the path list is dropped (core-php would otherwise throw).
        $cdn = Settings::toOptions(['domain' => 'example.com', 'mode' => 'cdn', 'exclude' => ['/app']]);
        $this->assertSame([], $cdn->exclude);
    }
}
