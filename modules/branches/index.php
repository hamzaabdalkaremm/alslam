<?php
require_once __DIR__ . '/../../includes/branch_admin_only.php';
$crud = new CrudService();
$isSuperAdmin = Auth::isSuperAdmin();
$accessibleBranchIds = Auth::branchIds();
$editId = (int) request_input('edit', 0);
$showForm = $editId > 0 || request_input('show_form', '') === '1';

$branch = null;
if ($editId > 0) {
    Auth::requireBranchAccess($editId);
    $branch = $crud->find('branches', $editId);
}

$branches = [];
$branchStatsMap = [];
$warehouses = [];
$params = [];
$whereSql = 'b.deleted_at IS NULL';

if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $placeholders = [];
        foreach ($accessibleBranchIds as $index => $branchId) {
            $placeholder = ':branch_' . $index;
            $placeholders[] = $placeholder;
            $params['branch_' . $index] = (int) $branchId;
        }
        $whereSql .= ' AND b.id IN (' . implode(', ', $placeholders) . ')';
    } else {
        $whereSql .= ' AND 1 = 0';
    }
}

$stmt = Database::connection()->prepare(
    "SELECT b.*,
            COALESCE(w.warehouses_count, 0) AS warehouses_count,
            COALESCE(u.users_count, 0) AS users_count,
            COALESCE(m.marketers_count, 0) AS marketers_count,
            COALESCE(s.sales_total, 0) AS sales_total,
            COALESCE(p.purchases_total, 0) AS purchases_total,
            COALESCE(e.expenses_total, 0) AS expenses_total
     FROM branches b
     LEFT JOIN (
         SELECT branch_id, COUNT(*) AS warehouses_count
         FROM warehouses
         WHERE deleted_at IS NULL
         GROUP BY branch_id
     ) w ON w.branch_id = b.id
     LEFT JOIN (
         SELECT default_branch_id AS branch_id, COUNT(*) AS users_count
         FROM users
         WHERE deleted_at IS NULL AND default_branch_id IS NOT NULL
         GROUP BY default_branch_id
     ) u ON u.branch_id = b.id
     LEFT JOIN (
         SELECT mb.branch_id, COUNT(DISTINCT mb.marketer_id) AS marketers_count
         FROM marketer_branches mb
         INNER JOIN marketers m ON m.id = mb.marketer_id AND m.deleted_at IS NULL
         GROUP BY mb.branch_id
     ) m ON m.branch_id = b.id
     LEFT JOIN (
         SELECT branch_id, COALESCE(SUM(total_amount), 0) AS sales_total
         FROM sales
         WHERE deleted_at IS NULL
         GROUP BY branch_id
     ) s ON s.branch_id = b.id
     LEFT JOIN (
         SELECT branch_id, COALESCE(SUM(total_amount), 0) AS purchases_total
         FROM purchases
         WHERE deleted_at IS NULL
         GROUP BY branch_id
     ) p ON p.branch_id = b.id
     LEFT JOIN (
         SELECT branch_id, COALESCE(SUM(amount), 0) AS expenses_total
         FROM expenses
         WHERE deleted_at IS NULL
         GROUP BY branch_id
     ) e ON e.branch_id = b.id
     WHERE {$whereSql}
     ORDER BY b.name_ar ASC"
);
$stmt->execute($params);
$branches = $stmt->fetchAll();

foreach ($branches as $branchRow) {
    $branchStatsMap[(int) $branchRow['id']] = $branchRow;
}

if ($branch) {
    $warehouses = warehouses_options((int) $branch['id']);
}
?>

