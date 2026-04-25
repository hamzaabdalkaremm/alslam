<?php

$config = [
    'name' => 'مجموعة السلام لاستيراد المواد الغدائية',
    'version' => '1.0.0',
    'base_path' => dirname(__DIR__),
    'base_url' => '',
    'timezone' => 'Africa/Tripoli',
    'locale' => 'ar',
    'currency' => 'LYD',
    'debug' => false,
    'log_path' => dirname(__DIR__) . '/storage/logs/app.log',
    'cache_path' => dirname(__DIR__) . '/storage/cache',
    'session_path' => dirname(__DIR__) . '/storage/sessions',
    'session_name' => 'wholesale_erp_session',
    'session_lifetime' => 7200,
    'upload_path' => dirname(__DIR__) . '/assets/uploads',
    'log_path' => dirname(__DIR__) . '/storage/logs/app.log',
    'default_role' => 'cashier',
    'per_page' => 15,
    'dashboard_cache_ttl' => 30,
    'asset_cdn_enabled' => true,
];

$localConfigFile = __DIR__ . '/app.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require_once $localConfigFile;
    if (is_array($localConfig)) {
        $config = array_replace($config, $localConfig);
    }
}

return $config;
