<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('expenses.create');
CSRF::verifyRequest();

$errors = Validator::validate($_POST, ['title' => 'required', 'amount' => 'required|numeric', 'expense_date' => 'required|date']);
if ($errors) {
    Response::error('تحقق من بيانات المصروف.', 422, $errors);
}

$payload = [
    'branch_id' => !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : Auth::defaultBranchId(),
    'expense_category_id' => $_POST['expense_category_id'] !== '' ? (int) $_POST['expense_category_id'] : null,
    'account_id' => $_POST['account_id'] !== '' ? (int) $_POST['account_id'] : null,
    'reference_no' => next_reference('payment_prefix', 'PAY'),
    'expense_date' => $_POST['expense_date'],
    'title' => trim($_POST['title']),
    'amount' => (float) $_POST['amount'],
    'payment_method' => trim($_POST['payment_method'] ?? 'cash'),
    'status' => 'approved',
    'approved_by' => Auth::id(),
    'approved_at' => now_datetime(),
    'notes' => trim($_POST['notes'] ?? ''),
    'created_by' => Auth::id(),
];

if (!Auth::canAccessBranch($payload['branch_id'])) {
    Response::error('لا تملك صلاحية تسجيل مصروف لهذا الفرع.', 403);
}

$expenseId = (new CrudService())->save('expenses', $payload);
(new CashboxService())->addEntry([
    'branch_id' => $payload['branch_id'],
    'entry_type' => 'payment',
    'reference_type' => 'expense',
    'reference_id' => $expenseId,
    'entry_date' => $payload['expense_date'] . ' 12:00:00',
    'amount' => $payload['amount'],
    'description' => 'مصروف: ' . $payload['title'],
    'created_by' => Auth::id(),
]);
(new AccountingService())->autoPostExpense($expenseId, $payload);
log_activity('expenses', 'create', 'إضافة مصروف', 'expenses', $expenseId);
Response::success('تم حفظ المصروف.');
