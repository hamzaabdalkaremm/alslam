<?php
require_once __DIR__ . '/../../includes/helpers/functions.php';

$pageSubtitle = 'مراقبة الرصيد وكرت الصنف والجرد والتسويات';
$inventoryService = new InventoryService();
$isSuperAdmin = Auth::isSuperAdmin();
$isDamageAdmin = strtolower((string) (Auth::user()['username'] ?? '')) === 'admin';
$accessibleBranchIds = Auth::branchIds();
$selectedProductId = (int) request_input('product_id', 0);
$selectedWarehouseId = max(0, (int) request_input('warehouse_id', 0));
$showWarehouseForm = request_input('show_warehouse_form', '') === '1';
$showWarehouseList = request_input('show_warehouse_list', '') === '1';
$showDamagePage = $isDamageAdmin && request_input('show_damage_page', '') === '1';
$showAdjustmentForm = request_input('show_adjustment_form', '') === '1';
$showTransferForm = request_input('show_transfer_form', '') === '1';
$showMovements = request_input('show_movements', '') === '1';

// Enforce fine-grained permissions for inventory actions
if ($showTransferForm && !Auth::can('inventory.adjust')) {
    $showTransferForm = false;
}
if ($showDamagePage && !Auth::can('inventory.adjust')) {
    $showDamagePage = false;
}
if ($showAdjustmentForm && !Auth::can('inventory.adjust')) {
    $showAdjustmentForm = false;
}

$search = trim((string) request_input('search', ''));
$perPage = 20;
$currentPage = max(1, (int) request_input('page', 1));
$offset = ($currentPage - 1) * $perPage;

// Movement filter parameters
$movementProductId = (int) request_input('movement_product_id', 0);
$movementWarehouseId = (int) request_input('movement_warehouse_id', 0);
$movementType = trim((string) request_input('movement_type', ''));
$movementDateFrom = trim((string) request_input('movement_date_from', ''));
$movementDateTo = trim((string) request_input('movement_date_to', ''));

/*
|--------------------------------------------------------------------------
| المنتجات
|--------------------------------------------------------------------------
*/
$productsWhere = 'WHERE deleted_at IS NULL';
$productsParams = [];

if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $placeholders = [];
        foreach ($accessibleBranchIds as $index => $branchId) {
            $placeholder = ':branch_' . $index;
            $placeholders[] = $placeholder;
            $productsParams['branch_' . $index] = (int) $branchId;
        }
        $productsWhere .= ' AND (branch_id IS NULL OR branch_id = 0 OR branch_id IN (' . implode(', ', $placeholders) . '))';
    } else {
        $productsWhere .= ' AND 1 = 0';
    }
}

$productsStmt = Database::connection()->prepare(
    "SELECT id, name, branch_id
     FROM products
     {$productsWhere}
     ORDER BY name ASC"
);
$productsStmt->execute($productsParams);
$productsList = $productsStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| الفروع المتاحة للمستخدم
|--------------------------------------------------------------------------
*/
$branchesSql = "
    SELECT id, name_ar
    FROM branches
    WHERE deleted_at IS NULL
      AND status = 'active'
";

$branchesParams = [];
if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $branchPlaceholders = [];
        foreach ($accessibleBranchIds as $index => $branchId) {
            $placeholder = ':allowed_branch_' . $index;
            $branchPlaceholders[] = $placeholder;
            $branchesParams['allowed_branch_' . $index] = (int) $branchId;
        }
        $branchesSql .= ' AND id IN (' . implode(', ', $branchPlaceholders) . ')';
    } else {
        $branchesSql .= ' AND 1 = 0';
    }
}

$branchesSql .= ' ORDER BY name_ar ASC';

$branchesStmt = Database::connection()->prepare($branchesSql);
$branchesStmt->execute($branchesParams);
$branches = $branchesStmt->fetchAll();

$defaultBranchId = Auth::defaultBranchId();
if (!$defaultBranchId && !empty($branches)) {
    $defaultBranchId = (int) $branches[0]['id'];
}

/*
|--------------------------------------------------------------------------
| المسوقون المتاحون لمخازن السيارات فقط
|--------------------------------------------------------------------------
*/
$marketersSql = "
    SELECT m.id, m.full_name
    FROM marketers m
    WHERE m.deleted_at IS NULL
      AND m.status = 'active'
      AND m.marketer_type = 'marketer'
      AND (
            m.default_warehouse_id IS NULL
            OR m.default_warehouse_id = 0
          )
";

$marketersParams = [];
if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $marketerPlaceholders = [];
        foreach ($accessibleBranchIds as $index => $branchId) {
            $placeholder = ':mb_' . $index;
            $marketerPlaceholders[] = $placeholder;
            $marketersParams['mb_' . $index] = (int) $branchId;
        }

        $marketersSql = "
            SELECT DISTINCT m.id, m.full_name
            FROM marketers m
            INNER JOIN marketer_branches mb ON mb.marketer_id = m.id
            WHERE m.deleted_at IS NULL
              AND m.status = 'active'
              AND m.marketer_type = 'marketer'
              AND (
                    m.default_warehouse_id IS NULL
                    OR m.default_warehouse_id = 0
                  )
              AND mb.branch_id IN (" . implode(', ', $marketerPlaceholders) . ")
        ";
    } else {
        $marketersSql = "
            SELECT m.id, m.full_name
            FROM marketers m
            WHERE 1 = 0
        ";
    }
}

$marketersSql .= ' ORDER BY full_name ASC';

$marketersStmt = Database::connection()->prepare($marketersSql);
$marketersStmt->execute($marketersParams);
$vehicleMarketers = $marketersStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| المخازن
|--------------------------------------------------------------------------
*/
$warehousesSql = "
    SELECT w.*, b.name_ar AS branch_name, m.full_name AS marketer_name
    FROM warehouses w
    INNER JOIN branches b ON b.id = w.branch_id
    LEFT JOIN marketers m ON m.id = w.marketer_id
    WHERE w.deleted_at IS NULL
";

$warehousesParams = [];
if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $warehousePlaceholders = [];
        foreach ($accessibleBranchIds as $index => $branchId) {
            $placeholder = ':wh_' . $index;
            $warehousePlaceholders[] = $placeholder;
            $warehousesParams['wh_' . $index] = (int) $branchId;
        }
        $warehousesSql .= ' AND w.branch_id IN (' . implode(', ', $warehousePlaceholders) . ')';
    } else {
        $warehousesSql .= ' AND 1 = 0';
    }
}

$warehousesSql .= ' ORDER BY b.name_ar ASC, w.name ASC';

$warehousesStmt = Database::connection()->prepare($warehousesSql);
$warehousesStmt->execute($warehousesParams);
$warehouses = $warehousesStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| الرصيد
|--------------------------------------------------------------------------
*/
if (!$isSuperAdmin) {
    if (!$accessibleBranchIds) {
        $search = '__no_accessible_branches__';
    }
}

$warehouseFilter = $selectedWarehouseId > 0 ? $selectedWarehouseId : null;
$totalStock = $inventoryService->stockBalance(null, null, null, $search !== '' ? $search : null, $warehouseFilter);
$totalProducts = count($totalStock);
$stockRows = $inventoryService->stockBalance(null, $perPage, $offset, $search !== '' ? $search : null, $warehouseFilter);

$stockPageData = [
    'data' => $stockRows,
    'total' => $totalProducts,
];

