SELECT r.name as role_name, p.key as permission_key
FROM roles r
JOIN role_permissions rp ON rp.role_id = r.id
JOIN permissions p ON p.id = rp.permission_id
JOIN users u ON u.role_id = r.id
WHERE u.username = 'admin' OR u.username = 'superadmin'
ORDER BY r.name, p.key;
