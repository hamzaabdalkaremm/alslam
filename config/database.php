<?php

$config = [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'alslamly_database',
    'username' => 'alslamly_database',
    'password' => '.mKnXR9301WB',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        // Native prepares are causing unstable HY093 errors on the current runtime.
        PDO::ATTR_EMULATE_PREPARES => true,
    ],
];

$localConfigFile = __DIR__ . '/database.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require_once $localConfigFile;
    if (is_array($localConfig)) {
        $config = array_replace($config, $localConfig);
    }
}

return $config;
