<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireLogin();

$productId = (int) ($_GET['id'] ?? 0);
$warehouseId = (int) ($_GET['warehouse_id'] ?? 0);
$warehouseFilter = $warehouseId > 0 ? $warehouseId : null;

if ($productId <= 0) {
    Response::json(['success' => false, 'message' => 'Invalid product ID'], 422);
}

$inventoryService = new InventoryService();
$productRows = $inventoryService->stockBalance($productId, 1, 0, null, $warehouseFilter);

if (empty($productRows)) {
    Response::json(['success' => false, 'message' => 'Product not found'], 404);
}

Response::json([
    'success' => true,
    'product' => [
        'id' => (int) $productRows[0]['id'],
        'name' => $productRows[0]['name'],
        'code' => $productRows[0]['code'],
        'stock_balance' => (float) $productRows[0]['stock_balance'],
    ],
    'stock_card' => $inventoryService->stockCard($productId, $warehouseFilter),
]);
