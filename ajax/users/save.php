<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('users.roles');
CSRF::verifyRequest();

$userId = (int) ($_POST['id'] ?? 0);
$rules = ['full_name' => 'required', 'username' => 'required', 'role_id' => 'required'];
if ($userId === 0) {
    $rules['password'] = 'required';
}

$errors = Validator::validate($_POST, $rules);
if ($errors) {
    Response::error('تحقق من بيانات المستخدم.', 422, $errors);
}

$username = trim((string) ($_POST['username'] ?? ''));
$duplicateStmt = Database::connection()->prepare(
    'SELECT id FROM users WHERE username = :username AND deleted_at IS NULL AND id <> :id LIMIT 1'
);
$duplicateStmt->execute(['username' => $username, 'id' => $userId]);
if ($duplicateStmt->fetch()) {
    Response::error('اسم المستخدم مستخدم بالفعل.');
}

$payload = [
    'role_id' => (int) $_POST['role_id'],
    'default_branch_id' => !empty($_POST['default_branch_id']) ? (int) $_POST['default_branch_id'] : null,
    'full_name' => trim((string) $_POST['full_name']),
    'username' => $username,
    'title' => trim((string) ($_POST['title'] ?? '')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
];

if ($payload['default_branch_id'] !== null && !Auth::canAccessBranch($payload['default_branch_id'])) {
    Response::error('لا تملك صلاحية تعيين هذا الفرع الافتراضي.', 403);
}

$selectedBranchIds = array_values(array_filter(array_unique(array_map('intval', $_POST['branch_ids'] ?? []))));
foreach ($selectedBranchIds as $branchId) {
    if (!Auth::canAccessBranch($branchId)) {
        Response::error('لا تملك صلاحية إسناد المستخدم إلى أحد الفروع المحددة.', 403);
    }
}

$password = trim((string) ($_POST['password'] ?? ''));
if ($password !== '') {
    $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
}

$pdo = Database::connection();
$pdo->beginTransaction();

try {
    $savedUserId = (new CrudService())->save('users', $payload, $userId ?: null);

    $pdo->prepare('DELETE FROM user_branches WHERE user_id = :user_id')->execute(['user_id' => $savedUserId]);
    $branchStmt = $pdo->prepare('INSERT INTO user_branches (user_id, branch_id) VALUES (:user_id, :branch_id)');
    foreach ($selectedBranchIds as $branchId) {
        if ($branchId > 0) {
            $branchStmt->execute(['user_id' => $savedUserId, 'branch_id' => $branchId]);
        }
    }

    $pdo->prepare('DELETE FROM user_permissions WHERE user_id = :user_id')->execute(['user_id' => $savedUserId]);
    $permissionStmt = $pdo->prepare('INSERT INTO user_permissions (user_id, permission_id, is_allowed) VALUES (:user_id, :permission_id, 1)');
    foreach (array_unique(array_map('intval', $_POST['permission_ids'] ?? [])) as $permissionId) {
        if ($permissionId > 0) {
            $permissionStmt->execute(['user_id' => $savedUserId, 'permission_id' => $permissionId]);
        }
    }

    $pdo->commit();
    log_activity('users', 'manage', $userId ? 'تعديل مستخدم' : 'إضافة مستخدم', 'users', $savedUserId);
    Response::success('تم حفظ المستخدم.', ['id' => $savedUserId]);
} catch (Throwable $e) {
    $pdo->rollBack();
    Response::error('تعذر حفظ المستخدم.');
}
