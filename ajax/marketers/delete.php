<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('marketers.delete');
CSRF::verifyRequest();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    Response::error('المسوق غير صالح.');
}

$stmt = Database::connection()->prepare("UPDATE marketers SET status = 'inactive', deleted_at = NOW() WHERE id = :id");
$stmt->execute(['id' => $id]);

log_activity('marketers', 'delete', 'تعطيل مسوق', 'marketers', $id);
Response::success('تم تعطيل المسوق.');
