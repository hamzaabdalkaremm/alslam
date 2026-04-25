<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('inventory.adjust');
CSRF::verifyRequest();

if (strtolower((string) (Auth::user()['username'] ?? '')) !== 'admin') {
    Response::error('صفحة التالف متاحة فقط للمستخدم admin.');
}

$productId = (int) ($_POST['product_id'] ?? 0);
$warehouseId = (int) ($_POST['warehouse_id'] ?? 0);
$quantity = (float) ($_POST['quantity'] ?? 0);
$damageDateInput = trim((string) ($_POST['damage_date'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($productId <= 0) {
    Response::error('يجب اختيار الصنف.');
}

if ($warehouseId <= 0) {
    Response::error('يجب اختيار المخزن.');
}

if ($quantity <= 0) {
    Response::error('الكمية يجب أن تكون أكبر من صفر.');
}

if ($damageDateInput === '') {
    Response::error('يجب تحديد تاريخ التلف.');
}

$warehouseStmt = Database::connection()->prepare(
    'SELECT id, branch_id
     FROM warehouses
     WHERE id = :id AND deleted_at IS NULL
     LIMIT 1'
);
$warehouseStmt->execute(['id' => $warehouseId]);
$warehouse = $warehouseStmt->fetch();

if (!$warehouse) {
    Response::error('المخزن المحدد غير موجود.');
}

if (!Auth::isSuperAdmin() && !in_array((int) $warehouse['branch_id'], Auth::branchIds(), true)) {
    Response::error('ليس لديك صلاحية على هذا المخزن.');
}

try {
    $movementId = (new InventoryService())->createDamage([
        'product_id' => $productId,
        'warehouse_id' => $warehouseId,
        'quantity' => $quantity,
        'damage_date' => date('Y-m-d H:i:s', strtotime($damageDateInput)),
        'notes' => $notes,
        'created_by' => Auth::id(),
    ]);

    Response::success('تم تسجيل التالف بنجاح.', ['id' => $movementId]);
} catch (Throwable $e) {
    Response::error('تعذر تسجيل التالف: ' . $e->getMessage());
}