$stockPaginationQuery = ['module' => 'inventory'];
if ($search !== '' && $search !== '__no_accessible_branches__') {
    $stockPaginationQuery['search'] = $search;
}
if ($selectedWarehouseId > 0) {
    $stockPaginationQuery['warehouse_id'] = (string) $selectedWarehouseId;
}
if ($showWarehouseForm) {
    $stockPaginationQuery['show_warehouse_form'] = '1';
}
if ($showWarehouseList) {
    $stockPaginationQuery['show_warehouse_list'] = '1';
}
if ($showDamagePage) {
    $stockPaginationQuery['show_damage_page'] = '1';
}
if ($showAdjustmentForm) {
    $stockPaginationQuery['show_adjustment_form'] = '1';
}
if ($showTransferForm) {
    $stockPaginationQuery['show_transfer_form'] = '1';
}
if ($selectedProductId > 0) {
    $stockPaginationQuery['product_id'] = (string) $selectedProductId;
}

$damageRows = [];
if ($showDamagePage) {
    $damageSql = "
        SELECT sm.id,
               sm.movement_date,
               sm.quantity_out,
               sm.notes,
               p.name AS product_name,
               w.name AS warehouse_name
        FROM stock_movements sm
        INNER JOIN products p ON p.id = sm.product_id
        LEFT JOIN warehouses w ON w.id = sm.warehouse_id
        WHERE sm.source_type = 'damaged_stock'
    ";
    $damageParams = [];

    if (!$isSuperAdmin && $accessibleBranchIds) {
        $damagePlaceholders = [];
        foreach ($accessibleBranchIds as $index => $branchId) {
            $placeholder = ':damage_branch_' . $index;
            $damagePlaceholders[] = $placeholder;
            $damageParams['damage_branch_' . $index] = (int) $branchId;
        }
        $damageSql .= ' AND sm.branch_id IN (' . implode(', ', $damagePlaceholders) . ')';
    } elseif (!$isSuperAdmin) {
        $damageSql .= ' AND 1 = 0';
    }

    if ($selectedWarehouseId > 0) {
        $damageSql .= ' AND sm.warehouse_id = :damage_warehouse_id';
        $damageParams['damage_warehouse_id'] = $selectedWarehouseId;
    }

    $damageSql .= ' ORDER BY sm.movement_date DESC, sm.id DESC LIMIT 20';
    $damageStmt = Database::connection()->prepare($damageSql);
    $damageStmt->execute($damageParams);
    $damageRows = $damageStmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| حركة المخزون الكاملة
|--------------------------------------------------------------------------
*/
$movementRows = [];
$movementTotal = 0;

if ($showMovements) {
    $movementSelect = [
        'sm.id',
        'sm.movement_date',
        'sm.movement_type',
        'sm.source_type',
        'sm.source_id',
        'sm.quantity_in',
        'sm.quantity_out',
        'sm.unit_cost',
        'sm.notes',
        'p.id AS product_id',
        'p.name AS product_name',
        'p.code AS product_code',
        'p.barcode AS product_barcode',
        'w.id AS warehouse_id',
        'w.name AS warehouse_name',
        'w.code AS warehouse_code',
        'b.id AS branch_id',
        'b.name_ar AS branch_name',
    ];

    $movementFrom = ' FROM stock_movements sm
                     LEFT JOIN products p ON p.id = sm.product_id
                     LEFT JOIN warehouses w ON w.id = sm.warehouse_id
                     LEFT JOIN branches b ON b.id = sm.branch_id';

    // Note: stock_movements table does not have deleted_at column
    $movementWhere = [];
    $movementParams = [];

    // Product filter
    if ($movementProductId > 0) {
        $movementWhere[] = 'sm.product_id = :product_id';
        $movementParams['product_id'] = $movementProductId;
    }

    // Warehouse filter
    if ($movementWarehouseId > 0) {
        $movementWhere[] = 'sm.warehouse_id = :warehouse_id';
        $movementParams['warehouse_id'] = $movementWarehouseId;
    }

    // Movement type filter
    if ($movementType !== '') {
        $movementWhere[] = 'sm.movement_type = :movement_type';
        $movementParams['movement_type'] = $movementType;
    }

    // Date range filter
    if ($movementDateFrom !== '') {
        $movementWhere[] = 'DATE(sm.movement_date) >= :date_from';
        $movementParams['date_from'] = $movementDateFrom;
    }

    if ($movementDateTo !== '') {
        $movementWhere[] = 'DATE(sm.movement_date) <= :date_to';
        $movementParams['date_to'] = $movementDateTo;
    }

    // Branch/warehouse access control
    if (!$isSuperAdmin) {
        if ($accessibleBranchIds) {
            $placeholders = [];
            foreach ($accessibleBranchIds as $index => $branchId) {
                $placeholder = ':branch_' . $index;
                $placeholders[] = $placeholder;
                $movementParams['branch_' . $index] = (int) $branchId;
            }
            $movementWhere[] = '(sm.branch_id IS NULL OR sm.branch_id IN (' . implode(', ', $placeholders) . '))';
        } else {
            $movementWhere[] = '1 = 0';
        }
    }

    $movementWhereSql = $movementWhere ? ' WHERE ' . implode(' AND ', $movementWhere) : '';

    // Get total count
    $countSql = 'SELECT COUNT(DISTINCT sm.id)' . $movementFrom . $movementWhereSql;
    $countStmt = Database::connection()->prepare($countSql);
    foreach ($movementParams as $key => $value) {
        $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $movementTotal = (int) $countStmt->fetchColumn();

    // Get movements data
    $movementSql = 'SELECT ' . implode(', ', $movementSelect) . $movementFrom . $movementWhereSql;
    $movementSql .= ' ORDER BY sm.movement_date DESC, sm.id DESC';
    $movementSql .= ' LIMIT :limit OFFSET :offset';

    $movementStmt = Database::connection()->prepare($movementSql);
    foreach ($movementParams as $key => $value) {
        $movementStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $movementStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $movementStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $movementStmt->execute();
    $movementRows = $movementStmt->fetchAll();

    $movementPageData = [
        'data' => $movementRows,
        'total' => $movementTotal,
    ];
}
?>

<?php if (Auth::can('branches.update')): ?>
    <div class="card">
        <div class="toolbar">
            <div>
                <h3>عمليات المخازن</h3>
                <p class="card-intro">كل نموذج أو قائمة في صفحة المخزون يفتح الآن من زر مستقل.</p>
            </div>
            <div>
                <a class="btn btn-light" href="index.php?module=inventory&show_warehouse_form=1<?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseList ? '&show_warehouse_list=1' : ''; ?><?= $showDamagePage ? '&show_damage_page=1' : ''; ?><?= $showAdjustmentForm ? '&show_adjustment_form=1' : ''; ?><?= $showTransferForm ? '&show_transfer_form=1' : ''; ?>">إضافة مخزن</a>
                <a class="btn btn-light" href="index.php?module=inventory&show_warehouse_list=1<?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseForm ? '&show_warehouse_form=1' : ''; ?><?= $showDamagePage ? '&show_damage_page=1' : ''; ?><?= $showAdjustmentForm ? '&show_adjustment_form=1' : ''; ?><?= $showTransferForm ? '&show_transfer_form=1' : ''; ?>">عرض المخازن</a>
            </div>
        </div>
    </div>

    <?php if ($showWarehouseForm): ?>
        <div class="card mt-2">
            <div class="toolbar">
                <div>
                    <h3>إضافة مخزن جديد</h3>
                    <p class="card-intro">يمكنك إنشاء مخزن رئيسي أو مخزن سيارة وربطه بمسوق واحد فقط.</p>
                </div>
                <div>
                    <a class="btn btn-light" href="index.php?module=inventory<?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseList ? '&show_warehouse_list=1' : ''; ?><?= $showDamagePage ? '&show_damage_page=1' : ''; ?><?= $showAdjustmentForm ? '&show_adjustment_form=1' : ''; ?><?= $showTransferForm ? '&show_transfer_form=1' : ''; ?>">إغلاق</a>
                </div>
            </div>

            <form action="ajax/branches/warehouse-save.php" method="post" data-ajax-form>
                <?= csrf_field(); ?>

                <div class="form-grid">
                    <div>
                        <label>الفرع</label>
                        <select name="branch_id" required>
                            <option value="">اختر الفرع</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= e((string) $branch['id']); ?>" <?= (string) $defaultBranchId === (string) $branch['id'] ? 'selected' : ''; ?>>
                                    <?= e($branch['name_ar']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>كود المخزن</label>
                        <input name="code" required placeholder="مثال: WH-MAIN-01">
                    </div>

                    <div>
                        <label>اسم المخزن</label>
                        <input name="name" required placeholder="مثال: مخزن الفرع الرئيسي">
                    </div>

                    <div>
                        <label>نوع المخزن</label>
                        <select name="warehouse_type" id="warehouseTypeSelect" required>
                            <option value="main">مخزن رئيسي</option>
                            <option value="vehicle">مخزن سيارة مسوق</option>
                        </select>
                    </div>

                    <div id="warehouseMarketerWrap" style="display:none;">
                        <label>المسوق المسؤول</label>
                        <select name="marketer_id" id="warehouseMarketerSelect">
                            <option value="">اختر المسوق</option>
                            <?php foreach ($vehicleMarketers as $marketer): ?>
                                <option value="<?= e((string) $marketer['id']); ?>">
                                    <?= e($marketer['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-help">لن يظهر هنا إلا المسوقون غير المرتبطين بمخزن سيارة.</small>
                    </div>

                    <div>
                        <label>اسم المسؤول</label>
                        <input name="manager_name" placeholder="اسم المشرف أو المسؤول">
                    </div>

                    <div>
                        <label>الحالة</label>
                        <select name="status">
                            <option value="active" selected>نشط</option>
                            <option value="inactive">موقوف</option>
                        </select>
                    </div>
                </div>

                <div class="mt-2">
                    <label>العنوان</label>
                    <input name="address" placeholder="عنوان المخزن">
                </div>

                <div class="mt-2">
                    <label>ملاحظات</label>
                    <textarea name="notes" placeholder="أي ملاحظات إضافية"></textarea>
                </div>

                <div class="mt-2">
                    <button class="btn btn-primary" type="submit">حفظ المخزن</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($showWarehouseList): ?>
        <div class="card mt-2">
            <div class="toolbar">
                <div>
                    <h3>المخازن الحالية</h3>
                    <p class="card-intro">يعرض هذا الجدول المخازن المتاحة ونوع كل مخزن والمسوق المسؤول إن وجد.</p>
                </div>
                <div>
                    <a class="btn btn-light" href="index.php?module=inventory<?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseForm ? '&show_warehouse_form=1' : ''; ?><?= $showDamagePage ? '&show_damage_page=1' : ''; ?><?= $showAdjustmentForm ? '&show_adjustment_form=1' : ''; ?><?= $showTransferForm ? '&show_transfer_form=1' : ''; ?>">إغلاق</a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>الفرع</th>
                            <th>الكود</th>
                            <th>الاسم</th>
                            <th>النوع</th>
                            <th>المسوق المسؤول</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($warehouses): ?>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <tr>
                                    <td><?= e($warehouse['branch_name']); ?></td>
                                    <td><?= e($warehouse['code']); ?></td>
                                    <td><?= e($warehouse['name']); ?></td>
                                    <td><?= e($warehouse['warehouse_type'] === 'vehicle' ? 'مخزن سيارة' : 'مخزن رئيسي'); ?></td>
                                    <td><?= e($warehouse['marketer_name'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge <?= ($warehouse['status'] ?? 'active') === 'active' ? 'success' : 'warning'; ?>">
                                            <?= e(($warehouse['status'] ?? 'active') === 'active' ? 'نشط' : 'موقوف'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="muted">لا توجد مخازن متاحة.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!$showMovements): ?>
<div class="card mt-2">
    <div class="toolbar">
        <div>
            <h3>أرصدة المخزون</h3>
            <p class="card-intro">هذا القسم هو المرجع الأساسي للرصيد الفعلي. تظهر الأرصدة هنا حسب حركات الشراء والبيع والمرتجعات والتسويات، ويعرض الجدول 20 صنفًا في كل صفحة.</p>
        </div>
        <div>
            <?php if ($showAdjustmentForm): ?>
                <a class="btn btn-light" href="index.php?module=inventory<?= $selectedProductId > 0 ? '&product_id=' . e((string) $selectedProductId) : ''; ?><?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseForm ? '&show_warehouse_form=1' : ''; ?><?= $showWarehouseList ? '&show_warehouse_list=1' : ''; ?><?= $showDamagePage ? '&show_damage_page=1' : ''; ?><?= $showTransferForm ? '&show_transfer_form=1' : ''; ?>">إغلاق</a>
            <?php else: ?>
                <?php if (Auth::can('inventory.adjust')): ?>
                    <a class="btn btn-light" href="index.php?module=inventory&show_transfer_form=1<?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseForm ? '&show_warehouse_form=1' : ''; ?><?= $showWarehouseList ? '&show_warehouse_list=1' : ''; ?><?= $showDamagePage ? '&show_damage_page=1' : ''; ?>">نقل مخزون</a>
                <?php endif; ?>
                <?php if ($isDamageAdmin && Auth::can('inventory.adjust')): ?>
                    <a class="btn btn-light" href="index.php?module=inventory&show_damage_page=1<?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseForm ? '&show_warehouse_form=1' : ''; ?><?= $showWarehouseList ? '&show_warehouse_list=1' : ''; ?><?= $showTransferForm ? '&show_transfer_form=1' : ''; ?><?= $showAdjustmentForm ? '&show_adjustment_form=1' : ''; ?>">التالف</a>
                <?php endif; ?>
                <a class="btn btn-primary" href="index.php?module=inventory&show_movements=1<?= $selectedProductId > 0 ? '&product_id=' . e((string) $selectedProductId) : ''; ?><?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseForm ? '&show_warehouse_form=1' : ''; ?><?= $showWarehouseList ? '&show_warehouse_list=1' : ''; ?><?= $showDamagePage ? '&show_damage_page=1' : ''; ?><?= $showTransferForm ? '&show_transfer_form=1' : ''; ?>">حركة المخزون</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="toolbar">
        <form method="get" class="toolbar-search">
            <input type="hidden" name="module" value="inventory">
            <?php if ($showWarehouseForm): ?>
                <input type="hidden" name="show_warehouse_form" value="1">
            <?php endif; ?>
            <?php if ($showWarehouseList): ?>
                <input type="hidden" name="show_warehouse_list" value="1">
            <?php endif; ?>
            <?php if ($showDamagePage): ?>
                <input type="hidden" name="show_damage_page" value="1">
            <?php endif; ?>
            <?php if ($showAdjustmentForm): ?>
                <input type="hidden" name="show_adjustment_form" value="1">
            <?php endif; ?>
            <?php if ($showTransferForm): ?>
                <input type="hidden" name="show_transfer_form" value="1">
            <?php endif; ?>
            <?php if ($selectedProductId > 0): ?>
                <input type="hidden" name="product_id" value="<?= e((string) $selectedProductId); ?>">
            <?php endif; ?>
            <select name="warehouse_id" id="stockWarehouseFilter">
                <option value="0">كل المخازن</option>
                <?php foreach ($warehouses as $warehouse): ?>
                    <option value="<?= e((string) $warehouse['id']); ?>" <?= $selectedWarehouseId === (int) $warehouse['id'] ? 'selected' : ''; ?>>
                        <?= e($warehouse['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" placeholder="بحث عن صنف أو كود أو باركود" value="<?= e($search === '__no_accessible_branches__' ? '' : $search); ?>">
            <button type="submit" class="btn btn-light">عرض</button>
            <?php if ($selectedWarehouseId > 0): ?>
                <a class="btn btn-light" target="_blank" href="api/print-warehouse-stock.php?warehouse_id=<?= e((string) $selectedWarehouseId); ?>">طباعة كشف المخزن</a>
                <a class="btn btn-light" target="_blank" href="api/print-warehouse-preinvoice.php?warehouse_id=<?= e((string) $selectedWarehouseId); ?>">طباعة فاتورة مبدئية</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>الكود</th>
                    <th>الصنف</th>
                    <th>الرصيد الحالي</th>
                    <th>حد التنبيه</th>
                    <th>كرت الصنف</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($stockRows): ?>
                    <?php foreach ($stockRows as $row): ?>
                        <tr>
                            <td><?= e($row['code']); ?></td>
                            <td><?= e($row['name']); ?></td>
                            <td><?= e(format_number($row['stock_balance'], 3)); ?></td>
                            <td><?= e(number_format((float) $row['min_stock_alert'], 0)); ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-light"
                                    data-stock-card-trigger
                                    data-product-id="<?= e((string) $row['id']); ?>"
                                    data-product-name="<?= e($row['name']); ?>"
                                >
                                    عرض الحركة
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="muted">لا توجد أصناف مطابقة للبحث الحالي.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_pagination($stockPageData, $currentPage, $perPage, $stockPaginationQuery); ?>
</div>
<?php endif; ?>

<?php if ($showMovements): ?>
    <div class="card mt-2">
        <div class="toolbar">
            <div>
                <h3>حركة المخزون الكاملة</h3>
                <p class="card-intro">عرض كافة حركات المخزون (المواد الداخلة والخارجة) مع إمكانية التصفية والتحميل كملف إكسل.</p>
            </div>
            <div>
                <a class="btn btn-light" href="index.php?module=inventory<?= $selectedProductId > 0 ? '&product_id=' . e((string) $selectedProductId) : ''; ?><?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseForm ? '&show_warehouse_form=1' : ''; ?><?= $showWarehouseList ? '&show_warehouse_list=1' : ''; ?><?= $showDamagePage ? '&show_damage_page=1' : ''; ?><?= $showAdjustmentForm ? '&show_adjustment_form=1' : ''; ?><?= $showTransferForm ? '&show_transfer_form=1' : ''; ?>">إغلاق</a>
                <a class="btn btn-primary" href="api/stock-movements-export.php?<?= http_build_query(array_filter([
                    'product_id' => $movementProductId ?: null,
                    'warehouse_id' => $movementWarehouseId ?: null,
                    'movement_type' => $movementType ?: null,
                    'date_from' => $movementDateFrom ?: null,
                    'date_to' => $movementDateTo ?: null,
                ])); ?>" target="_blank">تحميل إكسل</a>
            </div>
        </div>

        <form method="get" class="form-grid mt-2">
            <input type="hidden" name="module" value="inventory">
            <input type="hidden" name="show_movements" value="1">
            <?php if ($selectedProductId > 0): ?>
                <input type="hidden" name="product_id" value="<?= e((string) $selectedProductId); ?>">
            <?php endif; ?>
            <?php if ($selectedWarehouseId > 0): ?>
                <input type="hidden" name="warehouse_id" value="<?= e((string) $selectedWarehouseId); ?>">
            <?php endif; ?>
            <?php if ($search !== ''): ?>
                <input type="hidden" name="search" value="<?= e($search); ?>">
            <?php endif; ?>
            <?php if ($showWarehouseForm): ?>
                <input type="hidden" name="show_warehouse_form" value="1">
            <?php endif; ?>
            <?php if ($showWarehouseList): ?>
                <input type="hidden" name="show_warehouse_list" value="1">
            <?php endif; ?>
            <?php if ($showDamagePage): ?>
                <input type="hidden" name="show_damage_page" value="1">
            <?php endif; ?>
            <?php if ($showAdjustmentForm): ?>
                <input type="hidden" name="show_adjustment_form" value="1">
            <?php endif; ?>
            <?php if ($showTransferForm): ?>
                <input type="hidden" name="show_transfer_form" value="1">
            <?php endif; ?>

            <div>
                <label>الصنف</label>
                <select name="movement_product_id">
                    <option value="">كل الأصناف</option>
                    <?php foreach ($productsList as $product): ?>
                        <option value="<?= e((string) $product['id']); ?>" <?= $movementProductId === (int) $product['id'] ? 'selected' : ''; ?>>
                            <?= e($product['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>المخزن</label>
                <select name="movement_warehouse_id">
                    <option value="">كل المخازن</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= e((string) $warehouse['id']); ?>" <?= $movementWarehouseId === (int) $warehouse['id'] ? 'selected' : ''; ?>>
                            <?= e($warehouse['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

                    <div>
                        <label>نوع الحركة</label>
                        <select name="movement_type">
                            <option value="">جميع الأنواع</option>
                            <?php
                            $movementTypes = [
                                'purchase' => 'شراء - وارد',
                                'sale' => 'بيع - صادر',
                                'purchase_return' => 'مرتجع شراء - وارد',
                                'sale_return' => 'مرتجع بيع - وارد',
                                'transfer_in' => 'نقل - وارد',
                                'transfer_out' => 'نقل - صادر',
                                'adjustment' => 'تسوية',
                                'damage' => 'تالف',
                            ];
                            foreach ($movementTypes as $key => $label):
                            ?>
                                <option value="<?= e($key); ?>" <?= $movementType === $key ? 'selected' : ''; ?>>
                                    <?= e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

            <div>
                <label>من تاريخ</label>
                <input type="date" name="movement_date_from" value="<?= e($movementDateFrom); ?>" max="<?= date('Y-m-d'); ?>">
            </div>

            <div>
                <label>إلى تاريخ</label>
                <input type="date" name="movement_date_to" value="<?= e($movementDateTo); ?>" max="<?= date('Y-m-d'); ?>">
            </div>

            <div class="flex gap-2 items-end">
                <button type="submit" class="btn btn-light">
                    <i class="fa-solid fa-magnifying-glass"></i> تصفية
                </button>
                <?php if ($showMovements && ( $movementProductId > 0 || $movementWarehouseId > 0 || $movementType !== '' || $movementDateFrom !== '' || $movementDateTo !== '' )): ?>
                    <a class="btn btn-light" href="index.php?module=inventory&show_movements=1">مسح الفلاتر</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <table class="stock-movements-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>الصنف</th>
                        <th>المخزن</th>
                        <th>الفرع</th>
                        <th>نوع الحركة</th>
                        <th>المرجع</th>
                        <th>كمية داخلة</th>
                        <th>كمية خارجة</th>
                        <th>التكلفة</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($movementRows): ?>
                        <?php
                        $counter = $offset + 1;
                        foreach ($movementRows as $row):
                            $movementTypeLabels = [
                                'purchase' => 'شراء',
                                'sale' => 'بيع',
                                'purchase_return' => 'مرتجع شراء',
                                'sale_return' => 'مرتجع بيع',
                                'transfer_in' => 'نقل وارد',
                                'transfer_out' => 'نقل صادر',
                                'adjustment' => 'تسوية',
                                'damage' => 'تالف',
                            ];
                            $movementTypeLabel = $movementTypeLabels[$row['movement_type']] ?? $row['movement_type'];
                            $reference = $row['source_type'] ? ($row['source_type'] . ' #' . $row['source_id']) : '-';
                        ?>
                            <tr>
                                <td><?= e((string) $counter++); ?></td>
                                <td><?= e($row['movement_date']); ?></td>
                                <td>
                                    <?= e($row['product_name'] ?? '-'); ?><br>
                                    <small class="muted"><?= e($row['product_code'] ?? ''); ?></small>
                                </td>
                                <td><?= e($row['warehouse_name'] ?? '-'); ?></td>
                                <td><?= e($row['branch_name'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge <?= in_array($row['movement_type'], ['purchase', 'purchase_return', 'sale_return', 'transfer_in', 'adjustment']) ? 'success' : 'danger'; ?>">
                                        <?= e($movementTypeLabel); ?>
                                    </span>
                                </td>
                                <td><small><?= e($reference); ?></small></td>
                                <td class="<?= ($row['quantity_in'] > 0) ? 'text-success' : ''; ?>">
                                    <?= $row['quantity_in'] > 0 ? e(format_number($row['quantity_in'], 3)) : '-'; ?>
                                </td>
                                <td class="<?= ($row['quantity_out'] > 0) ? 'text-danger' : ''; ?>">
                                    <?= $row['quantity_out'] > 0 ? e(format_number($row['quantity_out'], 3)) : '-'; ?>
                                </td>
                                <td><?= e(format_currency($row['unit_cost'] ?? 0)); ?></td>
                                <td><?= e($row['notes'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center muted">لا توجد حركات مسجلة تطابق الفلاتر المحددة.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($movementTotal > $perPage): ?>
            <?= render_pagination($movementPageData, $currentPage, $perPage, [
                'module' => 'inventory',
                'show_movements' => '1',
                'movement_product_id' => $movementProductId ?: null,
                'movement_warehouse_id' => $movementWarehouseId ?: null,
                'movement_type' => $movementType ?: null,
                'movement_date_from' => $movementDateFrom ?: null,
                'movement_date_to' => $movementDateTo ?: null,
            ]); ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($showDamagePage): ?>
    <div class="card mt-2">
        <div class="toolbar">
            <div>
                <h3>التالف</h3>
                <p class="card-intro">سجل هنا الأصناف التالفة ليتم خصمها من المخزون حسب المخزن المحدد.</p>
            </div>
            <div>
                <a class="btn btn-light" href="index.php?module=inventory<?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseForm ? '&show_warehouse_form=1' : ''; ?><?= $showWarehouseList ? '&show_warehouse_list=1' : ''; ?><?= $showAdjustmentForm ? '&show_adjustment_form=1' : ''; ?><?= $showTransferForm ? '&show_transfer_form=1' : ''; ?>">إغلاق</a>
            </div>
        </div>

        <form action="ajax/inventory/damaged.php" method="post" data-ajax-form data-reset="false">
            <?= csrf_field(); ?>

            <div class="form-grid">
                <div>
                    <label>المخزن</label>
                    <select name="warehouse_id" id="damageWarehouseSelect" required>
                        <option value="">اختر المخزن</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= e((string) $warehouse['id']); ?>" <?= $selectedWarehouseId === (int) $warehouse['id'] ? 'selected' : ''; ?>>
                                <?= e($warehouse['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>الصنف</label>
                    <select name="product_id" id="damageProductSelect" required>
                        <option value="">اختر الصنف</option>
                        <?php foreach ($productsList as $productItem): ?>
                            <option value="<?= e((string) $productItem['id']); ?>"><?= e($productItem['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>الرصيد المتاح</label>
                    <input type="number" step="0.001" id="damageAvailableStock" value="0" readonly>
                </div>

                <div>
                    <label>الكمية التالفة</label>
                    <input type="number" step="0.001" min="0.001" name="quantity" value="1" required>
                </div>

                <div>
                    <label>تاريخ التلف</label>
                    <input type="date" name="damage_date" value="<?= e(date('Y-m-d')); ?>" required>
                </div>

                <div>
                    <label>ملاحظات</label>
                    <input type="text" name="notes" placeholder="سبب التلف أو أي ملاحظة">
                </div>
            </div>

            <div class="mt-2">
                <button type="submit" class="btn btn-primary">حفظ التالف</button>
            </div>
        </form>
    </div>

    <div class="card mt-2">
        <div class="toolbar">
            <div>
                <h3>آخر عمليات التالف</h3>
                <p class="card-intro">يعرض آخر الحركات المسجلة كتالف<?= $selectedWarehouseId > 0 ? ' للمخزن المحدد.' : '.'; ?></p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>المخزن</th>
                        <th>الصنف</th>
                        <th>الكمية</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($damageRows): ?>
                        <?php foreach ($damageRows as $damageRow): ?>
                            <tr>
                                <td><?= e($damageRow['movement_date']); ?></td>
                                <td><?= e($damageRow['warehouse_name'] ?? '-'); ?></td>
                                <td><?= e($damageRow['product_name']); ?></td>
                                <td><?= e(format_number($damageRow['quantity_out'], 3)); ?></td>
                                <td><?= e($damageRow['notes'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="muted">لا توجد عمليات تالف مسجلة حتى الآن.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="inventory-stock-modal" id="inventoryStockModal" hidden>
    <div class="inventory-stock-modal__backdrop" data-stock-card-close></div>
    <div class="inventory-stock-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="inventoryStockModalTitle">
        <div class="inventory-stock-modal__header">
            <div>
                <h3 id="inventoryStockModalTitle">كرت الصنف</h3>
                <p class="inventory-stock-modal__subtitle" id="inventoryStockModalMeta">جاري تحميل بيانات الحركة...</p>
            </div>
            <button type="button" class="btn btn-light inventory-stock-modal__close" data-stock-card-close>إغلاق</button>
        </div>

        <div class="inventory-stock-modal__body">
            <div class="inventory-stock-modal__state" id="inventoryStockModalState">جاري تحميل حركة الصنف...</div>
            <div class="table-wrap" id="inventoryStockModalTableWrap" hidden>
                <table>
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الحركة</th>
                            <th>المرجع</th>
                            <th>داخل</th>
                            <th>خارج</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryStockModalRows"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($showAdjustmentForm): ?>
    <div class="card mt-2">
        <div class="toolbar">
            <div>
                <h3>تسوية / جرد</h3>
                <p class="card-intro">استخدم الجرد عندما تكون الكمية الفعلية مختلفة عن الكمية المسجلة. النظام سيحسب الفرق ويعدل الرصيد تلقائيًا.</p>
            </div>
        </div>

        <form action="ajax/inventory/adjust.php" method="post" data-ajax-form>
            <?= csrf_field(); ?>

            <div class="form-grid">
                <div>
                    <label>رقم التسوية</label>
                    <input name="adjustment_no" required value="ADJ-<?= e(date('YmdHis')); ?>">
                    <small class="field-help">رقم مرجعي لعملية الجرد أو التسوية.</small>
                </div>

                <div>
                    <label>التاريخ والوقت</label>
                    <input type="datetime-local" name="adjustment_date" required value="<?= e(date('Y-m-d\TH:i')); ?>">
                    <small class="field-help">وقت اعتماد التسوية داخل النظام.</small>
                </div>

                <div>
                    <label>سبب التسوية</label>
                    <input name="reason" required value="جرد دوري">
                    <small class="field-help">مثال: جرد دوري، كسر، تلف، نقص، زيادة.</small>
                </div>

                <div>
                    <label>الفرع</label>
                    <select name="branch_id" id="adjustmentBranchSelect" required>
                        <option value="">اختر الفرع</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= e((string) $branch['id']); ?>" <?= (string) $defaultBranchId === (string) $branch['id'] ? 'selected' : ''; ?>>
                                <?= e($branch['name_ar']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>المخزن</label>
                    <select name="warehouse_id" id="adjustmentWarehouseSelect" required>
                        <option value="">اختر المخزن</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= e((string) $warehouse['id']); ?>" data-branch="<?= e((string) $warehouse['branch_id']); ?>"
                                <?= $selectedWarehouseId == $warehouse['id'] ? 'selected' : '' ?>>
                                <?= e($warehouse['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="dynamic-rows mt-2" id="adjustmentRows">
                <div class="row">
                    <div>
                        <label>الصنف</label>
                        <select name="items[0][product_id]" required>
                            <option value="">الصنف</option>
                            <?php foreach ($productsList as $productItem): ?>
                                <option value="<?= e((string) $productItem['id']); ?>"
                                    data-branch="<?= e((string) ($productItem['branch_id'] ?? '')); ?>"
                                    <?= $selectedProductId == $productItem['id'] ? 'selected' : '' ?>>
                                    <?= e($productItem['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-help">اختر المنتج الذي تريد تعديل كميته.</small>
                    </div>

                    <div>
                        <label>المخزن</label>
                        <select name="items[0][warehouse_id]" required>
                            <option value="">اختر المخزن</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?= e((string) $warehouse['id']); ?>" data-branch="<?= e((string) $warehouse['branch_id']); ?>"
                                    <?= $selectedWarehouseId == $warehouse['id'] ? 'selected' : '' ?>>
                                    <?= e($warehouse['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>رصيد النظام</label>
                        <input type="number" step="0.001" name="items[0][system_quantity]" placeholder="رصيد النظام" value="0" readonly>
                        <small class="field-help">الكمية المسجلة حاليًا في النظام قبل الجرد.</small>
                    </div>

                    <div>
                        <label>الكمية الفعلية</label>
                        <input type="number" step="0.001" name="items[0][actual_quantity]" placeholder="الكمية الفعلية" value="0">
                        <small class="field-help">الكمية التي وجدتها فعليًا في المخزن.</small>
                    </div>

                    <div>
                        <label>تكلفة الوحدة</label>
                        <input type="number" step="0.01" name="items[0][unit_cost]" placeholder="تكلفة الوحدة" value="0">
                        <small class="field-help">تكلفة تقديرية للوحدة لأغراض التقييم المالي.</small>
                    </div>

                    <div>
                        <label>معرف الدفعة</label>
                        <input type="text" name="items[0][batch_id]" placeholder="معرف الدفعة اختياري">
                        <small class="field-help">استخدمه إذا كانت التسوية مرتبطة بدفعة صلاحية محددة.</small>
                    </div>

                    <div class="align-self-end">
                        <button class="btn btn-danger" type="button" data-remove-row>حذف</button>
                    </div>
                </div>
            </div>

            <template id="adjustmentRowTemplate">
                <div class="row">
                    <div>
                        <label>الصنف</label>
                        <select name="items[][product_id]" required>
                            <option value="">الصنف</option>
                            <?php foreach ($productsList as $productItem): ?>
                                <option value="<?= e((string) $productItem['id']); ?>"
                                    data-branch="<?= e((string) ($productItem['branch_id'] ?? '')); ?>">
                                    <?= e($productItem['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-help">اختر المنتج الذي تريد تعديل كميته.</small>
                    </div>

                <div>
                    <label>المخزن</label>
                    <select name="warehouse_id" id="adjustmentWarehouseSelect" required>
                        <option value="">اختر المخزن</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= e((string) $warehouse['id']); ?>" data-branch="<?= e((string) $warehouse['branch_id']); ?>"
                                <?= $selectedWarehouseId == $warehouse['id'] ? 'selected' : '' ?>>
                                <?= e($warehouse['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                    <div>
                        <label>رصيد النظام</label>
                        <input type="number" step="0.001" name="items[][system_quantity]" placeholder="رصيد النظام" value="0" readonly>
                        <small class="field-help">الكمية المسجلة حاليًا في النظام قبل الجرد.</small>
                    </div>

                    <div>
                        <label>الكمية الفعلية</label>
                        <input type="number" step="0.001" name="items[][actual_quantity]" placeholder="الكمية الفعلية" value="0">
                        <small class="field-help">الكمية التي وجدتها فعليًا في المخزن.</small>
                    </div>

                    <div>
                        <label>تكلفة الوحدة</label>
                        <input type="number" step="0.01" name="items[][unit_cost]" placeholder="تكلفة الوحدة" value="0">
                        <small class="field-help">تكلفة تقديرية للوحدة لأغراض التقييم المالي.</small>
                    </div>

                    <div>
                        <label>معرف الدفعة</label>
                        <input type="text" name="items[][batch_id]" placeholder="معرف الدفعة اختياري">
                        <small class="field-help">استخدمه إذا كانت التسوية مرتبطة بدفعة صلاحية محددة.</small>
                    </div>

                    <div class="align-self-end">
                        <button class="btn btn-danger" type="button" data-remove-row>حذف</button>
                    </div>
                </div>
            </template>

            <div class="mt-2">
                <button class="btn btn-light" type="button" data-add-row="#adjustmentRows" data-template="#adjustmentRowTemplate">إضافة صنف</button>
                <button class="btn btn-primary" type="submit">حفظ التسوية</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($showTransferForm): ?>
    <div class="card mt-2">
        <div class="toolbar">
            <div>
                <h3>نقل مخزون بين المخازن</h3>
                <p class="card-intro">نقل المنتجات من مخزن إلى آخر. يتم خصم الكمية من المخزن المصدر وإضافتها للمخزن المستلم.</p>
            </div>
            <div>
                <a class="btn btn-light" href="index.php?module=inventory<?= $selectedWarehouseId > 0 ? '&warehouse_id=' . e((string) $selectedWarehouseId) : ''; ?><?= $search !== '' ? '&search=' . e($search) : ''; ?><?= $showWarehouseForm ? '&show_warehouse_form=1' : ''; ?><?= $showWarehouseList ? '&show_warehouse_list=1' : ''; ?><?= $showAdjustmentForm ? '&show_adjustment_form=1' : ''; ?>">إغلاق</a>
            </div>
        </div>

        <form action="ajax/inventory/transfer.php" method="post" data-ajax-form data-reset="false">
            <?= csrf_field(); ?>

            <div class="form-grid">
                <div>
                    <label>رقم التحويل</label>
                    <input type="text" name="transfer_no" required value="TRF-<?= e(date('YmdHis')); ?>">
                </div>

                <div>
                    <label>التاريخ</label>
                    <input type="datetime-local" name="transfer_date" required value="<?= e(date('Y-m-d\TH:i')); ?>">
                </div>

                <div>
                    <label>المخزن المصدر</label>
                    <select name="source_warehouse_id" id="sourceWarehouse" required>
                        <option value="">اختر المخزن المصدر</option>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?= e((string) $wh['id']); ?>"><?= e($wh['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>المخزن المستلم</label>
                    <select name="destination_warehouse_id" id="destWarehouse" required>
                        <option value="">اختر المخزن المستلم</option>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?= e((string) $wh['id']); ?>" data-code="<?= e($wh['code']); ?>"><?= e($wh['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid mt-2">
                <div>
                    <label>ملاحظات</label>
                    <input type="text" name="notes" placeholder="ملاحظات اختيارية">
                </div>
            </div>

            <div class="dynamic-rows mt-2" id="transferItems">
                <div class="row">
                    <div>
                        <label>الصنف</label>
                        <select name="items[0][product_id]" class="product-select" required>
                            <option value="">اختر الصنف</option>
                            <?php foreach ($productsList as $product): ?>
                                <option value="<?= e((string) $product['id']); ?>"><?= e($product['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>الكمية</label>
                        <input type="number" step="0.001" name="items[0][quantity]" min="0.001" required value="1">
                    </div>

                    <div class="align-self-end">
                        <button type="button" class="btn btn-danger" data-remove-row>حذف</button>
                    </div>
                </div>
            </div>

            <template id="transferItemTemplate">
                <div class="row">
                    <div>
                        <label>الصنف</label>
                        <select name="items[][product_id]" class="product-select" required>
                            <option value="">اختر الصنف</option>
                            <?php foreach ($productsList as $product): ?>
                                <option value="<?= e((string) $product['id']); ?>"><?= e($product['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>الكمية</label>
                        <input type="number" step="0.001" name="items[][quantity]" min="0.001" required value="1">
                    </div>

                    <div class="align-self-end">
                        <button type="button" class="btn btn-danger" data-remove-row>حذف</button>
                    </div>
                </div>
            </template>

            <div class="mt-2">
<button type="button" data-add-row="#transferItems" data-template="#transferItemTemplate">
    إضافة صنف
</button>                <button type="submit" class="btn btn-primary">تنفيذ النقل</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const warehouseTypeSelect = document.getElementById('warehouseTypeSelect');
    const warehouseMarketerWrap = document.getElementById('warehouseMarketerWrap');
    const warehouseMarketerSelect = document.getElementById('warehouseMarketerSelect');
    const adjustmentBranchSelect = document.getElementById('adjustmentBranchSelect');
    const adjustmentWarehouseSelect = document.getElementById('adjustmentWarehouseSelect');
    const adjustmentRows = document.getElementById('adjustmentRows');
    const stockModal = document.getElementById('inventoryStockModal');
    const stockModalTitle = document.getElementById('inventoryStockModalTitle');
    const stockModalMeta = document.getElementById('inventoryStockModalMeta');
    const stockModalState = document.getElementById('inventoryStockModalState');
    const stockModalRows = document.getElementById('inventoryStockModalRows');
    const stockModalTableWrap = document.getElementById('inventoryStockModalTableWrap');
    const stockCardTriggers = document.querySelectorAll('[data-stock-card-trigger]');
    const stockWarehouseFilter = document.getElementById('stockWarehouseFilter');
    const damageWarehouseSelect = document.getElementById('damageWarehouseSelect');
    const damageProductSelect = document.getElementById('damageProductSelect');
    const damageAvailableStock = document.getElementById('damageAvailableStock');
    const headerWarehouseOptions = adjustmentWarehouseSelect
        ? adjustmentWarehouseSelect.querySelectorAll('option[data-branch]')
        : [];
    let activeStockRequest = 0;

    function toggleWarehouseMarketer() {
        if (!warehouseTypeSelect || !warehouseMarketerWrap || !warehouseMarketerSelect) {
            return;
        }

        const isVehicle = warehouseTypeSelect.value === 'vehicle';
        warehouseMarketerWrap.style.display = isVehicle ? '' : 'none';

        if (!isVehicle) {
            warehouseMarketerSelect.value = '';
        }
    }

    if (warehouseTypeSelect) {
        warehouseTypeSelect.addEventListener('change', toggleWarehouseMarketer);
        toggleWarehouseMarketer();
    }

    function filterWarehousesByBranch() {
        if (!adjustmentBranchSelect || !adjustmentWarehouseSelect) {
            return;
        }

        const selectedBranch = adjustmentBranchSelect.value;
        headerWarehouseOptions.forEach((opt) => {
            if (selectedBranch === '' || opt.dataset.branch === selectedBranch) {
                opt.style.display = '';
                opt.disabled = false;
            } else {
                opt.style.display = 'none';
                opt.disabled = true;
            }
        });

        const selectedOption = adjustmentWarehouseSelect.options[adjustmentWarehouseSelect.selectedIndex];
        if (selectedOption && selectedOption.disabled) {
            adjustmentWarehouseSelect.value = '';
        }

        if (!adjustmentWarehouseSelect.value) {
            const firstMatching = Array.from(headerWarehouseOptions).find((opt) => !opt.disabled);
            if (firstMatching) {
                adjustmentWarehouseSelect.value = firstMatching.value;
            }
        }
    }

    function syncAdjustmentRowWarehouse(row, warehouseId) {
        const rowWarehouseSelect = row.querySelector('[name$="[warehouse_id]"]');
        if (!rowWarehouseSelect || !warehouseId) {
            return;
        }

        const targetOption = Array.from(rowWarehouseSelect.options).find(
            (option) => option.value === warehouseId && !option.disabled
        );
        if (!targetOption) {
            return;
        }

        rowWarehouseSelect.value = warehouseId;
    }

    function filterRowProductsByBranch(row) {
        const productSelect = row.querySelector('[name$="[product_id]"]');
        if (!productSelect) {
            return;
        }

        const rowWarehouseSelect = row.querySelector('[name$="[warehouse_id]"]');
        const selectedWarehouseOption = rowWarehouseSelect
            ? rowWarehouseSelect.options[rowWarehouseSelect.selectedIndex]
            : null;
        const branchId = selectedWarehouseOption?.dataset.branch || '';

        productSelect.querySelectorAll('option[data-branch]').forEach((opt) => {
            const optionBranch = opt.dataset.branch || '';
            const isVisible = branchId === '' || optionBranch === '' || optionBranch === branchId;

            opt.style.display = isVisible ? '' : 'none';
            opt.disabled = !isVisible;
        });

        const selectedProductOption = productSelect.options[productSelect.selectedIndex];
        if (selectedProductOption && selectedProductOption.disabled) {
            productSelect.value = '';
            const systemQtyInput = row.querySelector('[name$="[system_quantity]"]');
            if (systemQtyInput) {
                systemQtyInput.value = '0';
            }
        }
    }

    function filterRowWarehouses(row) {
        const rowWarehouseSelect = row.querySelector('[name$="[warehouse_id]"]');
        if (!rowWarehouseSelect) {
            return;
        }

        const selectedBranch = adjustmentBranchSelect ? adjustmentBranchSelect.value : '';
        const rowWarehouseOptions = rowWarehouseSelect.querySelectorAll('option[data-branch]');

        rowWarehouseOptions.forEach((opt) => {
            if (selectedBranch === '' || opt.dataset.branch === selectedBranch) {
                opt.style.display = '';
                opt.disabled = false;
            } else {
                opt.style.display = 'none';
                opt.disabled = true;
            }
        });

        const selectedOption = rowWarehouseSelect.options[rowWarehouseSelect.selectedIndex];
        if (selectedOption && selectedOption.disabled) {
            rowWarehouseSelect.value = '';
        }
    }

    function updateSystemQuantity(selectElement) {
        const row = selectElement.closest('.row');
        if (!row) {
            return;
        }

        const systemQtyInput = row.querySelector('[name$="[system_quantity]"]');
        const warehouseSelect = row.querySelector('[name$="[warehouse_id]"]');
        if (!systemQtyInput || selectElement.value === '' || !warehouseSelect || !warehouseSelect.value) {
            systemQtyInput.value = '0';
            return;
        }

        const productId = selectElement.value;
        const warehouseId = warehouseSelect.value;

        fetch('api/product-stock.php?id=' + encodeURIComponent(productId) + '&warehouse_id=' + encodeURIComponent(warehouseId))
            .then((res) => res.json())
            .then((data) => {
                if (data.success && data.stock_balance !== undefined) {
                    systemQtyInput.value = data.stock_balance;
                } else {
                    systemQtyInput.value = '0';
                }
            })
            .catch(() => {
                systemQtyInput.value = '0';
            });
    }

    function bindAdjustmentRow(row) {
        if (!row) {
            return;
        }

        filterRowWarehouses(row);

        if (adjustmentWarehouseSelect && adjustmentWarehouseSelect.value) {
            syncAdjustmentRowWarehouse(row, adjustmentWarehouseSelect.value);
        }

        filterRowProductsByBranch(row);

        const productSelect = row.querySelector('[name$="[product_id]"]');
        const warehouseSelect = row.querySelector('[name$="[warehouse_id]"]');

        if (productSelect) {
            productSelect.addEventListener('change', function () {
                filterRowProductsByBranch(row);
                updateSystemQuantity(this);
            });
        }

        if (warehouseSelect) {
            warehouseSelect.addEventListener('change', function () {
                filterRowProductsByBranch(row);
                if (productSelect && productSelect.value) {
                    updateSystemQuantity(productSelect);
                } else {
                    const systemQtyInput = row.querySelector('[name$="[system_quantity]"]');
                    if (systemQtyInput) {
                        systemQtyInput.value = '0';
                    }
                }
            });
        }

        if (productSelect && productSelect.value) {
            updateSystemQuantity(productSelect);
        }
    }

    function syncAdjustmentRowsWithHeaderWarehouse() {
        if (!adjustmentRows) {
            return;
        }

        adjustmentRows.querySelectorAll('.row').forEach((row) => {
            filterRowWarehouses(row);

            if (adjustmentWarehouseSelect && adjustmentWarehouseSelect.value) {
                syncAdjustmentRowWarehouse(row, adjustmentWarehouseSelect.value);
            }

            filterRowProductsByBranch(row);

            const productSelect = row.querySelector('[name$="[product_id]"]');
            if (productSelect && productSelect.value) {
                updateSystemQuantity(productSelect);
            }
        });
    }

    if (adjustmentBranchSelect) {
        adjustmentBranchSelect.addEventListener('change', function () {
            filterWarehousesByBranch();
            syncAdjustmentRowsWithHeaderWarehouse();
        });
        filterWarehousesByBranch();
    }

    if (adjustmentWarehouseSelect) {
        adjustmentWarehouseSelect.addEventListener('change', syncAdjustmentRowsWithHeaderWarehouse);
    }

    if (adjustmentRows) {
        adjustmentRows.querySelectorAll('.row').forEach(bindAdjustmentRow);
        syncAdjustmentRowsWithHeaderWarehouse();
    }

    document.addEventListener('dynamic-row:added', function (event) {
        if (event.detail?.target?.id !== 'adjustmentRows') {
            return;
        }

        const rows = event.detail.target.querySelectorAll('.row');
        const lastRow = rows[rows.length - 1];
        bindAdjustmentRow(lastRow);
    });

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function closeStockModal() {
        if (!stockModal) {
            return;
        }

        stockModal.hidden = true;
        document.body.classList.remove('inventory-stock-modal-open');
    }

    function openStockModal() {
        if (!stockModal) {
            return;
        }

        stockModal.hidden = false;
        document.body.classList.add('inventory-stock-modal-open');
    }

    function setStockModalState(message, tone) {
        if (!stockModalState || !stockModalTableWrap) {
            return;
        }

        stockModalState.textContent = message;
        stockModalState.hidden = false;
        stockModalState.dataset.tone = tone || 'muted';
        stockModalTableWrap.hidden = true;
    }

    function renderStockCardRows(rows) {
        if (!stockModalRows || !stockModalTableWrap || !stockModalState) {
            return;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            setStockModalState('لا توجد حركات مسجلة لهذا الصنف حتى الآن.', 'muted');
            stockModalRows.innerHTML = '';
            return;
        }

        stockModalRows.innerHTML = rows.map((row) => `
            <tr>
                <td>${escapeHtml(row.movement_date || '')}</td>
                <td>${escapeHtml(row.movement_type || '')}</td>
                <td>${escapeHtml((row.source_type || '') + '#' + (row.source_id || ''))}</td>
                <td>${escapeHtml(row.quantity_in ?? '0')}</td>
                <td>${escapeHtml(row.quantity_out ?? '0')}</td>
                <td>${escapeHtml(row.notes || '')}</td>
            </tr>
        `).join('');

        stockModalState.hidden = true;
        stockModalTableWrap.hidden = false;
    }

    function updateDamageAvailableStock() {
        if (!damageWarehouseSelect || !damageProductSelect || !damageAvailableStock) {
            return;
        }

        if (!damageWarehouseSelect.value || !damageProductSelect.value) {
            damageAvailableStock.value = '0';
            return;
        }

        fetch(`api/product-stock.php?id=${encodeURIComponent(damageProductSelect.value)}&warehouse_id=${encodeURIComponent(damageWarehouseSelect.value)}`)
            .then((res) => res.json())
            .then((data) => {
                if (data.success && data.stock_balance !== undefined) {
                    damageAvailableStock.value = data.stock_balance;
                } else {
                    damageAvailableStock.value = '0';
                }
            })
            .catch(() => {
                damageAvailableStock.value = '0';
            });
    }

    async function loadStockCard(productId, productName) {
        const requestId = ++activeStockRequest;
        const warehouseId = stockWarehouseFilter ? stockWarehouseFilter.value : '0';
        const warehouseName = stockWarehouseFilter && stockWarehouseFilter.value !== '0'
            ? stockWarehouseFilter.options[stockWarehouseFilter.selectedIndex].text
            : '';

        if (stockModalTitle) {
            stockModalTitle.textContent = `كرت الصنف: ${productName || '...'}`;
        }
        if (stockModalMeta) {
            stockModalMeta.textContent = warehouseId !== '0'
                ? `جاري تحميل بيانات الحركة للمخزن: ${warehouseName}...`
                : 'جاري تحميل بيانات الحركة...';
        }

        setStockModalState('جاري تحميل حركة الصنف...', 'loading');
        openStockModal();

        try {
            const response = await fetch(`api/stock-card.php?id=${encodeURIComponent(productId)}&warehouse_id=${encodeURIComponent(warehouseId)}`);
            const payload = await response.json();

            if (requestId !== activeStockRequest) {
                return;
            }

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'تعذر تحميل حركة الصنف.');
            }

            const product = payload.product || {};
            if (stockModalTitle) {
                stockModalTitle.textContent = `كرت الصنف: ${product.name || productName || ''}`;
            }
            if (stockModalMeta) {
                const codePart = product.code ? `الكود: ${product.code}` : 'بدون كود';
                const balancePart = product.stock_balance !== undefined ? `الرصيد الحالي: ${product.stock_balance}` : '';
                const warehousePart = warehouseName ? ` | المخزن: ${warehouseName}` : '';
                stockModalMeta.textContent = `${codePart}${balancePart ? ' | ' + balancePart : ''}${warehousePart}`;
            }

            renderStockCardRows(payload.stock_card || []);
        } catch (error) {
            if (requestId !== activeStockRequest) {
                return;
            }

            setStockModalState(error.message || 'تعذر تحميل حركة الصنف.', 'danger');
        }
    }

    stockCardTriggers.forEach((trigger) => {
        trigger.addEventListener('click', function () {
            loadStockCard(this.dataset.productId, this.dataset.productName || '');
        });
    });

    if (damageWarehouseSelect) {
        damageWarehouseSelect.addEventListener('change', updateDamageAvailableStock);
    }

    if (damageProductSelect) {
        damageProductSelect.addEventListener('change', updateDamageAvailableStock);
    }

    updateDamageAvailableStock();

    stockModal?.querySelectorAll('[data-stock-card-close]').forEach((button) => {
        button.addEventListener('click', closeStockModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && stockModal && !stockModal.hidden) {
            closeStockModal();
        }
    });

    <?php if ($selectedProductId > 0): ?>
    loadStockCard(<?= json_encode((string) $selectedProductId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, '');
    <?php endif; ?>
});
</script>
