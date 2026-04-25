<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('inventory.adjust');
CSRF::verifyRequest();

$items = $_POST['items'] ?? [];
if (!$items) {
    Response::error('يجب إضافة صنف واحد على الأقل.');
}

$preparedItems = [];
foreach ($items as $item) {
    if (empty($item['product_id'])) {
        continue;
    }

    $systemQty = (float) ($item['system_quantity'] ?? 0);
    $actualQty = (float) ($item['actual_quantity'] ?? 0);
    $preparedItems[] = [
        'product_id' => (int) $item['product_id'],
        'warehouse_id' => !empty($item['warehouse_id']) ? (int) $item['warehouse_id'] : null,
        'product_unit_id' => null,
        'batch_id' => !empty($item['batch_id']) ? (int) $item['batch_id'] : null,
        'system_quantity' => $systemQty,
        'actual_quantity' => $actualQty,
        'difference_quantity' => $actualQty - $systemQty,
        'unit_cost' => (float) ($item['unit_cost'] ?? 0),
    ];
}

$adjustmentId = (new InventoryService())->createAdjustment([
    'adjustment_no' => trim($_POST['adjustment_no']),
    'adjustment_date' => date('Y-m-d H:i:s', strtotime($_POST['adjustment_date'])),
    'reason' => trim($_POST['reason']),
    'notes' => trim($_POST['notes'] ?? ''),
    'branch_id' => (int) ($_POST['branch_id'] ?? 0),
    'warehouse_id' => (int) ($_POST['warehouse_id'] ?? 0),
    'created_by' => Auth::id(),
], $preparedItems);

Response::success('تم حفظ التسوية.', ['id' => $adjustmentId]);
