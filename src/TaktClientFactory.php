<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Vskstudio\Takt\Takt;
use Vskstudio\Takt\WordPress\Http\WpRemotePostClient;

/**
 * Builds the core-php S2S client from stored settings, over WordPress' own HTTP
 * layer. Returns null when ingest is not configured (no origin or key), so the
 * plugin simply skips server-side events instead of erroring.
 *
 * `api_endpoint` is an ingest *origin* (e.g. `https://taktlytics.com`), not a
 * collect URL: core-php appends `/api/event` on every send. Pass settings that
 * went through {@see Settings::sanitize()}, which folds a full collect URL back
 * to its origin so the two forms cannot produce `/api/event/api/event`.
 */
final class TaktClientFactory
{
    /** @param array<string,mixed> $settings */
    public static function fromSettings(array $settings, ?ClientInterface $client = null): ?Takt
    {
        $endpoint = (string) ($settings['api_endpoint'] ?? '');
        $apiKey = (string) ($settings['api_key'] ?? '');
        if ($endpoint === '' || $apiKey === '') {
            return null;
        }

        $factory = new Psr17Factory();

        try {
            return new Takt(
                $endpoint,
                (string) ($settings['domain'] ?? ''),
                $apiKey,
                $client ?? new WpRemotePostClient(),
                $factory,
                $factory,
            );
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
