<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin();

$search = '%' . trim((string) ($_GET['q'] ?? '')) . '%';
$stmt = Database::connection()->prepare(
    "SELECT DISTINCT p.id, p.code, p.name, p.barcode, p.wholesale_price, p.half_wholesale_price, p.retail_price
     FROM products p
     LEFT JOIN product_units pu ON pu.product_id = p.id
     WHERE p.deleted_at IS NULL
       AND p.is_active = 1
       AND (
            p.name LIKE :search
            OR p.code LIKE :search
            OR p.barcode LIKE :search
            OR pu.barcode LIKE :search
       )
     ORDER BY p.name ASC
     LIMIT 20"
);
$stmt->execute(['search' => $search]);
Response::json(['status' => 'success', 'data' => $stmt->fetchAll()]);
