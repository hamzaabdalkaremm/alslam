<?php
Auth::requirePermission('users.roles');
$crud = new CrudService();
$editUserId = (int) request_input('edit_user', 0);
$editRoleId = (int) request_input('edit_role', 0);
$editableUser = $editUserId ? $crud->find('users', $editUserId) : null;
$editableRole = $editRoleId ? $crud->find('roles', $editRoleId) : null;
$roles = Database::connection()->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll();
$branches = branches_options();
$permissions = Database::connection()->query('SELECT * FROM permissions ORDER BY module_key ASC, action_key ASC')->fetchAll();
$users = Database::connection()->query(
    "SELECT u.*, r.name AS role_name, b.name_ar AS branch_name
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     LEFT JOIN branches b ON b.id = u.default_branch_id
     WHERE u.deleted_at IS NULL
     ORDER BY u.id DESC"
)->fetchAll();
$userBranchSelections = [];
$userPermissionSelections = [];
$rolePermissionSelections = [];
if ($editableUser) {
    $stmt = Database::connection()->prepare('SELECT branch_id FROM user_branches WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $editableUser['id']]);
    $userBranchSelections = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $stmt = Database::connection()->prepare('SELECT permission_id FROM user_permissions WHERE user_id = :user_id AND is_allowed = 1');
    $stmt->execute(['user_id' => $editableUser['id']]);
    $userPermissionSelections = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
if ($editableRole) {
    $stmt = Database::connection()->prepare('SELECT permission_id FROM user_role_permissions WHERE role_id = :role_id');
    $stmt->execute(['role_id' => $editableRole['id']]);
    $rolePermissionSelections = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
$rolesMap = [];
foreach ($roles as $role) {
    $rolesMap[(int) $role['id']] = $role;
}
$permissionsByModule = [];
foreach ($permissions as $permission) {
    $permissionsByModule[$permission['module_key']][] = $permission;
}
?>
<div class="grid grid-2">
    <div class="card">
        <h3><?= $editableUser ? 'تعديل مستخدم' : 'إضافة مستخدم'; ?></h3>
        <form action="ajax/users/save.php" method="post" data-ajax-form data-reset="false">
            <?= csrf_field(); ?>
            <input type="hidden" name="id" value="<?= e((string) ($editableUser['id'] ?? '')); ?>">
            <div class="form-grid">
                <div><label>الاسم الكامل</label><input name="full_name" required value="<?= e($editableUser['full_name'] ?? ''); ?>"></div>
                <div><label>اسم المستخدم</label><input name="username" required value="<?= e($editableUser['username'] ?? ''); ?>"></div>
                <div><label>المسمى الوظيفي</label><input name="title" value="<?= e($editableUser['title'] ?? ''); ?>"></div>
                <div><label>كلمة المرور</label><input type="password" name="password"></div>
                <div>
                    <label>الدور</label>
                    <select name="role_id">
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= e((string) $role['id']); ?>" <?= (string) ($editableUser['role_id'] ?? '') === (string) $role['id'] ? 'selected' : ''; ?>><?= e($role['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>الفرع الافتراضي</label>
                    <select name="default_branch_id">
                        <option value="">بدون</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= e((string) $branch['id']); ?>" <?= (string) ($editableUser['default_branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : ''; ?>><?= e($branch['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>الهاتف</label><input name="phone" value="<?= e($editableUser['phone'] ?? ''); ?>"></div>
                <div><label>البريد</label><input name="email" value="<?= e($editableUser['email'] ?? ''); ?>"></div>
                <div class="checkbox-grid"><label><input type="checkbox" name="is_active" value="1" <?= !isset($editableUser['is_active']) || (int) $editableUser['is_active'] === 1 ? 'checked' : ''; ?>> مستخدم فعال</label></div>
            </div>
            <div class="mt-2">
                <label>الفروع المسموح بها</label>
                <div class="checkbox-grid">
                    <?php foreach ($branches as $branch): ?>
                        <label><input type="checkbox" name="branch_ids[]" value="<?= e((string) $branch['id']); ?>" <?= in_array((int) $branch['id'], $userBranchSelections, true) ? 'checked' : ''; ?>> <?= e($branch['name_ar']); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mt-2">
                <label>صلاحيات إضافية للمستخدم</label>
                <?php foreach ($permissionsByModule as $moduleKey => $modulePermissions): ?>
                    <div class="mt-1">
                        <strong><?= e($moduleKey); ?></strong>
                        <div class="permission-grid">
                            <?php foreach ($modulePermissions as $permission): ?>
                                <label><input type="checkbox" name="permission_ids[]" value="<?= e((string) $permission['id']); ?>" <?= in_array((int) $permission['id'], $userPermissionSelections, true) ? 'checked' : ''; ?>> <?= e($permission['label']); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-2"><button class="btn btn-primary" type="submit">حفظ المستخدم</button></div>
        </form>
    </div>

    <div class="card">
        <h3><?= $editableRole ? 'تعديل دور وصلاحياته' : 'إضافة دور وصلاحيات'; ?></h3>
        <form action="ajax/users/role-save.php" method="post" data-ajax-form data-reset="false">
            <?= csrf_field(); ?>
            <input type="hidden" name="id" value="<?= e((string) ($editableRole['id'] ?? '')); ?>">
            <div class="form-grid">
                <div><label>اسم الدور</label><input name="name" required value="<?= e($editableRole['name'] ?? ''); ?>"></div>
                <div><label>المعرف الفني</label><input name="slug" required value="<?= e($editableRole['slug'] ?? ''); ?>"></div>
                <div><label>الوصف</label><input name="description" value="<?= e($editableRole['description'] ?? ''); ?>"></div>
            </div>
            <div class="mt-2">
                <label>صلاحيات الدور</label>
                <?php foreach ($permissionsByModule as $moduleKey => $modulePermissions): ?>
                    <div class="mt-1">
                        <strong><?= e($moduleKey); ?></strong>
                        <div class="permission-grid">
                            <?php foreach ($modulePermissions as $permission): ?>
                                <label><input type="checkbox" name="permission_ids[]" value="<?= e((string) $permission['id']); ?>" <?= in_array((int) $permission['id'], $rolePermissionSelections, true) ? 'checked' : ''; ?>> <?= e($permission['label']); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-2"><button class="btn btn-light" type="submit">حفظ الدور</button></div>
        </form>
    </div>
</div>

<div class="card mt-2">
    <h3>الأدوار الحالية</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>الدور</th><th>المعرف الفني</th><th>الوصف</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td><?= e($role['name']); ?></td>
                    <td><?= e($role['slug']); ?></td>
                    <td><?= e($role['description'] ?? '-'); ?></td>
                    <td><a class="btn btn-light" href="index.php?module=users&edit_role=<?= e((string) $role['id']); ?>">تعديل الصلاحيات</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-2">
    <h3>المستخدمون الحاليون</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>الاسم</th><th>المستخدم</th><th>الدور</th><th>الفرع</th><th>آخر دخول</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= e($user['full_name']); ?></td>
                    <td><?= e($user['username']); ?></td>
                    <td><?= e($user['role_name']); ?></td>
                    <td><?= e($user['branch_name'] ?: '-'); ?></td>
                    <td><?= e($user['last_login_at'] ?: '-'); ?></td>
                    <td><span class="badge <?= (int) $user['is_active'] === 1 ? 'success' : 'warning'; ?>"><?= e((int) $user['is_active'] === 1 ? 'فعال' : 'موقوف'); ?></span></td>
                    <td><a class="btn btn-light" href="index.php?module=users&edit_user=<?= e((string) $user['id']); ?>">تعديل</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
