<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('suppliers.delete');
CSRF::verifyRequest();

$id = (int) ($_POST['id'] ?? 0);
(new CrudService())->delete('suppliers', $id);
log_activity('suppliers', 'delete', 'حذف مورد', 'suppliers', $id);
Response::success('تم حذف المورد.');