<div class="card">
    <div class="toolbar">
        <div>
            <h3>الفروع الحالية</h3>
            <p class="card-intro">كل فرع مرتبط بمخازنه ومستخدميه وحركته المالية والتجارية.</p>
        </div>
        <div>
            <?php if ($showForm): ?>
                <a class="btn btn-light" href="index.php?module=branches">إغلاق النموذج</a>
            <?php elseif ($isSuperAdmin): ?>
                <a class="btn btn-primary" href="index.php?module=branches&show_form=1">إضافة فرع</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>الكود</th>
                <th>الفرع</th>
                <th>المدينة</th>
                <th>المخازن</th>
                <th>المبيعات</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($branches as $row): ?>
                <tr>
                    <td><?= e($row['code']); ?></td>
                    <td><?= e($row['name_ar']); ?></td>
                    <td><?= e($row['city'] ?: '-'); ?></td>
                    <td><?= e((string) $row['warehouses_count']); ?></td>
                    <td><?= e(format_currency($row['sales_total'])); ?></td>
                    <td>
                        <span class="badge <?= $row['status'] === 'active' ? 'success' : 'warning'; ?>">
                            <?= e($row['status'] === 'active' ? 'نشط' : 'موقوف'); ?>
                        </span>
                    </td>
                    <td>
                        <?php if (Auth::can('branches.update')): ?>
                            <a class="btn btn-light" href="index.php?module=branches&edit=<?= e((string) $row['id']); ?>">تعديل</a>
                        <?php endif; ?>
                        <?php if ($isSuperAdmin): ?>
                            <button type="button" class="btn btn-danger" data-id="<?= e((string) $row['id']); ?>" data-delete-url="ajax/branches/delete.php" data-confirm="هل تريد تعطيل هذا الفرع؟">تعطيل</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($showForm && ($branch || $isSuperAdmin)): ?>
    <div class="card mt-2">
        <div class="toolbar">
            <div>
                <h3><?= $branch ? 'تعديل فرع' : 'إضافة فرع'; ?></h3>
                <p class="card-intro">أدخل بيانات الفرع الأساسية ثم احفظها ليصبح الفرع متاحًا في بقية وحدات النظام.</p>
            </div>
        </div>
        <form action="ajax/branches/save.php" method="post" data-ajax-form data-reset="false">
            <?= csrf_field(); ?>
            <input type="hidden" name="id" value="<?= e((string) ($branch['id'] ?? '')); ?>">
            <div class="form-grid">
                <div><label>كود الفرع</label><input name="code" required value="<?= e($branch['code'] ?? ''); ?>"></div>
                <div><label>اسم الفرع بالعربية</label><input name="name_ar" required value="<?= e($branch['name_ar'] ?? ''); ?>"></div>
                <div><label>الاسم الإنجليزي</label><input name="name_en" value="<?= e($branch['name_en'] ?? ''); ?>"></div>
                <div><label>المدينة</label><input name="city" value="<?= e($branch['city'] ?? ''); ?>"></div>
                <div><label>الهاتف</label><input name="phone" value="<?= e($branch['phone'] ?? ''); ?>"></div>
                <div><label>البريد</label><input name="email" value="<?= e($branch['email'] ?? ''); ?>"></div>
                <div><label>اسم المدير المسؤول</label><input name="manager_name" value="<?= e($branch['manager_name'] ?? ''); ?>"></div>
                <div><label>تاريخ الافتتاح</label><input type="date" name="opening_date" value="<?= e($branch['opening_date'] ?? ''); ?>"></div>
                <div>
                    <label>الحالة</label>
                    <select name="status">
                        <option value="active" <?= ($branch['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>نشط</option>
                        <option value="inactive" <?= ($branch['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>موقوف</option>
                    </select>
                </div>
            </div>
            <div class="mt-2"><label>العنوان</label><input name="address" value="<?= e($branch['address'] ?? ''); ?>"></div>
            <div class="mt-2"><label>ملاحظات</label><textarea name="notes"><?= e($branch['notes'] ?? ''); ?></textarea></div>
            <div class="mt-2">
                <button class="btn btn-primary" type="submit">حفظ الفرع</button>
                <a class="btn btn-light" href="index.php?module=branches">إلغاء</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($branch): ?>
    <div class="grid grid-4 mt-2">
        <div class="card stat-card"><div class="stat-label">المخازن</div><div class="stat-value"><?= e((string) count($warehouses)); ?></div></div>
        <div class="card stat-card"><div class="stat-label">المستخدمون</div><div class="stat-value"><?= e((string) ($branchStatsMap[(int) $branch['id']]['users_count'] ?? 0)); ?></div></div>
        <div class="card stat-card"><div class="stat-label">المسوقون</div><div class="stat-value"><?= e((string) ($branchStatsMap[(int) $branch['id']]['marketers_count'] ?? 0)); ?></div></div>
        <div class="card stat-card"><div class="stat-label">إجمالي المبيعات</div><div class="stat-value"><?= e(format_currency($branchStatsMap[(int) $branch['id']]['sales_total'] ?? 0)); ?></div></div>
    </div>

    <div class="grid grid-2 mt-2">
        <div class="card">
            <h3>إضافة مخزن للفرع</h3>
            <form action="ajax/branches/warehouse-save.php" method="post" data-ajax-form>
                <?= csrf_field(); ?>
                <input type="hidden" name="branch_id" value="<?= e((string) $branch['id']); ?>">
                <div class="form-grid">
                    <div><label>كود المخزن</label><input name="code" required></div>
                    <div><label>اسم المخزن</label><input name="name" required></div>
                    <div><label>اسم المسؤول</label><input name="manager_name"></div>
                </div>
                <div class="mt-2"><label>العنوان</label><input name="address"></div>
                <div class="mt-2"><label>ملاحظات</label><textarea name="notes"></textarea></div>
                <div class="mt-2"><button class="btn btn-light" type="submit">إضافة مخزن</button></div>
            </form>
        </div>
        <div class="card">
            <h3>مخازن الفرع</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>الكود</th><th>الاسم</th><th>المسؤول</th><th>الحالة</th></tr></thead>
                    <tbody>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <tr>
                            <td><?= e($warehouse['code']); ?></td>
                            <td><?= e($warehouse['name']); ?></td>
                            <td><?= e($warehouse['manager_name'] ?? '-'); ?></td>
                            <td><span class="badge <?= ($warehouse['status'] ?? 'active') === 'active' ? 'success' : 'warning'; ?>"><?= e(($warehouse['status'] ?? 'active') === 'active' ? 'نشط' : 'موقوف'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
