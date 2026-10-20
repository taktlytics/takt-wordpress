<?php

update_option('permalink_structure', '/index.php/%postname%/');
flush_rewrite_rules(true);

update_option('takt_settings', [
    'domain' => 'localhost',
    'mode' => 'sdk',
    'route_templates' => true,
    'api_endpoint' => 'https://host.docker.internal:9911',
    'api_key' => 'e2e-key',
]);

if (get_page_by_path('takt-e2e-article', OBJECT, 'post') === null) {
    wp_insert_post([
        'post_title' => 'Takt E2E article',
        'post_name' => 'takt-e2e-article',
        'post_status' => 'publish',
    ]);
}

echo "SETUP_OK\n";
