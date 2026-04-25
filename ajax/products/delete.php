<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('products.delete');
CSRF::verifyRequest();

$id = (int) ($_POST['id'] ?? 0);
(new CrudService())->delete('products', $id);
log_activity('products', 'delete', 'حذف منتج', 'products', $id);
Response::success('تم حذف المنتج.');
