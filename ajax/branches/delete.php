<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('branches.delete');
CSRF::verifyRequest();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    Response::error('الفرع غير صالح.');
}

if (!Auth::isSuperAdmin()) {
    Response::error('تعطيل الفروع متاح للمسؤول الرئيسي فقط.', 403);
}

$stmt = Database::connection()->prepare("UPDATE branches SET status = 'inactive', deleted_at = NOW() WHERE id = :id");
$stmt->execute(['id' => $id]);

log_activity('branches', 'delete', 'تعطيل فرع', 'branches', $id);
Response::success('تم تعطيل الفرع.');
