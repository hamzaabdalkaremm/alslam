<?php
require_once __DIR__ . '/../../config/bootstrap.php';

Auth::requireLogin();
Auth::requirePermission('branches.update');
CSRF::verifyRequest();

$pdo = Database::connection();

$id = (int) ($_POST['id'] ?? 0);

$errors = Validator::validate($_POST, [
    'branch_id' => 'required',
    'code' => 'required',
    'name' => 'required',
]);

if ($errors) {
    Response::error('تحقق من بيانات المخزن.', 422, $errors);
}

$branchId = (int) $_POST['branch_id'];
if (!Auth::canAccessBranch($branchId)) {
    Response::error('لا تملك صلاحية إدارة هذا الفرع.', 403);
}

$warehouseType = trim((string) ($_POST['warehouse_type'] ?? 'main'));
if (!in_array($warehouseType, ['main', 'vehicle'], true)) {
    Response::error('نوع المخزن غير صالح.');
}

$marketerId = !empty($_POST['marketer_id']) ? (int) $_POST['marketer_id'] : null;

if ($warehouseType === 'main') {
    $marketerId = null;
}
$marketerId = !empty($_POST['marketer_id']) ? (int) $_POST['marketer_id'] : null;

if ($warehouseType === 'main') {
    $marketerId = null;
}

if ($marketerId !== null) {
    $stmt = $pdo->prepare("
        SELECT id, full_name, marketer_type, status, deleted_at
        FROM marketers
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $marketerId]);
    $marketer = $stmt->fetch();

    if (!$marketer || !empty($marketer['deleted_at']) || ($marketer['status'] ?? '') !== 'active') {
        Response::error('المسوق المحدد غير صالح.');
    }

    if (($marketer['marketer_type'] ?? '') !== 'marketer') {
        Response::error('لا يمكن تعيين إلا مستخدم من نوع "مسوق" لهذا المخزن.');
    }

    $stmt = $pdo->prepare("
        SELECT id, name
        FROM warehouses
        WHERE marketer_id = :marketer_id
          AND deleted_at IS NULL
          AND id <> :id
        LIMIT 1
    ");
    $stmt->execute([
        'marketer_id' => $marketerId,
        'id' => $id,
    ]);
    $exists = $stmt->fetch();

    if ($exists) {
        Response::error('هذا المسوق مرتبط بالفعل بمخزن آخر، ولا يمكن ربطه بأكثر من مخزن.');
    }
}
if ($marketerId !== null) {
    $stmt = $pdo->prepare("
        SELECT id, full_name, marketer_type, status, deleted_at
        FROM marketers
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $marketerId]);
    $marketer = $stmt->fetch();

    if (!$marketer || !empty($marketer['deleted_at']) || ($marketer['status'] ?? '') !== 'active') {
        Response::error('المسوق المحدد غير صالح.');
    }

    if (($marketer['marketer_type'] ?? '') !== 'marketer') {
        Response::error('لا يمكن تعيين إلا مستخدم من نوع "مسوق" لهذا المخزن.');
    }

    $stmt = $pdo->prepare("
        SELECT id, name
        FROM warehouses
        WHERE marketer_id = :marketer_id
          AND deleted_at IS NULL
          AND id <> :id
        LIMIT 1
    ");
    $stmt->execute([
        'marketer_id' => $marketerId,
        'id' => $id,
    ]);
    $exists = $stmt->fetch();

    if ($exists) {
        Response::error('هذا المسوق مرتبط بالفعل بمخزن آخر، ولا يمكن ربطه بأكثر من مخزن.');
    }
}

$payload = [
    'branch_id' => $branchId,
    'code' => trim((string) $_POST['code']),
    'name' => trim((string) $_POST['name']),
    'warehouse_type' => $warehouseType,
    'marketer_id' => $marketerId,
    'manager_name' => trim((string) ($_POST['manager_name'] ?? '')),
    'address' => trim((string) ($_POST['address'] ?? '')),
    'status' => trim((string) ($_POST['status'] ?? 'active')),
    'notes' => trim((string) ($_POST['notes'] ?? '')),
];

$pdo->beginTransaction();

try {
    $warehouseId = (new CrudService())->save('warehouses', $payload, $id ?: null);

    if ($marketerId !== null) {
        $stmt = $pdo->prepare("
            UPDATE marketers
            SET default_warehouse_id = :warehouse_id
            WHERE id = :marketer_id
        ");
        $stmt->execute([
            'warehouse_id' => $warehouseId,
            'marketer_id' => $marketerId,
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE marketers
            SET default_warehouse_id = NULL
            WHERE default_warehouse_id = :warehouse_id
        ");
        $stmt->execute([
            'warehouse_id' => $warehouseId,
        ]);
    }

    $pdo->commit();

    log_activity(
        'branches',
        $id ? 'update' : 'create',
        $id ? 'تعديل مخزن' : 'إضافة مخزن',
        'warehouses',
        $warehouseId
    );

    Response::success($id ? 'تم تحديث المخزن.' : 'تمت إضافة المخزن.', [
        'id' => $warehouseId,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    Response::error('تعذر حفظ المخزن.');
}