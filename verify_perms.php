<?php
require __DIR__ . '/config/bootstrap.php';
$auth = new Auth();

if (!$auth->check()) {
    die("Not logged in");
}

echo "User: " . $auth->user()['username'] . "\n";
echo "Role: " . ($auth->user()['role_name'] ?? 'no role') . "\n";
echo "Is Super Admin: " . ($auth->isSuperAdmin() ? 'YES' : 'NO') . "\n\n";

echo "Can sales.create: " . ($auth->can('sales.create') ? 'YES' : 'NO') . "\n";
echo "Can customers.create: " . ($auth->can('customers.create') ? 'YES' : 'NO') . "\n";
echo "Can inventory.adjust: " . ($auth->can('inventory.adjust') ? 'YES' : 'NO') . "\n";
echo "Can settings.update: " . ($auth->can('settings.update') ? 'YES' : 'NO') . "\n";
