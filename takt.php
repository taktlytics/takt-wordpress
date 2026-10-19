<?php

/**
 * Plugin Name:       Takt Analytics
 * Plugin URI:        https://github.com/taktlytics/takt-wordpress
 * Description:       Privacy-first analytics for WordPress: injects the Takt browser snippet and reports WooCommerce purchases as server-to-server events.
 * Version:           0.4.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            vskstudio
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       takt-analytics
 *
 * @package Vskstudio\Takt\WordPress
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('TAKT_WP_VERSION', '0.4.0');
define('TAKT_WP_FILE', __FILE__);

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    return;
}
require $autoload;

add_action('plugins_loaded', static function (): void {
    Vskstudio\Takt\WordPress\Plugin::boot();
});
