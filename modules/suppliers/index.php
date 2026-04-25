<?php
require_once __DIR__ . '/../../includes/helpers/functions.php';
$pageSubtitle = 'إدارة الموردين والمستحقات وسجل الشراء';
$crud = new CrudService();
$isSuperAdmin = Auth::isSuperAdmin();
$accessibleBranchIds = Auth::branchIds();
$editId = (int) request_input('edit', 0);

// Enforce update permission for supplier edit
if ($editId > 0 && !Auth::can('suppliers.update')) {
    $editId = 0;
}

$currentPage = max(1, (int) request_input('page', 1));
$perPage = (int) app_config('per_page');
$supplier = $editId ? $crud->find('suppliers', $editId) : null;

// Apply branch filtering for non-super admins
$suppliersFilters = ['company_name' => request_input('search', '')];
if (!$isSuperAdmin) {
    if ($accessibleBranchIds) {
        $suppliersFilters['branch_id'] = ['in' => $accessibleBranchIds];
    } else {
        $suppliersFilters['branch_id'] = ['in' => [0]]; // No accessible branches
    }
}
$suppliersPage = $crud->paginate('suppliers', $suppliersFilters, $currentPage, $perPage);
$branches = branches_options();
$branchMap = [];
foreach ($branches as $branchItem) {
    $branchMap[(int) $branchItem['id']] = $branchItem;
}
?>
<div class="grid grid-2">
    <div class="card">
        <h3><?= $supplier ? 'تعديل مورد' : 'إضافة مورد'; ?></h3>
        <form action="ajax/suppliers/save.php" method="post" data-ajax-form>
            <?= csrf_field(); ?>
            <input type="hidden" name="id" value="<?= e((string) ($supplier['id'] ?? '')); ?>">
            <div class="form-grid">
                <div><label>الكود</label><input name="code" required value="<?= e($supplier['code'] ?? ''); ?>"></div>
                <div><label>اسم الشركة</label><input name="company_name" required value="<?= e($supplier['company_name'] ?? ''); ?>"></div>
                <div><label>اسم المسؤول</label><input name="contact_name" value="<?= e($supplier['contact_name'] ?? ''); ?>"></div>
                <div><label>الهاتف</label><input name="phone" value="<?= e($supplier['phone'] ?? ''); ?>"></div>
                <div><label>هاتف بديل</label><input name="alt_phone" value="<?= e($supplier['alt_phone'] ?? ''); ?>"></div>
                <div><label>البريد</label><input name="email" value="<?= e($supplier['email'] ?? ''); ?>"></div>
                <div><label>المدينة</label><input name="city" value="<?= e($supplier['city'] ?? ''); ?>"></div>
                <div>
                    <label>الفرع</label>
                    <select name="branch_id">
                        <option value="">بدون</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= e((string) $branch['id']); ?>" <?= (string) ($supplier['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : ''; ?>><?= e($branch['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>الرصيد الافتتاحي</label><input type="number" step="0.01" name="opening_balance" value="<?= e($supplier['opening_balance'] ?? '0'); ?>"></div>
                <div><label>الرقم الضريبي</label><input name="tax_number" value="<?= e($supplier['tax_number'] ?? ''); ?>"></div>
                <div>
                    <label>الحالة</label>
                    <select name="status">
                        <option value="active" <?= ($supplier['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>نشط</option>
                        <option value="inactive" <?= ($supplier['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>موقوف</option>
                    </select>
                </div>
                <div class="col-span-2"><label>العنوان</label><input name="address" value="<?= e($supplier['address'] ?? ''); ?>"></div>
            </div>
            <div class="mt-2">
                <label>ملاحظات</label>
                <textarea name="notes"><?= e($supplier['notes'] ?? ''); ?></textarea>
            </div>
            <div class="mt-2">
                <?php if (Auth::can('suppliers.create') || Auth::can('suppliers.update')): ?>
                    <button class="btn btn-primary" type="submit">حفظ المورد</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="card">
        <div class="toolbar">
            <h3>قائمة الموردين</h3>
            <form method="get" class="toolbar-search">
                <input type="hidden" name="module" value="suppliers">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="بحث" value="<?= e(request_input('search', '')); ?>">
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الكود</th><th>الشركة</th><th>الفرع</th><th>الهاتف</th><th>الرصيد الافتتاحي</th><th>إجراءات</th></tr></thead>
                <tbody>
                <?php foreach ($suppliersPage['data'] as $row): ?>
                    <tr>
                        <td><?= e($row['code']); ?></td>
                        <td><?= e($row['company_name']); ?></td>
                        <td><?= e($branchMap[(int) ($row['branch_id'] ?? 0)]['name_ar'] ?? '-'); ?></td>
                        <td><?= e($row['phone']); ?></td>
                        <td><?= e(format_currency($row['opening_balance'])); ?></td>
                        <td>
                            <?php if (Auth::can('suppliers.update')): ?>
                                <a class="btn btn-light" href="index.php?module=suppliers&edit=<?= e((string) $row['id']); ?>">تعديل</a>
                            <?php endif; ?>
                            <?php if (Auth::can('suppliers.delete')): ?>
                                <button type="button" class="btn btn-danger" data-id="<?= e((string) $row['id']); ?>" data-delete-url="ajax/suppliers/delete.php" data-confirm="هل تريد حذف المورد؟">حذف</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($suppliersPage, $currentPage, $perPage, ['module' => 'suppliers', 'search' => request_input('search', '')]); ?>
    </div>
</div>
