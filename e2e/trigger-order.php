<?php
// Run via wp-cli (`wp eval-file`) inside the WordPress container: creates a
// product, builds an order and moves it to "completed" so the plugin's
// woocommerce_order_status_changed handler fires a server-to-server Purchase.

if (!function_exists('wc_create_order')) {
    fwrite(STDERR, "WooCommerce is not active\n");
    exit(1);
}

add_filter('http_request_args', static fn (array $args): array => array_merge($args, ['sslverify' => false]));

$product = new WC_Product_Simple();
$product->set_name('E2E Widget');
$product->set_regular_price('49.95');
$product->set_status('publish');
$product->save();

$order = wc_create_order();
$order->add_product(wc_get_product($product->get_id()), 2);
$order->set_currency('EUR');
$order->set_customer_ip_address('203.0.113.9');
$order->set_customer_user_agent('E2E-Agent/1.0');
$order->calculate_totals();
$order->save();
$order->update_status('completed');

echo 'ORDER_ID=' . $order->get_id() . "\n";
