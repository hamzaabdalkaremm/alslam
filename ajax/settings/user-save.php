<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('users.manage');
CSRF::verifyRequest();

$userId = (int) ($_POST['id'] ?? 0);
$existingUser = $userId > 0 ? (new CrudService())->find('users', $userId) : null;
$isAdminAccount = $existingUser && ($existingUser['username'] ?? '') === 'admin';
$validationRules = ['full_name' => 'required', 'username' => 'required'];

if ($isAdminAccount) {
    $validationRules = ['old_password' => 'required', 'password' => 'required'];
}

if ($userId <= 0) {
    $validationRules['password'] = 'required';
}

$errors = Validator::validate($_POST, $validationRules);
if ($errors) {
    Response::error('تحقق من بيانات المستخدم.', 422, $errors);
}

$username = trim((string) ($_POST['username'] ?? ''));
$duplicateStmt = Database::connection()->prepare(
    'SELECT id FROM users WHERE username = :username AND deleted_at IS NULL AND id <> :id LIMIT 1'
);
$duplicateStmt->execute([
    'username' => $username,
    'id' => $userId,
]);

if ($duplicateStmt->fetch()) {
    Response::error('اسم المستخدم مستخدم بالفعل، اختر اسماً آخر.', 422, ['username' => ['اسم المستخدم مستخدم بالفعل.']]);
}

$password = (string) ($_POST['password'] ?? '');
if ($isAdminAccount) {
    $oldPassword = (string) ($_POST['old_password'] ?? '');
    if (!$existingUser || !password_verify($oldPassword, (string) $existingUser['password_hash'])) {
        Response::error('كلمة المرور القديمة غير صحيحة.', 422, ['old_password' => ['كلمة المرور القديمة غير صحيحة.']]);
    }

    $data = [
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ];
} else {
    $data = [
        'role_id' => (int) ($_POST['role_id'] ?? 0),
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'username' => $username,
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($password !== '') {
        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
}

$savedUserId = (new CrudService())->save('users', $data, $userId > 0 ? $userId : null);

$actionLabel = $userId > 0 ? ($isAdminAccount ? 'تغيير كلمة مرور حساب admin' : 'تعديل بيانات مستخدم') : 'إضافة مستخدم جديد';
$message = $userId > 0 ? ($isAdminAccount ? 'تم تحديث كلمة مرور admin.' : 'تم تحديث المستخدم.') : 'تم إنشاء المستخدم.';

log_activity('users', 'manage', $actionLabel, 'users', $savedUserId);
Response::success($message, ['id' => $savedUserId]);
