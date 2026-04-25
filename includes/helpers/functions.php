<?php

function app_config(?string $key = null): mixed
{
    static $config;
    if ($config === null) {
        global $appConfig;
        $config = is_array($appConfig ?? null) ? $appConfig : require __DIR__ . '/../../config/app.php';
    }
    return $key ? ($config[$key] ?? null) : $config;
}

function db_config(?string $key = null): mixed
{
    static $config;
    if ($config === null) {
        global $dbConfig;
        $config = is_array($dbConfig ?? null) ? $dbConfig : require __DIR__ . '/../../config/database.php';
    }
    return $key ? ($config[$key] ?? null) : $config;
}

function modules_config(?string $key = null): mixed
{
    static $config;
    if ($config === null) {
        global $moduleConfig;
        $config = is_array($moduleConfig ?? null) ? $moduleConfig : require __DIR__ . '/../../config/modules.php';
    }
    return $key ? ($config[$key] ?? null) : $config;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(CSRF::token()) . '">';
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        Session::put('_flash_' . $key, $message);
        return null;
    }

    $value = Session::get('_flash_' . $key);
    Session::forget('_flash_' . $key);
    return $value;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function request_input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function format_number(float|int|string|null $value, int $maxDecimals = 2, bool $useThousandsSeparator = true): string
{
    if ($value === null || $value === '') {
        return '0';
    }

    $number = (float) $value;
    $formatted = number_format(
        $number,
        max(0, $maxDecimals),
        '.',
        $useThousandsSeparator ? ',' : ''
    );

    if (str_contains($formatted, '.')) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }

    if ($formatted === '' || $formatted === '-0') {
        return '0';
    }

    return $formatted;
}

function format_input_number(float|int|string|null $value, int $maxDecimals = 2): string
{
    return format_number($value, $maxDecimals, false);
}

function format_currency(float|int|string $amount): string
{
    return format_number($amount, 2) . ' ' . setting('currency', app_config('currency'));
}

function current_module(): string
{
    return request_input('module', 'dashboard');
}

function asset(string $path): string
{
    $relativePath = 'assets/' . ltrim($path, '/');
    $absolutePath = __DIR__ . '/../../' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($absolutePath)) {
        return $relativePath;
    }

    return $relativePath . '?v=' . filemtime($absolutePath);
}

function asset_exists(string $path): bool
{
    $relativePath = 'assets/' . ltrim($path, '/');
    $absolutePath = __DIR__ . '/../../' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    return is_file($absolutePath);
}

function vendor_asset(string $path, string $cdnUrl = ''): string
{
    if (asset_exists($path)) {
        return asset($path);
    }

    if ((bool) app_config('asset_cdn_enabled') && $cdnUrl !== '') {
        return $cdnUrl;
    }

    return '';
}

function selected_module_class(string $key): string
{
    return current_module() === $key ? 'active' : '';
}

function paginate(int $page, int $perPage): array
{
    $page = max(1, $page);
    return ['limit' => $perPage, 'offset' => ($page - 1) * $perPage, 'page' => $page];
}

