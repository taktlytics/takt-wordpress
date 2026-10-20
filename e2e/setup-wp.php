<?php
// Configures the plugin for the E2E run (idempotent). Run via wp-cli:
//   wp eval-file wp-content/plugins/takt-wordpress/e2e/setup-wp.php
// Omitting `exclude_localhost` keeps localhost tracking on, so the browser
// beacon fires against the test site. The endpoint points at the host where
// e2e/mock-ingest.cjs listens (host.docker.internal from inside the container).

update_option('takt_settings', [
    'domain' => 'localhost',
    'mode' => 'inline',
    'outbound' => true,
    'woocommerce' => true,
    'wc_trigger_status' => 'completed',
    'api_endpoint' => 'https://host.docker.internal:9911',
    'api_key' => 'e2e-key',
]);

echo "SETUP_OK\n";
