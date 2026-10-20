<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress\Tests;

use PHPUnit\Framework\TestCase;
use Vskstudio\Takt\Takt;
use Vskstudio\Takt\WordPress\Settings;
use Vskstudio\Takt\WordPress\TaktClientFactory;
use Vskstudio\Takt\WordPress\Tests\Support\CapturingClient;

final class TaktClientFactoryTest extends TestCase
{
    public function test_returns_null_when_the_api_key_is_missing(): void
    {
        $this->assertNull(TaktClientFactory::fromSettings([
            'api_endpoint' => 'https://takt.example.com',
            'api_key' => '',
        ]));
    }

    public function test_returns_null_when_the_api_endpoint_is_missing(): void
    {
        $this->assertNull(TaktClientFactory::fromSettings([
            'api_endpoint' => '',
            'api_key' => 'secret',
        ]));
    }

    public function test_builds_a_client_wired_to_the_endpoint_domain_and_key(): void
    {
        $client = new CapturingClient();
        $takt = TaktClientFactory::fromSettings([
            'api_endpoint' => 'https://takt.example.com',
            'domain' => 'shop.example.com',
            'api_key' => 'secret',
        ], $client);

        $this->assertInstanceOf(Takt::class, $takt);

        $takt->event('Ping');

        $this->assertSame('https://takt.example.com/api/event', (string) $client->lastRequest->getUri());
        $this->assertSame('Bearer secret', $client->lastRequest->getHeaderLine('Authorization'));
        $this->assertSame('shop.example.com', $client->lastPayload()['d']);
    }

    public function test_a_full_collect_url_posts_to_a_single_api_event_path(): void
    {
        $client = new CapturingClient();
        $takt = TaktClientFactory::fromSettings(Settings::sanitize([
            'api_endpoint' => 'https://taktlytics.com/api/event',
            'domain' => 'shop.example.com',
            'api_key' => 'secret',
        ]), $client);

        $this->assertInstanceOf(Takt::class, $takt);
        $takt->event('Ping');

        $this->assertSame('https://taktlytics.com/api/event', (string) $client->lastRequest->getUri());
    }

    public function test_returns_null_instead_of_throwing_when_core_php_refuses_a_plain_http_endpoint(): void
    {
        $this->assertNull(TaktClientFactory::fromSettings([
            'api_endpoint' => 'http://localhost:9911',
            'domain' => 'shop.example.com',
            'api_key' => 'secret',
        ]));
    }
}
