<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('accounts.journal');
CSRF::verifyRequest();

$errors = Validator::validate($_POST, ['entry_no' => 'required', 'entry_date' => 'required|date', 'description' => 'required']);
if ($errors) {
    Response::error('تحقق من بيانات القيد.', 422, $errors);
}

try {
    $entryDate = date('Y-m-d H:i:s', strtotime((string) $_POST['entry_date']));
    $branchId = !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : null;
    if ($branchId !== null && !Auth::canAccessBranch($branchId)) {
        Response::error('لا تملك صلاحية إنشاء قيد لهذا الفرع.', 403);
    }

    $journalId = (new AccountingService())->createJournal([
        'branch_id' => $branchId,
        'entry_no' => trim((string) $_POST['entry_no']),
        'entry_date' => $entryDate,
        'description' => trim((string) $_POST['description']),
        'source_type' => 'manual',
        'created_by' => Auth::id(),
        'approved_by' => Auth::id(),
    ], $_POST['lines'] ?? []);
} catch (Throwable $e) {
    Response::error($e->getMessage());
}

log_activity('accounts', 'journal', 'إضافة قيد يومي', 'journal_entries', $journalId);
Response::success('تم ترحيل القيد اليومي.', ['id' => $journalId]);
