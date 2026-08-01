<?php

$categoryUrls = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('LCD_PHONE_CATEGORY_URLS', 'https://lcd-phone.com/fr/49-accessoires'))
)));

return [
    'serverless' => [
        'enabled' => filter_var(env('SUPPLIER_SERVERLESS', false), FILTER_VALIDATE_BOOL),
        'discovery_pages' => max(1, (int) env('SUPPLIER_DISCOVERY_PAGES', 1)),
        'discovery_products' => max(1, (int) env('SUPPLIER_DISCOVERY_PRODUCTS', 5)),
        'catalog_nodes' => max(1, (int) env('SUPPLIER_CATALOG_NODES', 1)),
        'catalog_pages' => max(1, (int) env('SUPPLIER_CATALOG_PAGES', 1)),
        'catalog_products' => max(1, (int) env('SUPPLIER_CATALOG_PRODUCTS', 3)),
    ],
    'lcd_phone' => [
        'base_url' => env('LCD_PHONE_BASE_URL', 'https://lcd-phone.com'),
        'login_url' => env('LCD_PHONE_LOGIN_URL', 'https://lcd-phone.com/fr/connexion'),
        'email' => env('LCD_PHONE_EMAIL'),
        'password' => env('LCD_PHONE_PASSWORD'),
        'category_urls' => $categoryUrls,
        'timeout' => (int) env('LCD_PHONE_TIMEOUT', 30),
        'delay_ms' => (int) env('LCD_PHONE_DELAY_MS', 1000),
        'sync_url_limit' => (int) env('LCD_PHONE_SYNC_URL_LIMIT', 100),
        'auto_sync_exact' => filter_var(env('LCD_PHONE_AUTO_SYNC_EXACT', true), FILTER_VALIDATE_BOOL),
    ],
];
