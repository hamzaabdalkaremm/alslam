<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('purchases.return');
CSRF::verifyRequest();

$items = $_POST['items'] ?? [];
if (!$items) {
    Response::error('يجب إضافة عناصر المرتجع.');
}

$preparedItems = [];
$subtotal = 0;
foreach ($items as $item) {
    if (empty($item['product_id'])) {
        continue;
    }

    $lineTotal = (float) ($item['line_total'] ?? 0);
    $subtotal += $lineTotal;
    $preparedItems[] = [
        'purchase_item_id' => (int) ($item['purchase_item_id'] ?? 0),
        'product_id' => (int) $item['product_id'],
        'product_unit_id' => !empty($item['product_unit_id']) ? (int) $item['product_unit_id'] : null,
        'quantity' => (float) ($item['quantity'] ?? 0),
        'unit_cost' => (float) ($item['unit_cost'] ?? 0),
        'line_total' => $lineTotal,
    ];
}

$returnId = (new PurchaseService())->createReturn([
    'purchase_id' => (int) $_POST['purchase_id'],
    'supplier_id' => !empty($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : null,
    'return_no' => trim($_POST['return_no']),
    'return_date' => now_datetime(),
    'subtotal' => $subtotal,
    'notes' => trim($_POST['notes'] ?? ''),
    'created_by' => Auth::id(),
], $preparedItems);

Response::success('تم حفظ مرتجع الشراء.', ['id' => $returnId]);
