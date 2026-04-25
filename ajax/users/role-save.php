<?php
require_once __DIR__ . '/../../config/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('users.roles');
CSRF::verifyRequest();

$roleId = (int) ($_POST['id'] ?? 0);
$errors = Validator::validate($_POST, ['name' => 'required', 'slug' => 'required']);
if ($errors) {
    Response::error('تحقق من بيانات الدور.', 422, $errors);
}

$pdo = Database::connection();
$pdo->beginTransaction();

try {
    $existingRole = null;
    if ($roleId > 0) {
        $stmtRole = $pdo->prepare('SELECT id, is_system FROM roles WHERE id = :id LIMIT 1');
        $stmtRole->execute(['id' => $roleId]);
        $existingRole = $stmtRole->fetch();
        if (!$existingRole) {
            throw new RuntimeException('Role not found');
        }
    }

    $rolePayload = [
        'name' => trim((string) $_POST['name']),
        'slug' => trim((string) $_POST['slug']),
        'description' => trim((string) ($_POST['description'] ?? '')),
    ];

    if ($roleId === 0) {
        $rolePayload['is_system'] = 0;
    }

    $roleId = (new CrudService())->save('roles', $rolePayload, $roleId ?: null);

    $pdo->prepare('DELETE FROM user_role_permissions WHERE role_id = :role_id')->execute(['role_id' => $roleId]);

    $stmt = $pdo->prepare('INSERT INTO user_role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');
    foreach (array_unique(array_map('intval', $_POST['permission_ids'] ?? [])) as $permissionId) {
        if ($permissionId > 0) {
            $stmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    $pdo->commit();
    log_activity('users', 'roles', $roleId > 0 ? 'حفظ صلاحيات الدور' : 'إضافة دور جديد', 'roles', $roleId);
    Response::success('تم حفظ الدور والصلاحيات.');
} catch (Throwable $e) {
    $pdo->rollBack();
    Response::error('تعذر حفظ الدور.');
}
