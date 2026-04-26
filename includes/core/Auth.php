<?php

class Auth
{
    private static ?array $user = null;
    private static array $permissions = [];
    private static array $branchIds = [];

    public static function boot(): void
    {
        $userId = Session::get('auth_user_id');
        if (!$userId || !self::canQueryUsers()) {
            return;
        }

        $userFilters = ['users.id = :id'];
        if (schema_has_column('users', 'deleted_at')) {
            $userFilters[] = 'users.deleted_at IS NULL';
        }
        if (schema_has_column('users', 'is_active')) {
            $userFilters[] = 'users.is_active = 1';
        }

        $sql = self::baseUserSelect() . '
                WHERE ' . implode(' AND ', $userFilters) . '
                LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $userId]);
        self::$user = $stmt->fetch() ?: null;

        if (self::$user) {
            self::$permissions = Session::get('auth_permissions', []);
            self::$branchIds = Session::get('auth_branch_ids', []);
            if (!self::$permissions) {
                self::$permissions = self::loadPermissions((int) (self::$user['role_id'] ?? 0), (int) self::$user['id']);
                Session::put('auth_permissions', self::$permissions);
            }
            if (!self::$branchIds) {
                self::$branchIds = self::loadBranchIds((int) self::$user['id'], !empty(self::$user['default_branch_id']) ? (int) self::$user['default_branch_id'] : null);
                Session::put('auth_branch_ids', self::$branchIds);
            }
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        if (!self::canQueryUsers() || !schema_has_column('users', 'username') || !schema_has_column('users', 'password_hash')) {
            return false;
        }

        $userFilters = ['users.username = :username'];
        if (schema_has_column('users', 'deleted_at')) {
            $userFilters[] = 'users.deleted_at IS NULL';
        }
        if (schema_has_column('users', 'is_active')) {
            $userFilters[] = 'users.is_active = 1';
        }

        $stmt = Database::connection()->prepare(
            self::baseUserSelect() . '
             WHERE ' . implode(' AND ', $userFilters) . '
             LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        Session::regenerate();
        Session::put('auth_user_id', (int) $user['id']);
        self::$user = $user;
        self::$permissions = self::loadPermissions((int) ($user['role_id'] ?? 0), (int) $user['id']);
        self::$branchIds = self::loadBranchIds((int) $user['id'], !empty($user['default_branch_id']) ? (int) $user['default_branch_id'] : null);
        Session::put('auth_permissions', self::$permissions);
        Session::put('auth_branch_ids', self::$branchIds);

        if (schema_has_column('users', 'last_login_at')) {
            $update = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
            $update->execute(['id' => $user['id']]);
        }
        self::persistSession((int) $user['id']);

        log_activity('auth', 'login', 'تسجيل دخول المستخدم ' . ($user['full_name'] ?? $user['username']), 'users', (int) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            self::removePersistedSession();
            log_activity('auth', 'logout', 'تسجيل خروج المستخدم ' . (self::user()['full_name'] ?? ''), 'users', self::id());
        }

        self::$user = null;
        self::$permissions = [];
        self::$branchIds = [];
        Session::destroy();
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user ? (int) self::$user['id'] : null;
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('login.php');
        }
    }

   public static function can(string $permission): bool
{
    if (!self::check()) {
        return false;
    }

    if (self::isSuperAdmin()) {
        return true;
    }

    return (self::$permissions[$permission] ?? false) === true;
}
    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            http_response_code(403);
            exit('ليس لديك صلاحية للوصول إلى هذه الصفحة.');
        }
    }

    public static function defaultBranchId(): ?int
    {
        if (!self::$user) {
            return null;
        }

        return !empty(self::$user['default_branch_id']) ? (int) self::$user['default_branch_id'] : null;
    }

    public static function branchIds(): array
    {
        return self::$branchIds;
    }

    public static function roleSlug(): ?string
    {
        return self::$user['role_slug'] ?? null;
    }

    public static function isSuperAdmin(): bool
    {
        return self::roleSlug() === 'super_admin';
    }

    public static function canAccessBranch(?int $branchId): bool
    {
        if ($branchId === null || $branchId === 0) {
            return true;
        }

        if (!self::check()) {
            return false;
        }

        if (self::isSuperAdmin()) {
            return true;
        }

        return in_array($branchId, self::$branchIds, true);
    }

    public static function requireBranchAccess(?int $branchId, ?string $message = null): void
    {
        if (self::canAccessBranch($branchId)) {
            return;
        }

        http_response_code(403);
        exit($message ?? 'ليس لديك صلاحية للوصول إلى هذا الفرع.');
    }

    private static function canQueryUsers(): bool
    {
        return schema_table_exists('users');
    }

    private static function baseUserSelect(): string
    {
        $select = ['users.*', 'NULL AS role_slug', 'NULL AS role_name'];
        $join = '';

        if (
            schema_table_exists('roles')
            && schema_has_column('users', 'role_id')
            && schema_has_column('roles', 'id')
        ) {
            $select = [
                'users.*',
                schema_has_column('roles', 'slug') ? 'roles.slug AS role_slug' : 'NULL AS role_slug',
                schema_has_column('roles', 'name') ? 'roles.name AS role_name' : 'NULL AS role_name',
            ];
            $join = ' LEFT JOIN roles ON roles.id = users.role_id';
        }

        return 'SELECT ' . implode(', ', $select) . ' FROM users' . $join;
    }

    private static function persistSession(int $userId): void
    {
        if (!schema_table_exists('auth_sessions')) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO auth_sessions (user_id, session_id, ip_address, user_agent, last_activity_at)
             VALUES (:user_id, :session_id, :ip_address, :user_agent, NOW())
             ON DUPLICATE KEY UPDATE last_activity_at = NOW(), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'session_id' => session_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }

    private static function removePersistedSession(): void
    {
        if (!schema_table_exists('auth_sessions')) {
            return;
        }

        $stmt = Database::connection()->prepare('DELETE FROM auth_sessions WHERE session_id = :session_id');
        $stmt->execute(['session_id' => session_id()]);
    }

    private static function loadPermissions(int $roleId, int $userId): array
{
    if (
        !schema_table_exists('permissions')
        || !schema_has_column('permissions', 'id')
        || !schema_has_column('permissions', 'module_key')
        || !schema_has_column('permissions', 'action_key')
    ) {
        return [];
    }

    $permissions = [];

    $allStmt = Database::connection()->query(
        'SELECT id, CONCAT(module_key, ".", action_key) AS permission_key FROM permissions'
    );

    $allPermissions = $allStmt->fetchAll();

    foreach ($allPermissions as $permission) {
        $permissions[$permission['permission_key']] = false;
    }

    /*
     * الأهم:
     * لو عند المستخدم صلاحيات محفوظة في user_permissions
     * نعتمد عليها فقط ولا نرجع لصلاحيات الدور.
     */
    if (
        schema_table_exists('user_permissions')
        && schema_has_column('user_permissions', 'user_id')
        && schema_has_column('user_permissions', 'permission_id')
        && schema_has_column('user_permissions', 'is_allowed')
    ) {
        $checkStmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM user_permissions WHERE user_id = :user_id'
        );
        $checkStmt->execute(['user_id' => $userId]);

        if ((int) $checkStmt->fetchColumn() > 0) {
            $userStmt = Database::connection()->prepare(
                'SELECT 
                    CONCAT(p.module_key, ".", p.action_key) AS permission_key,
                    up.is_allowed
                 FROM user_permissions up
                 INNER JOIN permissions p ON p.id = up.permission_id
                 WHERE up.user_id = :user_id'
            );

            $userStmt->execute(['user_id' => $userId]);

            foreach ($userStmt->fetchAll() as $row) {
                $permissions[$row['permission_key']] = ((int) $row['is_allowed'] === 1);
            }

            return $permissions;
        }
    }

    /*
     * لو المستخدم ما عنداش صلاحيات خاصة محفوظة،
     * نستخدم صلاحيات الدور.
     */
    if (
        schema_table_exists('role_permissions')
        && schema_has_column('role_permissions', 'role_id')
        && schema_has_column('role_permissions', 'permission_id')
    ) {
        $roleStmt = Database::connection()->prepare(
            'SELECT CONCAT(p.module_key, ".", p.action_key) AS permission_key
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id'
        );

        $roleStmt->execute(['role_id' => $roleId]);

        foreach ($roleStmt->fetchAll() as $row) {
            $permissions[$row['permission_key']] = true;
        }
    }

    return $permissions;
}
    private static function loadBranchIds(int $userId, ?int $defaultBranchId = null): array
    {
        $branchIds = [];

        try {
            if (schema_table_exists('user_branches')) {
                $stmt = Database::connection()->prepare('SELECT branch_id FROM user_branches WHERE user_id = :user_id');
                $stmt->execute(['user_id' => $userId]);
                $branchIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            }
        } catch (Throwable $e) {
            $branchIds = [];
        }

        if ($defaultBranchId !== null && !in_array($defaultBranchId, $branchIds, true)) {
            $branchIds[] = $defaultBranchId;
        }

        return array_values(array_unique(array_filter($branchIds)));
    }
}
