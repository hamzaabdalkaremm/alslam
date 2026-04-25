<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('customers.delete');
CSRF::verifyRequest();

$id = (int) ($_POST['id'] ?? 0);
(new CrudService())->delete('customers', $id);
log_activity('customers', 'delete', 'حذف عميل', 'customers', $id);
Response::success('تم حذف العميل.');
