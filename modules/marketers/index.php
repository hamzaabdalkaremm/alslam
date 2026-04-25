<?php
require_once __DIR__ . '/../../includes/helpers/functions.php';
$crud = new CrudService();
$isSuperAdmin = Auth::isSuperAdmin();
$accessibleBranchIds = Auth::branchIds();
$editId = (int) request_input('edit', 0);
$showForm = $editId > 0 || request_input('show_form', '') === '1';

// Enforce update permission for marketer edit
if ($editId > 0 && !Auth::can('marketers.update')) {
    $editId = 0;
    $showForm = false;
} elseif ($showForm && $editId == 0 && !Auth::can('marketers.create')) {
    $showForm = false;
}

$marketer = $editId ? $crud->find('marketers', $editId) : null;
$nextMarketerCode = $marketer['code'] ?? next_marketer_code();
$branches = branches_options();
$branchSelections = [];
if ($marketer) {
    $stmt = Database::connection()->prepare('SELECT branch_id FROM marketer_branches WHERE marketer_id = :marketer_id');
    $stmt->execute(['marketer_id' => $marketer['id']]);
    $branchSelections = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// Apply branch filtering for non-super admins
$marketersWhere = 'm.deleted_at IS NULL';
$marketersParams = [];
if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $placeholders = [];
        foreach ($accessibleBranchIds as $index => $branchId) {
            $placeholder = ':branch_' . $index;
            $placeholders[] = $placeholder;
            $marketersParams['branch_' . $index] = (int) $branchId;
        }
        $marketersWhere .= ' AND EXISTS (SELECT 1 FROM marketer_branches mb WHERE mb.marketer_id = m.id AND mb.branch_id IN (' . implode(', ', $placeholders) . '))';
    } else {
        $marketersWhere .= ' AND 1 = 0';
    }
}

$stmt = Database::connection()->prepare(
    "SELECT m.*,
            COALESCE(branches.branch_names, '') AS branch_names,
            COALESCE(c.customers_count, 0) AS customers_count,
            COALESCE(s.sales_total, 0) AS sales_total,
            COALESCE(dc.collections_total, 0) AS collections_total
     FROM marketers m
     LEFT JOIN (
         SELECT mb.marketer_id,
                GROUP_CONCAT(DISTINCT b.name_ar ORDER BY b.name_ar SEPARATOR '، ') AS branch_names
         FROM marketer_branches mb
         INNER JOIN branches b ON b.id = mb.branch_id AND b.deleted_at IS NULL
         GROUP BY mb.marketer_id
     ) branches ON branches.marketer_id = m.id
     LEFT JOIN (
         SELECT marketer_id, COUNT(*) AS customers_count
         FROM customers
         WHERE deleted_at IS NULL AND marketer_id IS NOT NULL
         GROUP BY marketer_id
     ) c ON c.marketer_id = m.id
     LEFT JOIN (
         SELECT marketer_id, COALESCE(SUM(total_amount), 0) AS sales_total
         FROM sales
         WHERE deleted_at IS NULL AND marketer_id IS NOT NULL
         GROUP BY marketer_id
     ) s ON s.marketer_id = m.id
     LEFT JOIN (
         SELECT marketer_id, COALESCE(SUM(amount), 0) AS collections_total
         FROM debt_collections
         WHERE marketer_id IS NOT NULL
         GROUP BY marketer_id
     ) dc ON dc.marketer_id = m.id
     WHERE {$marketersWhere}
     ORDER BY m.full_name ASC"
);
$stmt->execute($marketersParams);
$marketers = $stmt->fetchAll();
?>

