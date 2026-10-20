<?php

update_option('takt_settings', [
    'domain' => 'localhost',
    'mode' => 'sdk',
    'route_templates' => true,
    'api_endpoint' => 'https://host.docker.internal:9911',
    'api_key' => 'e2e-key',
]);

echo "SETUP_OK\n";