function render_pagination(array $pageData, int $page, int $perPage, array $query = []): string
{
    $total = (int) ($pageData['total'] ?? 0);
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $totalPages = max(1, (int) ceil($total / $perPage));

    if ($totalPages <= 1) {
        return '';
    }

    $start = (($page - 1) * $perPage) + 1;
    $end = min($total, $page * $perPage);
    $query = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');

    $buildUrl = static function (int $targetPage) use ($query): string {
        $params = array_merge($query, ['page' => $targetPage]);
        return 'index.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    };

    $windowStart = max(1, $page - 2);
    $windowEnd = min($totalPages, $page + 2);
    $links = [];

    $previousClass = $page <= 1 ? 'pagination-link is-disabled' : 'pagination-link';
    $links[] = '<a class="' . $previousClass . '" href="' . e($page <= 1 ? '#' : $buildUrl($page - 1)) . '"' . ($page <= 1 ? ' aria-disabled="true"' : '') . '>السابق</a>';

    if ($windowStart > 1) {
        $links[] = '<a class="pagination-link" href="' . e($buildUrl(1)) . '">1</a>';
        if ($windowStart > 2) {
            $links[] = '<span class="pagination-ellipsis">...</span>';
        }
    }

    for ($targetPage = $windowStart; $targetPage <= $windowEnd; $targetPage++) {
        $className = $targetPage === $page ? 'pagination-link active' : 'pagination-link';
        $links[] = '<a class="' . $className . '" href="' . e($buildUrl($targetPage)) . '">' . e((string) $targetPage) . '</a>';
    }

    if ($windowEnd < $totalPages) {
        if ($windowEnd < $totalPages - 1) {
            $links[] = '<span class="pagination-ellipsis">...</span>';
        }
        $links[] = '<a class="pagination-link" href="' . e($buildUrl($totalPages)) . '">' . e((string) $totalPages) . '</a>';
    }

    $nextClass = $page >= $totalPages ? 'pagination-link is-disabled' : 'pagination-link';
    $links[] = '<a class="' . $nextClass . '" href="' . e($page >= $totalPages ? '#' : $buildUrl($page + 1)) . '"' . ($page >= $totalPages ? ' aria-disabled="true"' : '') . '>التالي</a>';

    return
        '<div class="pagination-bar">' .
            '<div class="pagination-summary">عرض ' . e((string) $start) . ' - ' . e((string) $end) . ' من ' . e((string) $total) . '</div>' .
            '<nav class="pagination-links" aria-label="Pagination">' . implode('', $links) . '</nav>' .
        '</div>';
}

function now_datetime(): string
{
    return date('Y-m-d H:i:s');
}

function schema_table_exists(string $table): bool
{
    static $cache = [];
    $cacheKey = strtolower($table);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $pdo = Database::connection();
        $tableSql = $pdo->quote($table);
        $sql = "SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = {$tableSql}";
        $cache[$cacheKey] = (int) $pdo->query($sql)->fetchColumn() > 0;
    } catch (Throwable $e) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function schema_has_column(string $table, string $column): bool
{
    static $cache = [];
    $cacheKey = strtolower($table . '.' . $column);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $pdo = Database::connection();
        $tableSql = $pdo->quote($table);
        $columnSql = $pdo->quote($column);
        $sql = "SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = {$tableSql}
                  AND COLUMN_NAME = {$columnSql}";
        $cache[$cacheKey] = (int) $pdo->query($sql)->fetchColumn() > 0;
    } catch (Throwable $e) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function modules_for_sidebar(): array
{
    $modules = modules_config();
    if (!is_array($modules)) {
        return [];
    }

    return array_filter($modules, static function (array $module): bool {
        return !empty($module['permission']) && Auth::can($module['permission']);
    });
}

function module_icon_svg(string $moduleKey): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13h4v7H4zM10 4h4v16h-4zM16 9h4v11h-4z"/></svg>',
        'branches' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V6l8-3 8 3v14M9 20v-4h6v4M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01"/></svg>',
        'marketers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 14V9l10-4v14L4 15zM14 10h3a3 3 0 0 1 0 6h-3M6 15l1.5 4h3L9 14.4"/></svg>',
        'products' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 7l8 4 8-4-8-4zM4 7v10l8 4 8-4V7M12 11v10"/></svg>',
        'inventory' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18v10H3zM7 7V5h10v2M9 12h6"/></svg>',
        'sales' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h13l-1.5 7h-9zM6 7 5 4H3M9 20a1 1 0 1 0 0-.01M17 20a1 1 0 1 0 0-.01"/></svg>',
        'purchases' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h2l2.4 9h9.8L20 8H7M10 18h8M10 21h8"/></svg>',
        'customers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM17 12a2.5 2.5 0 1 0 0-5M4 19a5 5 0 0 1 10 0M14 19a4 4 0 0 1 6 0"/></svg>',
        'suppliers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v8H3zM14 10h3l3 3v2h-6M7 18a1.5 1.5 0 1 0 0-.01M17 18a1.5 1.5 0 1 0 0-.01"/></svg>',
        'debts' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10l3 3v13H7zM17 4v4h4M10 11h6M10 15h4"/></svg>',
        'expenses' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h16v8H4zM8 12h8M7 16h10"/></svg>',
        'cashbox' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v10H4zM8 7V5h8v2M12 11a2 2 0 1 0 0 4"/></svg>',
        'accounts' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 9h14M7 5h2v4H7zM15 5h2v4h-2zM7 15h2v4H7zM15 15h2v4h-2z"/></svg>',
        'returns' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7H5v4M5 11a7 7 0 1 0 2-5M15 17h4v-4"/></svg>',
        'reports' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9h-9zM13 3.1A9 9 0 0 1 20.9 11H13z"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 19a6 6 0 0 1 12 0M18 4l2 2-4 4-2-2z"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zM4 13v-2l2-1 .5-1.2-.8-2L7.8 5l2 .8L11 5h2l1.2.8 2-.8 2.1 1.8-.8 2L18 10l2 1v2l-2 1-.5 1.2.8 2-2.1 1.8-2-.8L13 19h-2l-1.2-.8-2 .8-2.1-1.8.8-2L6 14z"/></svg>',
    ];

    return $icons[$moduleKey] ?? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M12 5v14"/></svg>';
}

