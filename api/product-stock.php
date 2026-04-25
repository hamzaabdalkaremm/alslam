<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin();

$productId = (int) ($_GET['id'] ?? 0);
$warehouseId = (int) ($_GET['warehouse_id'] ?? 0);

if ($productId <= 0) {
    Response::json(['success' => false, 'message' => 'Invalid product ID']);
}

$inventoryService = new InventoryService();
$stock = $inventoryService->stockBalance($productId, 1, 0, null, $warehouseId > 0 ? $warehouseId : null);

if (empty($stock)) {
    Response::json(['success' => false, 'message' => 'Product not found']);
}

Response::json([
    'success' => true,
    'stock_balance' => (float) $stock[0]['stock_balance'],
    'product_name' => $stock[0]['name'],
    'product_code' => $stock[0]['code']
]);