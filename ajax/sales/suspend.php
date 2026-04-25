<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('sales.create');
CSRF::verifyRequest();

$saleId = (int) ($_POST['id'] ?? 0);
if ($saleId <= 0) {
    Response::error('معرف الفاتورة غير صالح.');
}

(new SalesService())->suspend($saleId);
log_activity('sales', 'update', 'تعليق فاتورة بيع', 'sales', $saleId);
Response::success('تم تعليق الفاتورة.');
