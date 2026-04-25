<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission(!empty($_POST['id']) ? 'accounts.update' : 'accounts.create');
CSRF::verifyRequest();

$errors = Validator::validate($_POST, ['code' => 'required', 'name' => 'required', 'account_type' => 'required']);
if ($errors) {
    Response::error('تحقق من بيانات الحساب.', 422, $errors);
}

$id = (int) ($_POST['id'] ?? 0);
$parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
$level = 1;
if ($parentId) {
    $stmt = Database::connection()->prepare('SELECT level_no FROM accounts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $parentId]);
    $level = ((int) $stmt->fetchColumn()) + 1;
}

$payload = [
    'company_id' => 1,
    'branch_id' => !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : null,
    'parent_id' => $parentId,
    'code' => trim((string) $_POST['code']),
    'name' => trim((string) $_POST['name']),
    'name_en' => trim((string) ($_POST['name_en'] ?? '')),
    'account_type' => trim((string) $_POST['account_type']),
    'account_group' => trim((string) ($_POST['account_group'] ?? $_POST['account_type'])),
    'level_no' => $level,
    'is_group' => !empty($_POST['is_group']) ? 1 : 0,
    'accepts_entries' => !empty($_POST['accepts_entries']) ? 1 : 0,
    'status' => 'active',
    'notes' => trim((string) ($_POST['notes'] ?? '')),
];

if ($payload['branch_id'] !== null && !Auth::canAccessBranch($payload['branch_id'])) {
    Response::error('لا تملك صلاحية ربط الحساب بهذا الفرع.', 403);
}

try {
    $accountId = (new CrudService())->save('accounts', $payload, $id ?: null);
} catch (Throwable $e) {
    Response::error('تعذر حفظ الحساب.');
}

log_activity('accounts', $id ? 'update' : 'create', $id ? 'تعديل حساب' : 'إضافة حساب', 'accounts', $accountId);
Response::success('تم حفظ الحساب.', ['id' => $accountId]);