function settings_map(bool $refresh = false): array
{
    static $settings = null;

    if ($refresh || $settings === null) {
        $settings = [];

        try {
            $rows = Database::connection()->query('SELECT setting_key, setting_value FROM store_settings')->fetchAll();
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $settings = [];
        }
    }

    return $settings;
}

function setting(string $key, mixed $default = null): mixed
{
    $settings = settings_map();
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function company_profile(bool $refresh = false): array
{
    static $profile = null;

    if ($refresh || $profile === null) {
        $profile = [
            'name' => setting('company_name', app_config('name')),
            'name_en' => setting('company_name_en', ''),
            'phone' => setting('company_phone', ''),
            'email' => setting('company_email', ''),
            'address' => setting('company_address', ''),
            'commercial_register' => setting('company_register', ''),
            'tax_number' => setting('company_tax_number', ''),
            'invoice_footer' => setting('invoice_footer', ''),
            'logo_path' => setting('logo_path', ''),
            'stamp_path' => setting('stamp_path', ''),
        ];

        try {
            $stmt = Database::connection()->query('SELECT * FROM companies ORDER BY id ASC LIMIT 1');
            $company = $stmt->fetch();
            if ($company) {
                $profile = array_merge($profile, [
                    'name' => $company['name_ar'] ?: $profile['name'],
                    'name_en' => $company['name_en'] ?: $profile['name_en'],
                    'phone' => $company['phone'] ?: $profile['phone'],
                    'email' => $company['email'] ?: $profile['email'],
                    'address' => $company['address'] ?: $profile['address'],
                    'commercial_register' => $company['commercial_register'] ?: $profile['commercial_register'],
                    'tax_number' => $company['tax_number'] ?: $profile['tax_number'],
                    'invoice_footer' => $company['invoice_footer'] ?: $profile['invoice_footer'],
                    'logo_path' => $company['logo_path'] ?: $profile['logo_path'],
                    'stamp_path' => $company['stamp_path'] ?: $profile['stamp_path'],
                ]);
            }
        } catch (Throwable $e) {
        }
    }

    return $profile;
}

function company_logo_url(): string
{
    $path = (string) company_profile()['logo_path'];
    if ($path === '') {
        return '';
    }

    return str_starts_with($path, 'assets/') ? $path : ltrim($path, '/');
}

function branches_options(): array
{
    try {
        $sql = "SELECT id, code, name_ar, city, status
                FROM branches
                WHERE deleted_at IS NULL";
        $params = [];

        if (Auth::check() && !Auth::isSuperAdmin()) {
            $branchIds = Auth::branchIds();
            if (!$branchIds) {
                return [];
            }

            $placeholders = [];
            foreach ($branchIds as $index => $branchId) {
                $placeholder = ':branch_' . $index;
                $placeholders[] = $placeholder;
                $params['branch_' . $index] = (int) $branchId;
            }

            $sql .= ' AND id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY name_ar ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function warehouses_options(?int $branchId = null): array
{
    try {
        if ($branchId && !Auth::canAccessBranch($branchId)) {
            return [];
        }

        $sql = "SELECT id, branch_id, code, name, status
                       , manager_name
                FROM warehouses
                WHERE deleted_at IS NULL";
        $params = [];

        if ($branchId) {
            $sql .= ' AND branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY name ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function marketers_options(): array
{
    try {
        if (!schema_table_exists('marketers')) {
            return [];
        }

        $select = [
            'id',
            schema_has_column('marketers', 'code') ? 'code' : "'' AS code",
            schema_has_column('marketers', 'full_name') ? 'full_name' : "'' AS full_name",
            schema_has_column('marketers', 'status') ? 'status' : "'active' AS status",
            schema_has_column('marketers', 'marketer_type') ? 'marketer_type' : "'' AS marketer_type",
            schema_has_column('marketers', 'default_warehouse_id') ? 'default_warehouse_id' : 'NULL AS default_warehouse_id',
        ];

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM marketers';
        $where = [];

        if (schema_has_column('marketers', 'deleted_at')) {
            $where[] = 'deleted_at IS NULL';
        }
        if (schema_has_column('marketers', 'status')) {
            $where[] = "status = 'active'";
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= schema_has_column('marketers', 'full_name') ? ' ORDER BY full_name ASC' : ' ORDER BY id DESC';
        return Database::connection()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function accounts_options(bool $acceptsEntriesOnly = true): array
{
    try {
        $sql = "SELECT id, code, name, account_type, accepts_entries
                FROM accounts
                WHERE deleted_at IS NULL AND status = 'active'";
        if ($acceptsEntriesOnly) {
            $sql .= ' AND accepts_entries = 1';
        }
        $sql .= ' ORDER BY code ASC';

        return Database::connection()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function next_reference(string $prefixSettingKey, string $fallbackPrefix): string
{
    $prefix = trim((string) setting($prefixSettingKey, $fallbackPrefix));
    return $prefix . '-' . date('YmdHis') . random_int(10, 99);
}

function next_product_code(): string
{
    try {
        $rows = Database::connection()->query(
            "SELECT code
             FROM products
             WHERE deleted_at IS NULL"
        )->fetchAll(PDO::FETCH_COLUMN);

        $maxCode = 0;
        foreach ($rows as $code) {
            $trimmed = trim((string) $code);
            if ($trimmed !== '' && ctype_digit($trimmed)) {
                $maxCode = max($maxCode, (int) $trimmed);
            }
        }

        return str_pad((string) ($maxCode + 1), 5, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        return '00001';
    }
}

function next_marketer_code(): string
{
    try {
        $rows = Database::connection()->query(
            "SELECT code
             FROM marketers
             WHERE deleted_at IS NULL"
        )->fetchAll(PDO::FETCH_COLUMN);

        $maxCode = 99;
        foreach ($rows as $code) {
            $trimmed = trim((string) $code);
            if ($trimmed !== '' && ctype_digit($trimmed)) {
                $maxCode = max($maxCode, (int) $trimmed);
            }
        }

        return str_pad((string) ($maxCode + 1), 4, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        return '0100';
    }
}

function log_activity(string $module, string $action, string $description, ?string $referenceTable = null, ?int $referenceId = null): void
{
    if (!schema_table_exists('activity_logs')) {
        return;
    }

    $stmt = Database::connection()->prepare(
        'INSERT INTO activity_logs (user_id, branch_id, module_key, action_key, description, reference_table, reference_id, ip_address)
         VALUES (:user_id, :branch_id, :module_key, :action_key, :description, :reference_table, :reference_id, :ip_address)'
    );

    try {
        $stmt->execute([
            'user_id' => Auth::id(),
            'branch_id' => Auth::defaultBranchId(),
            'module_key' => $module,
            'action_key' => $action,
            'description' => $description,
            'reference_table' => $referenceTable,
            'reference_id' => $referenceId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
    }
}

function next_customer_code(): string
{
    try {
        $rows = Database::connection()->query(
            "SELECT code
             FROM customers
             WHERE deleted_at IS NULL"
        )->fetchAll(PDO::FETCH_COLUMN);

        $maxCode = 0;

        foreach ($rows as $code) {
            // يبحث عن أرقام بعد CUST
            if (preg_match('/^CUST(\d+)$/', trim($code), $matches)) {
                $maxCode = max($maxCode, (int) $matches[1]);
            }
        }

        return 'CUST' . str_pad((string) ($maxCode + 1), 4, '0', STR_PAD_LEFT);

    } catch (Throwable $e) {
        return 'CUST0001';
    }
}
