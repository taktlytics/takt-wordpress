<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vskstudio\Takt\WordPress\Settings;
use Vskstudio\Takt\WordPress\SnippetInjector;

final class SnippetInjectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);
        Functions\when('is_front_page')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_markup_is_empty_without_a_configured_domain(): void
    {
        $this->assertSame('', SnippetInjector::markup(['domain' => '']));
        $this->assertSame('', SnippetInjector::markup([]));
    }

    public function test_markup_renders_the_snippet_for_a_configured_domain(): void
    {
        $html = SnippetInjector::markup([
            'domain' => 'example.com',
            'mode' => 'cdn',
            'outbound' => true,
        ]);

        $this->assertStringContainsString('<script', $html);
        $this->assertStringContainsString('data-domain="example.com"', $html);
        $this->assertStringContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringContainsString('data-auto="outbound"', $html);
    }

    public function test_markup_adds_the_csp_nonce_when_provided(): void
    {
        $html = SnippetInjector::markup(['domain' => 'example.com', 'mode' => 'cdn'], 'nonce-xyz');

        $this->assertStringContainsString('nonce="nonce-xyz"', $html);
    }

    public function test_markup_hands_redacted_routes_to_the_sdk_only(): void
    {
        $sdk = SnippetInjector::markup([
            'domain' => 'example.com',
            'mode' => 'sdk',
            'redact_routes' => ['/verify/{token}'],
        ]);
        $cdn = SnippetInjector::markup([
            'domain' => 'example.com',
            'mode' => 'cdn',
            'redact_routes' => ['/verify/{token}'],
        ]);

        $this->assertStringContainsString('"redactRoutes":["\\/verify\\/[token]"]', $sdk);
        $this->assertStringNotContainsString('redactRoutes', $cdn);
    }

    public function test_an_http_script_origin_never_renders_a_snippet_in_any_mode(): void
    {
        foreach (['inline', 'cdn', 'asset', 'sdk'] as $mode) {
            foreach (['http://cdn.example.com', 'http://localhost:8080', 'HTTP://cdn.example.com'] as $origin) {
                $html = SnippetInjector::markup(Settings::sanitize([
                    'domain' => 'example.com',
                    'mode' => $mode,
                    'script_origin' => $origin,
                ]));

                $this->assertSame('', $html, "mode {$mode}, origin {$origin}");
            }
        }
    }

    public function test_a_refused_script_origin_never_falls_back_to_taktlytics(): void
    {
        $html = SnippetInjector::markup(Settings::sanitize([
            'domain' => 'example.com',
            'mode' => 'inline',
            'script_origin' => 'http://cdn.example.com',
        ]));

        $this->assertStringNotContainsString('taktlytics.com', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_a_valid_https_script_origin_still_renders(): void
    {
        $html = SnippetInjector::markup(Settings::sanitize([
            'domain' => 'example.com',
            'mode' => 'cdn',
            'script_origin' => 'https://cdn.example.com',
        ]));

        $this->assertStringContainsString('data-domain="example.com"', $html);
    }

    public function test_route_templates_send_the_custom_filter_value(): void
    {
        Functions\when('apply_filters')->alias(
            static fn (string $hook, mixed $value): mixed => $hook === 'takt_route_template' ? '/shop/{sku}' : $value,
        );

        $html = SnippetInjector::markup(Settings::sanitize([
            'domain' => 'example.com',
            'mode' => 'sdk',
            'route_templates' => '1',
        ]));

        $this->assertStringContainsString('routeTemplate', $html);
        $this->assertStringContainsString('shop', $html);
    }

    public function test_route_templates_default_to_the_front_page_template(): void
    {
        Functions\when('is_front_page')->justReturn(true);

        $html = SnippetInjector::markup(Settings::sanitize(['domain' => 'example.com', 'mode' => 'sdk', 'route_templates' => '1']));

        $this->assertStringContainsString('routeTemplate:()=>"\\/"', $html);
    }

    public function test_route_templates_default_to_the_singular_template(): void
    {
        Functions\when('is_singular')->justReturn(true);

        $html = SnippetInjector::markup(Settings::sanitize(['domain' => 'example.com', 'mode' => 'sdk', 'route_templates' => '1']));

        $this->assertStringContainsString('{post_type}', $html);
        $this->assertStringContainsString('{slug}', $html);
    }

    public function test_route_templates_fall_back_to_the_redacted_placeholder_without_a_match(): void
    {
        $html = SnippetInjector::markup(Settings::sanitize(['domain' => 'example.com', 'mode' => 'sdk', 'route_templates' => '1']));

        $this->assertStringContainsString('routeTemplate:()=>"\\/[redacted]"', $html);
    }

    public function test_no_route_template_is_emitted_when_the_checkbox_is_off(): void
    {
        $html = SnippetInjector::markup(Settings::sanitize(['domain' => 'example.com', 'mode' => 'sdk']));

        $this->assertStringNotContainsString('routeTemplate', $html);
    }
}
