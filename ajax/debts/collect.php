<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('debts.collect');
CSRF::verifyRequest();

$collectionId = (new DebtService())->collect([
    'party_type' => trim($_POST['party_type']),
    'party_id' => (int) $_POST['party_id'],
    'source_type' => trim($_POST['source_type']),
    'source_id' => (int) $_POST['source_id'],
    'payment_date' => date('Y-m-d H:i:s', strtotime($_POST['payment_date'] ?? now_datetime())),
    'amount' => (float) ($_POST['amount'] ?? 0),
    'notes' => trim($_POST['notes'] ?? ''),
    'created_by' => Auth::id(),
]);

Response::success('تم حفظ حركة التحصيل/السداد.', ['id' => $collectionId]);
