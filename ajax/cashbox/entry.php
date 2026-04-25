<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('cashbox.manage');
CSRF::verifyRequest();

$errors = Validator::validate($_POST, ['entry_date' => 'required|date', 'amount' => 'required|numeric', 'description' => 'required']);
if ($errors) {
    Response::error('تحقق من بيانات الحركة.', 422, $errors);
}

$entryId = (new CashboxService())->addEntry([
    'entry_type' => $_POST['entry_type'] === 'payment' ? 'payment' : 'receipt',
    'reference_type' => 'manual',
    'reference_id' => null,
    'entry_date' => date('Y-m-d H:i:s', strtotime($_POST['entry_date'])),
    'amount' => (float) $_POST['amount'],
    'description' => trim($_POST['description']),
    'created_by' => Auth::id(),
]);
log_activity('cashbox', 'manage', 'تسجيل حركة خزينة', 'cashbox_entries', $entryId);
Response::success('تم تسجيل حركة الخزينة.');
