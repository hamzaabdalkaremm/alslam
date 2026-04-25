<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
CSRF::verifyRequest();

$id = (int) ($_POST['account_id'] ?? 0);
$amount = (float) ($_POST['amount'] ?? 0);
$entry_type = trim((string) ($_POST['entry_type'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$entry_date = trim((string) ($_POST['entry_date'] ?? date('Y-m-d H:i:s')));

if ($id <= 0) {
    Response::error('الحساب غير محدد.');
}
if ($amount <= 0) {
    Response::error('المبلغ يجب أن يكون أكبر من صفر.');
}
if (!in_array($entry_type, ['receipt', 'payment'])) {
    Response::error('نوع الحركة غير صالح.');
}

try {
    $cashboxService = new CashboxService();
    $entryId = $cashboxService->addEntry([
        'account_id' => $id,
        'entry_type' => $entry_type,
        'amount' => $amount,
        'description' => $description,
        'entry_date' => $entry_date,
        'created_by' => Auth::id(),
        // branch_id and cashbox_id can be null, will be handled by CashboxService
        // or we can try to get default from user's branch
    ]);

    log_activity('accounts', 'quick_entry', "إضافة حركة سريعة للحساب {$id}", 'cashbox_entries', $entryId);
    Response::success('تم إضافة الحركة بنجاح.', ['id' => $entryId]);
} catch (Throwable $e) {
    Response::error('تعذر إضافة الحركة.');
}