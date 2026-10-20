<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress;

final class RouteTemplate
{
    public const REDACTED = '/[redacted]';

    public const SINGULAR = '/{post_type}/{slug}';

    public const FRONT_PAGE = '/';

    public static function resolve(): string
    {
        $custom = \apply_filters('takt_route_template', null);
        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }
        if (\is_front_page()) {
            return self::FRONT_PAGE;
        }
        if (\is_singular()) {
            return self::SINGULAR;
        }

        return self::REDACTED;
    }
}
