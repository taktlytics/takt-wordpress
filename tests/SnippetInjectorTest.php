<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress\Tests;

use PHPUnit\Framework\TestCase;
use Vskstudio\Takt\WordPress\SnippetInjector;

final class SnippetInjectorTest extends TestCase
{
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
}
