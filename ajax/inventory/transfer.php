<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('inventory.adjust');
CSRF::verifyRequest();

$items = $_POST['items'] ?? [];
if (!$items) {
    Response::error('يجب إضافة صنف واحد على الأقل.');
}

$transferNo = trim($_POST['transfer_no']);
$transferDate = date('Y-m-d H:i:s', strtotime($_POST['transfer_date'] ?? 'now'));
$sourceWarehouseId = (int) ($_POST['source_warehouse_id'] ?? 0);
$destinationWarehouseId = (int) ($_POST['destination_warehouse_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if ($sourceWarehouseId <= 0) {
    Response::error('يجب اختيار المخزن المصدر.');
}

if ($destinationWarehouseId <= 0) {
    Response::error('يجب اختيار المخزن المستلم.');
}

if ($sourceWarehouseId === $destinationWarehouseId) {
    Response::error('المخزن المصدر والمخزن المستلم يجب أن يكونا مختلفين.');
}

$preparedItems = [];
$productReservations = [];
$inventoryService = new InventoryService();
foreach ($items as $item) {
    if (empty($item['product_id'])) {
        continue;
    }

    $productId = (int) $item['product_id'];
    $quantity = (float) ($item['quantity'] ?? 0);
    if ($quantity <= 0) {
        Response::error('الكمية يجب أن تكون أكبر من صفر.');
    }

    $productReservations[$productId] = ($productReservations[$productId] ?? 0) + $quantity;
    $availableStock = $inventoryService->availableStockForProduct($productId, $sourceWarehouseId);

    if ($productReservations[$productId] > $availableStock) {
        Response::error('الكمية المتوفرة غير كافية. الرصيد المتوفر: ' . $availableStock);
    }

    $preparedItems[] = [
        'product_id' => $productId,
        'product_unit_id' => !empty($item['product_unit_id']) ? (int) $item['product_unit_id'] : null,
        'quantity' => $quantity,
    ];
}

if (!$preparedItems) {
    Response::error('يجب إضافة أصناف صحيحة.');
}

try {
    $transferId = (new InventoryService())->createTransfer([
        'transfer_no' => $transferNo,
        'transfer_date' => $transferDate,
        'source_warehouse_id' => $sourceWarehouseId,
        'destination_warehouse_id' => $destinationWarehouseId,
        'notes' => $notes,
        'created_by' => Auth::id(),
    ], $preparedItems);

    Response::success('تم نقل المخزون بنجاح.', [
        'id' => $transferId,
        'print_url' => 'api/print-transfer.php?id=' . $transferId
    ]);
} catch (Throwable $e) {
    Response::error('خطأ في التنفيذ: ' . $e->getMessage());
}
