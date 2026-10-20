<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress;

use Vskstudio\Takt\SnippetRenderer;

/**
 * Renders the Takt browser snippet for the <head>. Pure markup builder so it can
 * be unit-tested; the WordPress wiring (read option, echo on wp_head) lives in
 * Plugin and stays a thin shell around markup().
 */
final class SnippetInjector
{
    /** @param array<string,mixed> $settings */
    public static function markup(array $settings, ?string $nonce = null): string
    {
        if (($settings['domain'] ?? '') === '' || in_array('script_origin', Settings::refusedUrls($settings), true)) {
            return '';
        }

        $options = Settings::toOptions($settings, $nonce);
        $renderer = new SnippetRenderer($options);
        if ($options->routeTemplates) {
            $renderer = $renderer->withRouteTemplate(RouteTemplate::resolve());
        }

        return $renderer->render();
    }
}
