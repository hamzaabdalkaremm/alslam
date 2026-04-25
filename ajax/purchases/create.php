<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('purchases.create');
CSRF::verifyRequest();

$items = $_POST['items'] ?? [];
if (!$items) {
    Response::error('يجب إضافة أصناف للفاتورة.');
}

$preparedItems = [];
foreach ($items as $item) {
    if (empty($item['product_id'])) {
        continue;
    }

    $quantity = (float) ($item['quantity'] ?? 0);
    $cost = (float) ($item['unit_cost'] ?? 0);
    $preparedItems[] = [
        'product_id' => (int) $item['product_id'],
        'product_unit_id' => !empty($item['product_unit_id']) ? (int) $item['product_unit_id'] : null,
        'batch_number' => trim($item['batch_number'] ?? ('B-' . date('His'))),
        'production_date' => !empty($item['production_date']) ? $item['production_date'] : null,
        'expiry_date' => !empty($item['expiry_date']) ? $item['expiry_date'] : null,
        'quantity' => $quantity,
        'unit_cost' => $cost,
        'line_discount' => 0,
        'line_total' => $quantity * $cost,
    ];
}

$branchId = !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : Auth::defaultBranchId();
if (!Auth::canAccessBranch($branchId)) {
    Response::error('لا تملك صلاحية تسجيل مشتريات لهذا الفرع.', 403);
}

$purchaseId = (new PurchaseService())->create([
    'branch_id' => $branchId,
    'warehouse_id' => !empty($_POST['warehouse_id']) ? (int) $_POST['warehouse_id'] : null,
    'invoice_no' => trim($_POST['invoice_no']),
    'supplier_id' => !empty($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : null,
    'purchased_by' => Auth::id(),
    'purchase_date' => !empty($_POST['purchase_date']) ? date('Y-m-d H:i:s', strtotime($_POST['purchase_date'])) : now_datetime(),
    'status' => 'completed',
    'approval_status' => 'approved',
    'subtotal' => (float) ($_POST['subtotal'] ?? 0),
    'discount_value' => (float) ($_POST['discount_value'] ?? 0),
    'tax_value' => 0,
    'import_costs' => (float) ($_POST['import_costs'] ?? 0),
    'total_amount' => (float) ($_POST['total_amount'] ?? 0),
    'paid_amount' => (float) ($_POST['paid_amount'] ?? 0),
    'due_amount' => (float) ($_POST['due_amount'] ?? 0),
    'notes' => trim($_POST['notes'] ?? ''),
], $preparedItems);

Response::success('تم حفظ فاتورة الشراء.', ['id' => $purchaseId]);
