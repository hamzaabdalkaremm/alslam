<?php
require __DIR__ . '/config/bootstrap.php';
$auth = new Auth();

echo "=== USER ===\n";
print_r($auth->user());

echo "\n=== RAW PERMISSIONS ===\n";
$reflection = new ReflectionClass($auth);
$prop = $reflection->getProperty('permissions');
$prop->setAccessible(true);
print_r($prop->getValue($auth));

echo "\n=== ALL PERMISSIONS ARRAY ===\n";
$perms = $auth->permissions();
print_r($perms);

echo "\n=== CHECK sales.create ===\n";
var_dump($auth->can('sales.create'));

echo "\n=== CHECK customers.create ===\n";
var_dump($auth->can('customers.create'));
