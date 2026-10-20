<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress\Tests;

use PHPUnit\Framework\TestCase;

final class BundledTrackerTest extends TestCase
{
    public function testBundledTrackerRunsInsideItsOwnScope(): void
    {
        $path = dirname(__DIR__) . '/vendor/vskstudio/takt-core-php/resources/takt.auto.js';
        $bundle = file_get_contents($path);

        self::assertIsString($bundle);
        self::assertStringStartsWith("(function(){'use strict';", $bundle);
    }
}
