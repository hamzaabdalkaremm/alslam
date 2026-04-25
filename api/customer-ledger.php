<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin();

$customerId = (int) ($_GET['customer_id'] ?? 0);
$stmt = Database::connection()->prepare(
    "SELECT invoice_no, sale_date AS transaction_date, total_amount, paid_amount, due_amount
     FROM sales
     WHERE customer_id = :customer_id AND deleted_at IS NULL
     ORDER BY sale_date DESC"
);
$stmt->execute(['customer_id' => $customerId]);
Response::json(['status' => 'success', 'data' => $stmt->fetchAll()]);
