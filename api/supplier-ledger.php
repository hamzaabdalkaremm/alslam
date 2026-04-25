<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin();

$supplierId = (int) ($_GET['supplier_id'] ?? 0);
$stmt = Database::connection()->prepare(
    "SELECT invoice_no, purchase_date AS transaction_date, total_amount, paid_amount, due_amount
     FROM purchases
     WHERE supplier_id = :supplier_id AND deleted_at IS NULL
     ORDER BY purchase_date DESC"
);
$stmt->execute(['supplier_id' => $supplierId]);
Response::json(['status' => 'success', 'data' => $stmt->fetchAll()]);