<div class="card">
    <div class="toolbar">
        <div>
            <h3>لوحة أداء المسوقين</h3>
            <p class="card-intro">ملخص العملاء والمبيعات والتحصيلات المنسوبة لكل مسوق.</p>
        </div>
        <div>
            <?php if ($showForm): ?>
                <a class="btn btn-light" href="index.php?module=marketers">إغلاق النموذج</a>
            <?php else: ?>
                <?php if (Auth::can('marketers.create')): ?>
                    <a class="btn btn-primary" href="index.php?module=marketers&show_form=1">إضافة مسوق</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>الكود</th><th>الاسم</th><th>الفروع</th><th>العملاء</th><th>المبيعات</th><th>التحصيلات</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php foreach ($marketers as $row): ?>
                <tr>
                    <td><?= e($row['code']); ?></td>
                    <td><?= e($row['full_name']); ?></td>
                    <td><?= e($row['branch_names'] ?: '-'); ?></td>
                    <td><?= e((string) $row['customers_count']); ?></td>
                    <td><?= e(format_currency($row['sales_total'])); ?></td>
                    <td><?= e(format_currency($row['collections_total'])); ?></td>
                        <td>
                            <?php if (Auth::can('marketers.update')): ?>
                                <a class="btn btn-light" href="index.php?module=marketers&edit=<?= e((string) $row['id']); ?>">تعديل</a>
                            <?php endif; ?>
                            <?php if (Auth::can('marketers.delete')): ?>
                                <button type="button" class="btn btn-danger" data-id="<?= e((string) $row['id']); ?>" data-delete-url="ajax/marketers/delete.php" data-confirm="هل تريد تعطيل هذا المسوق؟">تعطيل</button>
                            <?php endif; ?>
                        </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($showForm): ?>
    <div class="card mt-2">
        <div class="toolbar">
            <div>
                <h3><?= $marketer ? 'تعديل مسوق' : 'إضافة مسوق'; ?></h3>
                <p class="card-intro">أدخل بيانات المسوق واربطه بالفروع المناسبة ليظهر في المبيعات والتحصيلات.</p>
            </div>
        </div>
        <form action="ajax/marketers/save.php" method="post" data-ajax-form data-reset="false">
            <?= csrf_field(); ?>
            <input type="hidden" name="id" value="<?= e((string) ($marketer['id'] ?? '')); ?>">
            <div class="form-grid">
                <div><label>كود المسوق</label><input name="code" required value="<?= e($nextMarketerCode); ?>"></div>
                <div><label>الاسم الكامل</label><input name="full_name" required value="<?= e($marketer['full_name'] ?? ''); ?>"></div>
                <div><label>رقم الهاتف</label><input name="phone" value="<?= e($marketer['phone'] ?? ''); ?>"></div>
                <div><label>البريد الإلكتروني</label><input name="email" value="<?= e($marketer['email'] ?? ''); ?>"></div>
                <div><label>العنوان</label><input name="address" value="<?= e($marketer['address'] ?? ''); ?>"></div>
                <div><label>الرقم الوطني / الهوية</label><input name="national_id" value="<?= e($marketer['national_id'] ?? ''); ?>"></div>
                <div><label>نسبة العمولة</label><input type="number" step="0.01" name="commission_rate" value="<?= e($marketer['commission_rate'] ?? '0'); ?>"></div>
                <div>
                    <label>نوع المسوق</label>
                    <select name="marketer_type" id="marketerTypeSelect">
    <option value="marketer" <?= ($marketer['marketer_type'] ?? 'delegate') === 'marketer' ? 'selected' : ''; ?>>مسوق</option>
    <option value="delegate" <?= ($marketer['marketer_type'] ?? '') === 'delegate' ? 'selected' : ''; ?>>مندوب</option>
</select>
<?php $warehouses = warehouses_options(); ?>
<div id="defaultWarehouseWrap">
    <label>مخزن السيارة</label>
    <select name="default_warehouse_id" id="defaultWarehouseSelect">
        <option value="">بدون</option>
        <?php foreach ($warehouses as $warehouse): ?>
            <option
                value="<?= e((string) $warehouse['id']); ?>"
                <?= (string) ($marketer['default_warehouse_id'] ?? '') === (string) $warehouse['id'] ? 'selected' : ''; ?>>
                <?= e($warehouse['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <small class="field-help">يظهر فقط إذا كان النوع = مسوق</small>
</div>
                </div>
                
                <div>
                    <label>حالة النشاط</label>
                    <select name="status">
                        <option value="active" <?= ($marketer['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>نشط</option>
                        <option value="inactive" <?= ($marketer['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>موقوف</option>
                    </select>
                </div>
                <div><label>تاريخ التوظيف</label><input type="date" name="employment_date" value="<?= e($marketer['employment_date'] ?? ''); ?>"></div>
            </div>
            <div class="mt-2">
                <label>الفروع المرتبطة</label>
                <div class="checkbox-grid">
                    <?php foreach ($branches as $branch): ?>
                        <label><input type="checkbox" name="branch_ids[]" value="<?= e((string) $branch['id']); ?>" <?= in_array((int) $branch['id'], $branchSelections, true) ? 'checked' : ''; ?>> <?= e($branch['name_ar']); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mt-2"><label>ملاحظات</label><textarea name="notes"><?= e($marketer['notes'] ?? ''); ?></textarea></div>
            <div class="mt-2">
                <?php if (Auth::can('marketers.create') || Auth::can('marketers.update')): ?>
                    <button class="btn btn-primary" type="submit">حفظ المسوق</button>
                <?php endif; ?>
                <a class="btn btn-light" href="index.php?module=marketers">إلغاء</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('marketerTypeSelect');
    const warehouseWrap = document.getElementById('defaultWarehouseWrap');
    const warehouseSelect = document.getElementById('defaultWarehouseSelect');

    function toggleWarehouseField() {
        const isMarketer = typeSelect.value === 'marketer';
        warehouseWrap.style.display = isMarketer ? '' : 'none';
        if (!isMarketer && warehouseSelect) {
            warehouseSelect.value = '';
        }
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', toggleWarehouseField);
        toggleWarehouseField();
    }
});
</script>